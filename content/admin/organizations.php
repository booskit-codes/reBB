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

// Logging function for organization admin actions
function logOrganizationAdminAction(string $action, bool $success = true, ?string $orgId = null, ?string $targetUserId = null, array $details = []) {
    $logFile = STORAGE_DIR . '/logs/organization_activity.log';
    $logsDir = dirname($logFile);

    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $adminUser = auth()->getUser();
    $adminUsername = $adminUser ? $adminUser['username'] : 'UnknownAdmin';
    $status = $success ? 'SUCCESS' : 'FAILED';

    $logEntry = "[$timestamp] [$status] [Admin:$adminUsername] [IP:$ip]";
    if ($orgId) {
        $logEntry .= " [OrgID:$orgId]";
    }
    if ($targetUserId) {
        $logEntry .= " [TargetUserID:$targetUserId]";
    }
    $logEntry .= " Action: " . $action;
    if (!empty($details)) {
        $logEntry .= " Details: " . json_encode($details);
    }
    $logEntry .= PHP_EOL;

    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

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

// --- Admin: Handle Edit Organization Details (Name) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_edit_organization_details') {
    auth()->requireRole(Auth::ROLE_ADMIN);
    $orgIdToEdit = isset($_POST['organization_id_to_edit']) ? (int)$_POST['organization_id_to_edit'] : null;
    $newOrgName = isset($_POST['new_organization_name']) ? trim($_POST['new_organization_name']) : null;

    if (!$orgIdToEdit || empty($newOrgName)) {
        $actionMessage = "Missing Organization ID or new name for editing details.";
        $actionMessageType = 'danger';
    } else {
        try {
            if (!$organizationsStore) {
                $dbPath = ROOT_DIR . '/db';
                $organizationsStore = new \SleekDB\Store('organizations', $dbPath, ['auto_cache' => false, 'timeout' => false]);
            }
            $orgEntry = $organizationsStore->findById($orgIdToEdit);
            if (!$orgEntry) {
                $actionMessage = "Organization not found for editing.";
                $actionMessageType = 'danger';
            } else {
                $updated = $organizationsStore->updateById($orgIdToEdit, ['organization_name' => $newOrgName, 'updated_at' => time()]);
                if ($updated) {
                    logOrganizationAdminAction("Updated organization name", true, (string)$orgIdToEdit, null, ['old_name' => $orgEntry['organization_name'], 'new_name' => $newOrgName]);
                    $actionMessage = "Organization name updated successfully to '{$newOrgName}'.";
                    $actionMessageType = 'success';
                    // Refresh data if viewing this org or the list
                    if (isset($_GET['view_org_id']) && (int)$_GET['view_org_id'] === $orgIdToEdit) {
                        $viewingOrganization = $organizationsStore->findById($orgIdToEdit); // Re-fetch
                         // Also re-fetch owner username for the specific org being viewed
                        $ownerUser = $usersStore->findById($viewingOrganization['owner_user_id']);
                        $viewingOrganization['owner_username'] = ($ownerUser && isset($ownerUser['username'])) ? $ownerUser['username'] : 'Unknown';
                        $viewingOrganization['created_at_formatted'] = isset($viewingOrganization['created_at']) ? date('Y-m-d H:i', $viewingOrganization['created_at']) : 'N/A';
                    }
                    // Refresh allOrganizations list
                    $allOrganizations = []; // Clear it first
                    $rawOrganizations = $organizationsStore->findAll(['created_at' => 'desc']);
                     foreach ($rawOrganizations as $org) {
                        $ownerName = 'Unknown';
                        if (isset($org['owner_user_id']) && $usersStore) {
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
                    $actionMessage = "Failed to update organization name.";
                    $actionMessageType = 'danger';
                    logOrganizationAdminAction("Failed to update organization name", false, (string)$orgIdToEdit, null, ['new_name' => $newOrgName]);
                }
            }
        } catch (\Exception $e) {
            $actionMessage = "Error editing organization details: " . $e->getMessage();
            $actionMessageType = 'danger';
            error_log("Admin Edit Organization Details Error: " . $e->getMessage());
            logOrganizationAdminAction("Error editing organization details", false, (string)$orgIdToEdit, null, ['error' => $e->getMessage()]);
        }
    }
}

// --- Admin: Handle Remove Organization Member ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_remove_org_member') {
    auth()->requireRole(Auth::ROLE_ADMIN);
    $userToRemoveId = isset($_POST['user_to_remove_id']) ? (int)$_POST['user_to_remove_id'] : null;
    $organizationId = isset($_POST['organization_id_for_remove']) ? (int)$_POST['organization_id_for_remove'] : null; // Matched to frontend form

    if (!$userToRemoveId || !$organizationId) {
        $actionMessage = "Missing data for removing member.";
        $actionMessageType = 'danger';
    } else {
        try {
            if (!$orgMembersStore || !$userOrgStore || !$usersStore) { // Ensure stores are initialized
                $dbPath = ROOT_DIR . '/db';
                $orgMembersStore = new \SleekDB\Store('organization_members', $dbPath, ['auto_cache' => false, 'timeout' => false]);
                $userOrgStore = new \SleekDB\Store('user_to_organization_links', $dbPath, ['auto_cache' => false, 'timeout' => false]);
                $usersStore = new \SleekDB\Store('users', $dbPath, ['auto_cache' => false, 'timeout' => false]);
            }

            $memberEntry = $orgMembersStore->findOneBy([
                ['user_id', '=', $userToRemoveId],
                'AND',
                ['organization_id', '=', $organizationId]
            ]);

            if (!$memberEntry) {
                $actionMessage = "Member not found in this organization for removal.";
                $actionMessageType = 'danger';
            } else {
                // Prevent removing the last owner
                if ($memberEntry['organization_role'] === 'organization_owner') {
                    $ownerCount = $orgMembersStore->count([
                        ['organization_id', '=', $organizationId],
                        'AND',
                        ['organization_role', '=', 'organization_owner']
                    ]);
                    if ($ownerCount <= 1) {
                        $actionMessage = "Cannot remove the last organization owner. Assign another owner first or delete the organization.";
                        $actionMessageType = 'warning';
                    }
                }

                if ($actionMessageType !== 'warning' && $actionMessageType !== 'danger') {
                    // Proceed with deletion
                    $deletedMember = $orgMembersStore->deleteById($memberEntry['_id']);
                    if ($deletedMember) {
                        logOrganizationAdminAction("Removed member from organization", true, (string)$organizationId, (string)$userToRemoveId, ['removed_role' => $memberEntry['organization_role']]);
                        $actionMessage = "Member removed from organization successfully.";
                        $actionMessageType = 'success';

                        // If the user's global role is ROLE_ORGANIZATION_USER, remove their primary link to this org.
                        $userGlobalData = $usersStore->findById($userToRemoveId);
                        if ($userGlobalData && $userGlobalData['role'] === Auth::ROLE_ORGANIZATION_USER) {
                            $userOrgLink = $userOrgStore->findOneBy([
                                ['user_id', '=', $userToRemoveId],
                                'AND',
                                ['organization_id', '=', $organizationId]
                            ]);
                            if ($userOrgLink) {
                                $userOrgStore->deleteById($userOrgLink['_id']);
                                $actionMessage .= " User's primary link to this organization was also removed.";
                            }
                        }
                         // Refresh data for the view
                        if (isset($_GET['view_org_id']) && (int)$_GET['view_org_id'] === $organizationId) {
                            $viewingOrganizationMembers = []; // Clear to force re-fetch
                        }

                    } else {
                        logOrganizationAdminAction("Failed to remove member from organization", false, (string)$organizationId, (string)$userToRemoveId);
                        $actionMessage = "Failed to remove member from organization.";
                        $actionMessageType = 'danger';
                    }
                }
            }
        } catch (\Exception $e) {
            $actionMessage = "Error removing member: " . $e->getMessage();
            $actionMessageType = 'danger';
            error_log("Admin Remove Member Error: " . $e->getMessage());
            logOrganizationAdminAction("Error removing member", false, (string)$organizationId, (string)$userToRemoveId, ['error' => $e->getMessage()]);
        }
    }
}

// --- Admin: Handle Edit Organization Member Role ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_edit_org_member_role') {
    auth()->requireRole(Auth::ROLE_ADMIN); // Double-check admin privileges for this action block
    $userToEditId = isset($_POST['user_to_edit_id']) ? (int)$_POST['user_to_edit_id'] : null;
    $organizationId = isset($_POST['organization_id_for_edit']) ? (int)$_POST['organization_id_for_edit'] : null; // Matched to frontend form
    $newOrganizationRole = $_POST['new_organization_role'] ?? null;

    if (!$userToEditId || !$organizationId || !$newOrganizationRole) {
        $actionMessage = "Missing data for editing member role.";
        $actionMessageType = 'danger';
    } elseif (!in_array($newOrganizationRole, ['organization_owner', 'organization_admin', 'organization_member'])) {
        $actionMessage = "Invalid new role specified.";
        $actionMessageType = 'danger';
    } else {
        try {
            if (!$orgMembersStore) { // Ensure store is initialized
                $dbPath = ROOT_DIR . '/db';
                $orgMembersStore = new \SleekDB\Store('organization_members', $dbPath, ['auto_cache' => false, 'timeout' => false]);
            }

            $memberEntry = $orgMembersStore->findOneBy([
                ['user_id', '=', $userToEditId],
                'AND',
                ['organization_id', '=', $organizationId]
            ]);

            if (!$memberEntry) {
                $actionMessage = "Member not found in this organization.";
                $actionMessageType = 'danger';
            } else {
                // Prevent changing the role of the last owner to a non-owner role
                if ($memberEntry['organization_role'] === 'organization_owner' && $newOrganizationRole !== 'organization_owner') {
                    $ownerCount = $orgMembersStore->count([
                        ['organization_id', '=', $organizationId],
                        'AND',
                        ['organization_role', '=', 'organization_owner']
                    ]);
                    if ($ownerCount <= 1) {
                        $actionMessage = "Cannot change the role of the last owner to a non-owner role. Assign another owner first.";
                        $actionMessageType = 'warning';
                    }
                }

                if ($actionMessageType !== 'warning' && $actionMessageType !== 'danger') { // Proceed if no warning/error yet
                    $updated = $orgMembersStore->updateById($memberEntry['_id'], ['organization_role' => $newOrganizationRole]);
                    if ($updated) {
                        logOrganizationAdminAction("Updated member role", true, (string)$organizationId, (string)$userToEditId, ['old_role' => $memberEntry['organization_role'], 'new_role' => $newOrganizationRole]);
                        $actionMessage = "Member's organization role updated successfully.";
                        $actionMessageType = 'success';
                        // Refresh data for the view if currently viewing this org
                        if (isset($_GET['view_org_id']) && (int)$_GET['view_org_id'] === $organizationId) {
                            $viewingOrganizationMembers = [];
                        }
                    } else {
                        logOrganizationAdminAction("Failed to update member role", false, (string)$organizationId, (string)$userToEditId, ['new_role' => $newOrganizationRole]);
                        $actionMessage = "Failed to update member's role.";
                        $actionMessageType = 'danger';
                    }
                }
            }
        } catch (\Exception $e) {
            $actionMessage = "Error editing member role: " . $e->getMessage();
            $actionMessageType = 'danger';
            error_log("Admin Edit Member Role Error: " . $e->getMessage());
            logOrganizationAdminAction("Error editing member role", false, (string)$organizationId, (string)$userToEditId, ['error' => $e->getMessage()]);
        }
    }
}

// --- Handle Organization Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_organization']) && isset($_POST['organization_id_to_delete'])) {
    $orgIdToDelete = $_POST['organization_id_to_delete'];

    // Ensure DB stores are initialized within this POST scope
    $dbPath = ROOT_DIR . '/db';
    if (!isset($organizationsStore) || !is_object($organizationsStore)) {
        $organizationsStore = new \SleekDB\Store('organizations', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    }
    if (!isset($orgMembersStore) || !is_object($orgMembersStore)) {
        $orgMembersStore = new \SleekDB\Store('organization_members', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    }
    if (!isset($userOrgStore) || !is_object($userOrgStore)) {
        $userOrgStore = new \SleekDB\Store('user_to_organization_links', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    }
    // $usersStore is used by logOrganizationAdminAction, ensure it's available if not already
    if (!isset($usersStore) || !is_object($usersStore)) {
        $usersStore = new \SleekDB\Store('users', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    }

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
                logOrganizationAdminAction("Deleted organization", true, (string)$orgIdToDelete);
                $actionMessage = "Organization ID '{$orgIdToDelete}' and all associated member links have been deleted.";
                $actionMessageType = 'success';
                error_log("Admin: Successfully deleted organization ID: " . $orgIdToDelete); // Keep existing error_log for system level
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
                logOrganizationAdminAction("Failed to delete organization", false, (string)$orgIdToDelete);
                $actionMessage = "Failed to delete organization ID '{$orgIdToDelete}'. It might have already been deleted or does not exist.";
                $actionMessageType = 'warning';
                error_log("Admin: Failed to delete organization ID: " . $orgIdToDelete . " from organizations store."); // Keep system log
            }

        } catch (\Exception $e) {
            $actionMessage = "Error deleting organization: " . $e->getMessage();
            $actionMessageType = 'danger';
            error_log("Admin Delete Organization Error: " . $e->getMessage()); // Keep system log
            logOrganizationAdminAction("Error deleting organization", false, (string)$orgIdToDelete, null, ['error' => $e->getMessage()]);
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
                <button type="button" class="btn btn-sm btn-outline-secondary admin-edit-org-details-btn mt-2"
                        data-bs-toggle="modal" data-bs-target="#adminEditOrgDetailsModal"
                        data-org-id="<?php echo htmlspecialchars($viewingOrganization['_id']); ?>"
                        data-org-name="<?php echo htmlspecialchars($viewingOrganization['organization_name']); ?>">
                    <i class="bi bi-pencil-square"></i> Edit Name
                </button>
                <!-- Add Delete button for org itself later -->
            </div>
        </div>

        <div class="card mt-4">
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
                                            <button type="button" class="btn btn-sm btn-outline-secondary admin-edit-member-role-btn"
                                                    data-bs-toggle="modal" data-bs-target="#adminEditMemberRoleModal"
                                                    data-user-id="<?php echo htmlspecialchars($member['user_id']); ?>"
                                                    data-username="<?php echo htmlspecialchars($member['username']); ?>"
                                                    data-current-role="<?php echo htmlspecialchars($member['organization_role']); ?>"
                                                    data-organization-id="<?php echo htmlspecialchars($viewingOrganization['_id']); // Pass org ID for the form ?>">
                                                <i class="bi bi-pencil-square"></i> Edit Role
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger admin-remove-member-btn"
                                                    data-bs-toggle="modal" data-bs-target="#adminRemoveMemberModal"
                                                    data-user-id="<?php echo htmlspecialchars($member['user_id']); ?>"
                                                    data-username="<?php echo htmlspecialchars($member['username']); ?>"
                                                    data-organization-id="<?php echo htmlspecialchars($viewingOrganization['_id']); ?>">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
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
                                        <button type="button" class="btn btn-sm btn-outline-secondary admin-edit-org-details-btn"
                                                data-bs-toggle="modal" data-bs-target="#adminEditOrgDetailsModal"
                                                data-org-id="<?php echo htmlspecialchars($org['_id']); ?>"
                                                data-org-name="<?php echo htmlspecialchars($org['organization_name']); ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
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

<!-- Admin Edit Member Role Modal -->
<div class="modal fade" id="adminEditMemberRoleModal" tabindex="-1" aria-labelledby="adminEditMemberRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo site_url('admin/organizations?view_org_id=' . htmlspecialchars($viewingOrganization['_id'] ?? '')); ?>">
                <input type="hidden" name="action" value="admin_edit_org_member_role">
                <input type="hidden" name="user_to_edit_id" id="adminEditMember_userId">
                <input type="hidden" name="organization_id_for_edit" id="adminEditMember_orgId">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminEditMemberRoleModalLabel">Edit Role for <span id="adminEditMember_username"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Current Organization: <?php echo htmlspecialchars($viewingOrganization['organization_name'] ?? 'N/A'); ?> (ID: <span id="adminEditMember_displayOrgId"></span>)</p>
                    <div class="mb-3">
                        <label for="adminEditMember_newRole" class="form-label">New Organization Role</label>
                        <select class="form-select" id="adminEditMember_newRole" name="new_organization_role">
                            <option value="organization_member">Member</option>
                            <option value="organization_admin">Admin</option>
                            <option value="organization_owner">Owner</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Admin Remove Member Modal -->
<div class="modal fade" id="adminRemoveMemberModal" tabindex="-1" aria-labelledby="adminRemoveMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo site_url('admin/organizations?view_org_id=' . htmlspecialchars($viewingOrganization['_id'] ?? '')); ?>">
                <input type="hidden" name="action" value="admin_remove_org_member">
                <input type="hidden" name="user_to_remove_id" id="adminRemoveMember_userId">
                <input type="hidden" name="organization_id_for_remove" id="adminRemoveMember_orgId">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminRemoveMemberModalLabel">Confirm Member Removal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove user <strong id="adminRemoveMember_username"></strong> from this organization?</p>
                    <p class="text-danger">This action cannot be undone. If the user's global role is 'Organization User' and this is their only organization, their primary link to an organization will be removed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Remove</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Admin Edit Organization Details Modal -->
<div class="modal fade" id="adminEditOrgDetailsModal" tabindex="-1" aria-labelledby="adminEditOrgDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo site_url('admin/organizations' . (isset($_GET['view_org_id']) ? '?view_org_id=' . htmlspecialchars($_GET['view_org_id']) : '')); ?>">
                <input type="hidden" name="action" value="admin_edit_organization_details">
                <input type="hidden" name="organization_id_to_edit" id="adminEditOrg_idInput">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminEditOrgDetailsModalLabel">Edit Organization Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="adminEditOrg_newNameInput" class="form-label">Organization Name</label>
                        <input type="text" class="form-control" id="adminEditOrg_newNameInput" name="new_organization_name" required>
                    </div>
                    <!-- Future: Add field for changing owner -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
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

    const adminEditMemberRoleModal = document.getElementById('adminEditMemberRoleModal');
    if (adminEditMemberRoleModal) {
        adminEditMemberRoleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const currentRole = button.getAttribute('data-current-role');
            const organizationId = button.getAttribute('data-organization-id');

            const modalUserIdInput = adminEditMemberRoleModal.querySelector('#adminEditMember_userId');
            const modalOrgIdInput = adminEditMemberRoleModal.querySelector('#adminEditMember_orgId');
            const modalUsernameDisplay = adminEditMemberRoleModal.querySelector('#adminEditMember_username');
            const modalRoleSelect = adminEditMemberRoleModal.querySelector('#adminEditMember_newRole');
            const modalDisplayOrgId = adminEditMemberRoleModal.querySelector('#adminEditMember_displayOrgId');

            if(modalUserIdInput) modalUserIdInput.value = userId;
            if(modalOrgIdInput) modalOrgIdInput.value = organizationId;
            if(modalUsernameDisplay) modalUsernameDisplay.textContent = username;
            if(modalDisplayOrgId) modalDisplayOrgId.textContent = organizationId;

            if (modalRoleSelect) {
                // Set the selected value in the dropdown
                for (let i = 0; i < modalRoleSelect.options.length; i++) {
                    if (modalRoleSelect.options[i].value === currentRole) {
                        modalRoleSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        });
    }

    const adminRemoveMemberModal = document.getElementById('adminRemoveMemberModal');
    if (adminRemoveMemberModal) {
        adminRemoveMemberModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const organizationId = button.getAttribute('data-organization-id');

            const modalUserIdInput = adminRemoveMemberModal.querySelector('#adminRemoveMember_userId');
            const modalOrgIdInput = adminRemoveMemberModal.querySelector('#adminRemoveMember_orgId');
            const modalUsernameDisplay = adminRemoveMemberModal.querySelector('#adminRemoveMember_username');

            if(modalUserIdInput) modalUserIdInput.value = userId;
            if(modalOrgIdInput) modalOrgIdInput.value = organizationId;
            if(modalUsernameDisplay) modalUsernameDisplay.textContent = username;
        });
    }

    const adminEditOrgDetailsModal = document.getElementById('adminEditOrgDetailsModal');
    if (adminEditOrgDetailsModal) {
        adminEditOrgDetailsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const orgId = button.getAttribute('data-org-id');
            const orgName = button.getAttribute('data-org-name');

            const modalOrgIdInput = adminEditOrgDetailsModal.querySelector('#adminEditOrg_idInput');
            const modalOrgNameInput = adminEditOrgDetailsModal.querySelector('#adminEditOrg_newNameInput');

            if(modalOrgIdInput) modalOrgIdInput.value = orgId;
            if(modalOrgNameInput) modalOrgNameInput.value = orgName;
        });
    }
});
</script>
<?php
$GLOBALS['page_javascript'] = ob_get_clean(); // Assign the script to global var

require_once ROOT_DIR . '/includes/master.php';
?>
