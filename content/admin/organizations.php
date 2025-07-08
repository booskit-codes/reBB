<?php
/**
 * reBB - Admin - Manage Organizations
 *
 * Allows system administrators to view and manage all organizations.
 */

// Require admin authentication
auth()->requireRole(Auth::ROLE_ADMIN, 'login');
$currentUser = auth()->getUser(); // Get current admin user

// Initialize variables
$actionMessage = '';
$actionMessageType = 'info';
$allOrganizations = [];
$viewingOrganization = null;
$viewingOrganizationMembers = [];

// Initialize SleekDB stores
$organizationsStore = null;
$usersStore = null;
$orgMembersStore = null;

try {
    $dbPath = ROOT_DIR . '/db';
    $organizationsStore = new \SleekDB\Store('organizations', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    $usersStore = new \SleekDB\Store('users', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    $orgMembersStore = new \SleekDB\Store('organization_members', $dbPath, ['auto_cache' => false, 'timeout' => false]);

    $rawOrganizations = $organizationsStore->findAll(['created_at' => 'desc']);

    foreach ($rawOrganizations as $org) {
        $ownerName = 'Unknown';
        if (isset($org['owner_user_id'])) {
            $ownerUser = $usersStore->findById($org['owner_user_id']);
            if ($ownerUser && isset($ownerUser['username'])) {
                $ownerName = $ownerUser['username'];
            }
        }

        $memberCount = 0;
        if ($orgMembersStore) {
            $memberCount = $orgMembersStore->count(['organization_id', '=', $org['_id']]);
        }

        $allOrganizations[] = array_merge($org, [
            'owner_username' => $ownerName,
            'member_count' => $memberCount,
            'created_at_formatted' => isset($org['created_at']) ? date('Y-m-d H:i', $org['created_at']) : 'N/A',
        ]);
    }

} catch (\Exception $e) {
    $actionMessage = "Error fetching organizations: " . $e->getMessage();
    $actionMessageType = 'danger';
    error_log("Admin Manage Organizations Error: " . $e->getMessage());
}

// Check if viewing a specific organization
if (isset($_GET['view_org_id'])) {
    $viewOrgId = $_GET['view_org_id'];
    try {
        if ($organizationsStore) {
            $viewingOrganization = $organizationsStore->findById($viewOrgId);
        }
        if ($viewingOrganization && $orgMembersStore && $usersStore) {
            // Fetch owner username for the specific org being viewed
            $ownerUser = $usersStore->findById($viewingOrganization['owner_user_id']);
            $viewingOrganization['owner_username'] = ($ownerUser && isset($ownerUser['username'])) ? $ownerUser['username'] : 'Unknown';
            $viewingOrganization['created_at_formatted'] = isset($viewingOrganization['created_at']) ? date('Y-m-d H:i', $viewingOrganization['created_at']) : 'N/A';


            $rawMembers = $orgMembersStore->findBy(['organization_id', '=', $viewingOrganization['_id']]);
            foreach ($rawMembers as $rawMember) {
                $memberUser = $usersStore->findById($rawMember['user_id']);
                if ($memberUser) {
                    $viewingOrganizationMembers[] = [
                        'user_id' => $rawMember['user_id'],
                        'username' => $memberUser['username'] ?? 'Unknown User',
                        'organization_role' => $rawMember['organization_role'],
                        'added_at_formatted' => isset($rawMember['added_at']) ? date('Y-m-d H:i', $rawMember['added_at']) : 'N/A',
                    ];
                }
            }
            usort($viewingOrganizationMembers, function($a, $b) {
                return strcasecmp($a['username'], $b['username']);
            });
        } elseif (!$viewingOrganization && $organizationsStore) {
            $actionMessage = "Organization with ID {$viewOrgId} not found.";
            $actionMessageType = 'warning';
        }
    } catch (\Exception $e) {
        $actionMessage = "Error fetching organization details: " . $e->getMessage();
        $actionMessageType = 'danger';
        error_log("Admin View Organization Error: " . $e->getMessage());
        $viewingOrganization = null; // Ensure it's null on error
    }
}

// --- Handle Organization Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_organization']) && isset($_POST['organization_id_to_delete'])) {
    $orgIdToDelete = $_POST['organization_id_to_delete'];
    if (empty($orgIdToDelete)) {
        $actionMessage = "Organization ID missing for deletion.";
        $actionMessageType = 'danger';
    } else {
        try {
            // Ensure all necessary stores are initialized (they should be from the top of the script)
            if (!$organizationsStore || !$orgMembersStore || !$userOrgStore) {
                throw new \Exception("Database stores not properly initialized for deletion.");
            }

            // 1. Delete from organization_members
            $membersToDelete = $orgMembersStore->findBy(['organization_id', '=', $orgIdToDelete]);
            foreach ($membersToDelete as $member) {
                $orgMembersStore->deleteById($member['_id']);
            }
            error_log("Admin: Deleted " . count($membersToDelete) . " members for org ID: " . $orgIdToDelete);

            // 2. Delete from user_to_organization_links
            // Note: This might orphan ROLE_ORGANIZATION_USER if this was their only link.
            // A more advanced system might deactivate them or change their role.
            $linksToDelete = $userOrgStore->findBy(['organization_id', '=', $orgIdToDelete]);
            foreach ($linksToDelete as $link) {
                $userOrgStore->deleteById($link['_id']);
            }
            error_log("Admin: Deleted " . count($linksToDelete) . " user-org links for org ID: " . $orgIdToDelete);

            // 3. Delete from organizations store
            $deletedOrg = $organizationsStore->deleteBy(['_id', '=', $orgIdToDelete]); // deleteBy returns number of affected rows

            if ($deletedOrg) {
                $actionMessage = "Organization ID '{$orgIdToDelete}' and all associated member links have been deleted.";
                $actionMessageType = 'success';
                error_log("Admin: Successfully deleted organization ID: " . $orgIdToDelete);
                // Refresh all organizations list as the current list might be stale
                $allOrganizations = []; // Clear it first
                $rawOrganizations = $organizationsStore->findAll(['created_at' => 'desc']);
                foreach ($rawOrganizations as $org) {
                    $ownerName = 'Unknown';
                    if (isset($org['owner_user_id'])) {
                        $ownerUser = $usersStore->findById($org['owner_user_id']);
                        if ($ownerUser && isset($ownerUser['username'])) {
                            $ownerName = $ownerUser['username'];
                        }
                    }
                    $memberCount = $orgMembersStore ? $orgMembersStore->count(['organization_id', '=', $org['_id']]) : 0;
                    $allOrganizations[] = array_merge($org, [
                        'owner_username' => $ownerName,
                        'member_count' => $memberCount,
                        'created_at_formatted' => isset($org['created_at']) ? date('Y-m-d H:i', $org['created_at']) : 'N/A',
                    ]);
                }

            } else {
                $actionMessage = "Failed to delete organization ID '{$orgIdToDelete}'. It might have already been deleted or does not exist.";
                $actionMessageType = 'warning';
                error_log("Admin: Failed to delete organization ID: " . $orgIdToDelete . " from organizations store.");
            }

        } catch (\Exception $e) {
            $actionMessage = "Error deleting organization: " . $e->getMessage();
            $actionMessageType = 'danger';
            error_log("Admin Delete Organization Error: " . $e->getMessage());
        }
    }
}


// Define the page content
ob_start();
?>

<div class="container-admin mt-4">
    <div class="page-header">
        <h1><?php echo $viewingOrganization ? 'Organization: ' . htmlspecialchars($viewingOrganization['organization_name']) : 'Manage Organizations'; ?></h1>
        <div>
        <?php if ($viewingOrganization): ?>
            <a href="<?php echo site_url('admin/organizations'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-list-ul"></i> Back to All Organizations
            </a>
        <?php else: ?>
            <a href="<?php echo site_url('admin'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Admin Dashboard
            </a>
        <?php endif; ?>
        </div>
    </div>

    <?php if ($actionMessage): ?>
        <div class="alert alert-<?php echo $actionMessageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($actionMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($viewingOrganization): ?>
        <!-- Single Organization View -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Organization Details</h4>
            </div>
            <div class="card-body">
                <p><strong>ID:</strong> <?php echo htmlspecialchars($viewingOrganization['_id']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($viewingOrganization['organization_name']); ?></p>
                <p><strong>Owner:</strong> <?php echo htmlspecialchars($viewingOrganization['owner_username']); ?> (ID: <?php echo htmlspecialchars($viewingOrganization['owner_user_id']); ?>)</p>
                <p><strong>Created At:</strong> <?php echo htmlspecialchars($viewingOrganization['created_at_formatted']); ?></p>
                <!-- Add Edit/Delete buttons for org itself later -->
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Members (<?php echo count($viewingOrganizationMembers); ?>)</h4>
                <!-- Add "Link Existing User" button later -->
            </div>
            <div class="card-body">
                <?php if (empty($viewingOrganizationMembers)): ?>
                    <p class="text-center">This organization has no members.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Username</th>
                                    <th>Organization Role</th>
                                    <th>Added At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($viewingOrganizationMembers as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($member['username']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $member['organization_role']))); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['added_at_formatted']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Edit Role</button> <!-- Placeholder -->
                                            <button class="btn btn-sm btn-outline-danger" disabled>Remove</button> <!-- Placeholder -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- List All Organizations View -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">All Organizations (<?php echo count($allOrganizations); ?>)</h4>
            </div>
            <div class="card-body">
                <?php if (empty($allOrganizations) && empty($actionMessage)): ?>
                    <p class="text-center">No organizations found in the system yet.</p>
                <?php elseif (!empty($allOrganizations)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Owner</th>
                                    <th>Members</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allOrganizations as $org): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($org['_id']); ?></td>
                                        <td><?php echo htmlspecialchars($org['organization_name']); ?></td>
                                        <td><?php echo htmlspecialchars($org['owner_username']); ?> (ID: <?php echo htmlspecialchars($org['owner_user_id']); ?>)</td>
                                        <td><?php echo htmlspecialchars($org['member_count']); ?></td>
                                        <td><?php echo htmlspecialchars($org['created_at_formatted']); ?></td>
                                        <td>
                                            <a href="?view_org_id=<?php echo htmlspecialchars($org['_id']); ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> View/Manage
                                            </a>
                                        <button class="btn btn-sm btn-outline-secondary" disabled>Edit</button> <!-- Placeholder for future edit functionality -->
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-org-btn"
                                                data-bs-toggle="modal" data-bs-target="#deleteOrgModal"
                                                data-org-id="<?php echo htmlspecialchars($org['_id']); ?>"
                                                data-org-name="<?php echo htmlspecialchars($org['organization_name']); ?>">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (!empty($actionMessage) && $actionMessageType === 'danger'): ?>
                    <p>Could not load organizations. See error message above.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Organization Confirmation Modal -->
<div class="modal fade" id="deleteOrgModal" tabindex="-1" aria-labelledby="deleteOrgModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo site_url('admin/organizations'); ?>">
                <input type="hidden" name="delete_organization" value="1">
                <input type="hidden" name="organization_id_to_delete" id="deleteOrgIdInput">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteOrgModalLabel">Confirm Organization Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the organization: <strong id="deleteOrgNameDisplay"></strong> (ID: <span id="deleteOrgIdDisplay"></span>)?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. It will remove the organization, all its member associations, and all user-organization links. Forms belonging to this organization will be orphaned.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Organization</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();
$GLOBALS['page_title'] = 'Manage Organizations';
// Add page-specific CSS from admin.css, as it's a common style for admin pages
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">';

ob_start(); // Start a new buffer for page-specific JS
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteOrgModal = document.getElementById('deleteOrgModal');
    if (deleteOrgModal) {
        deleteOrgModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const orgId = button.getAttribute('data-org-id');
            const orgName = button.getAttribute('data-org-name');

            const modalOrgIdInput = deleteOrgModal.querySelector('#deleteOrgIdInput');
            const modalOrgNameDisplay = deleteOrgModal.querySelector('#deleteOrgNameDisplay');
            const modalOrgIdDisplay = deleteOrgModal.querySelector('#deleteOrgIdDisplay');

            if (modalOrgIdInput) modalOrgIdInput.value = orgId;
            if (modalOrgNameDisplay) modalOrgNameDisplay.textContent = orgName;
            if (modalOrgIdDisplay) modalOrgIdDisplay.textContent = orgId;
        });
    }
});
</script>
<?php
$GLOBALS['page_javascript'] = ob_get_clean(); // Assign the script to global var

require_once ROOT_DIR . '/includes/master.php';
?>
