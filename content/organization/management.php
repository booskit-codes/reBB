<?php
/**
 * reBB - Organization Management Page
 */

// Require authentication
auth()->requireAuth('login');
$currentUser = auth()->getUser();
$dbPath = ROOT_DIR . '/db';

$userOrganizationLink = null;
$organization = null;
$organizationForms = [];
$currentUserOrgRole = null; // To store the current user's role in this specific organization
$organizationMembers = []; // To store list of members for display
$actionMessage = '';
$actionMessageType = 'info';

// Initialize SleekDB stores
try {
    $userOrgStore = new \SleekDB\Store('user_to_organization_links', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    $organizationsStore = new \SleekDB\Store('organizations', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    $orgMembersStore = new \SleekDB\Store('organization_members', $dbPath, ['auto_cache' => false, 'timeout' => false]); // For managing members
    $usersStore = new \SleekDB\Store('users', $dbPath, ['auto_cache' => false, 'timeout' => false]); // For fetching usernames
} catch (\Exception $e) {
    $actionMessage = "Error initializing database: " . $e->getMessage();
    $actionMessageType = 'danger';
}

// --- Handle Organization Creation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_organization'])) {
    if (!isset($userOrgStore) || !isset($organizationsStore)) {
        $actionMessage = "Database not available for organization creation.";
        $actionMessageType = 'danger';
    } else {
        $orgName = trim($_POST['organization_name'] ?? '');

        if (empty($orgName)) {
            $actionMessage = "Organization name cannot be empty.";
            $actionMessageType = 'danger';
        } else {
            // Check if user already in an org (again, just in case)
            $existingLink = $userOrgStore->findOneBy(['user_id', '=', $currentUser['_id']]);
            if ($existingLink) {
                $actionMessage = "You already belong to an organization.";
                $actionMessageType = 'warning';
            } else {
                try {
                    $newOrg = $organizationsStore->insert([
                        'organization_name' => $orgName,
                        'owner_user_id' => $currentUser['_id'],
                        'created_at' => time(),
                        'updated_at' => time(),
                    ]);

                    if ($newOrg && isset($newOrg['_id'])) {
                        $userOrgStore->insert([
                            'user_id' => $currentUser['_id'],
                            'organization_id' => $newOrg['_id'],
                        ]);

                        // Add creator as organization_owner to organization_members store
                        $orgMembersStore = new \SleekDB\Store('organization_members', $dbPath, ['auto_cache' => false, 'timeout' => false]);
                        $orgMembersStore->insert([
                            'organization_id' => $newOrg['_id'],
                            'user_id' => $currentUser['_id'],
                            'organization_role' => 'organization_owner', // Defined role
                            'added_at' => time(),
                            'status' => 'active'
                        ]);

                        $actionMessage = "Organization '{$orgName}' created successfully!";
                        $actionMessageType = 'success';
                        // Re-fetch user's org link and current organization details
                        $userOrganizationLink = $userOrgStore->findOneBy(['user_id', '=', $currentUser['_id']]);
                        // $organization variable will be re-fetched below based on $userOrganizationLink
                    } else {
                        $actionMessage = "Failed to create organization record.";
                        $actionMessageType = 'danger';
                    }
                } catch (\Exception $e) {
                    $actionMessage = "Error creating organization: " . $e->getMessage();
                    $actionMessageType = 'danger';
                }
            }
        }
    }
}

// --- Handle Organization Creation ---
// ... (existing code for org creation) ...

// --- Handle Adding New User to Organization ---
// ... (existing add user logic) ...

// --- Handle Edit Organization Member Role ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_org_member_role') {
    if (!isset($orgMembersStore) || !isset($usersStore) || !isset($organizationsStore)) {
        $actionMessage = "Database not available for editing member role.";
        $actionMessageType = 'danger';
    } else {
        $organizationId = isset($_POST['organization_id_for_edit']) ? (int)$_POST['organization_id_for_edit'] : null;
        $userToEditId = isset($_POST['user_to_edit_id']) ? (int)$_POST['user_to_edit_id'] : null;
        $newOrganizationRole = $_POST['new_organization_role'] ?? null;
        $currentActorUserId = $currentUser['_id']; // This is likely already an int from SleekDB's user store _id

        // Fetch current actor's role in this specific organization
        $actorMembership = $orgMembersStore->findOneBy([
            ['organization_id', '=', $organizationId],
            'AND',
            ['user_id', '=', $currentActorUserId]
        ]);
        $currentActorOrgRole = $actorMembership ? $actorMembership['organization_role'] : null;

        $memberToEdit = $orgMembersStore->findOneBy([
            ['organization_id', '=', $organizationId],
            'AND',
            ['user_id', '=', $userToEditId]
        ]);

        if (!$organizationId || !$userToEditId || !$newOrganizationRole || !$memberToEdit) {
            $actionMessage = "Missing data for role edit.";
            $actionMessageType = 'danger';
        } elseif ($userToEditId === $currentActorUserId) {
            $actionMessage = "You cannot edit your own role.";
            $actionMessageType = 'warning';
        } elseif ($memberToEdit['organization_role'] === 'organization_owner') {
            $actionMessage = "The organization owner's role cannot be changed through this interface.";
            $actionMessageType = 'warning';
        } elseif (!in_array($newOrganizationRole, ['organization_admin', 'organization_member'])) {
            $actionMessage = "Invalid target role specified.";
            $actionMessageType = 'danger';
        } elseif ($currentActorOrgRole === 'organization_owner') {
            // Owner can change roles of admins and members to admin or member
            if (in_array($memberToEdit['organization_role'], ['organization_admin', 'organization_member'])) {
                $orgMembersStore->updateById($memberToEdit['_id'], ['organization_role' => $newOrganizationRole]);
                $actionMessage = "User role updated successfully.";
                $actionMessageType = 'success';
            } else {
                $actionMessage = "Owners can only modify Admin or Member roles.";
                $actionMessageType = 'warning';
            }
        } elseif ($currentActorOrgRole === 'organization_admin') {
            // Admin can only change role of members, and only to 'organization_member'
            if ($memberToEdit['organization_role'] === 'organization_member' && $newOrganizationRole === 'organization_member') {
                 // Potentially useful if more granular member roles are added later. For now, it's a no-op if already member.
                $orgMembersStore->updateById($memberToEdit['_id'], ['organization_role' => $newOrganizationRole]);
                $actionMessage = "User role updated successfully (or remained the same).";
                $actionMessageType = 'success';
            } elseif ($memberToEdit['organization_role'] !== 'organization_member') {
                $actionMessage = "Admins can only modify roles of Members.";
                $actionMessageType = 'warning';
            } elseif ($newOrganizationRole !== 'organization_member') {
                $actionMessage = "Admins can only assign the Member role.";
                $actionMessageType = 'warning';
            }
        } else {
            $actionMessage = "You do not have permission to edit member roles.";
            $actionMessageType = 'danger';
        }
    }
}

// --- Handle Remove Organization Member ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_org_member') {
    error_log("Attempting to remove member. POST data: " . print_r($_POST, true)); // DEBUG
    if (!isset($orgMembersStore) || !isset($userOrgStore) || !isset($usersStore) || !isset($organizationsStore)) {
        $actionMessage = "Database not available for removing member.";
        $actionMessageType = 'danger';
    } else {
        $organizationId = isset($_POST['organization_id_for_remove']) ? (int)$_POST['organization_id_for_remove'] : null;
        $userToRemoveId = isset($_POST['user_to_remove_id']) ? (int)$_POST['user_to_remove_id'] : null;
        $currentActorUserId = $currentUser['_id']; // This is likely already an int

        $actorMembership = $orgMembersStore->findOneBy([
            ['organization_id', '=', $organizationId],
            'AND',
            ['user_id', '=', $currentActorUserId]
        ]);
        $currentActorOrgRole = $actorMembership ? $actorMembership['organization_role'] : null;

        $memberToRemove = $orgMembersStore->findOneBy([
            ['organization_id', '=', $organizationId],
            'AND',
            ['user_id', '=', $userToRemoveId]
        ]);

        error_log("Remove Member - Org ID: " . $organizationId . ", User to Remove: " . $userToRemoveId . ", Actor Role: " . ($currentActorOrgRole ?? 'N/A')); // DEBUG
        error_log("Remove Member - Member to Remove Query Result: " . print_r($memberToRemove, true)); // DEBUG

        if (!$organizationId || !$userToRemoveId || !$memberToRemove) {
            $actionMessage = "Missing data for member removal or member not found. (OrgID: {$organizationId}, UserID: {$userToRemoveId})";
            $actionMessageType = 'danger';
            error_log("Remove Member - FAILED: Missing data or member not found. OrgID: {$organizationId}, UserID: {$userToRemoveId}, MemberData: " . print_r($memberToRemove, true)); // DEBUG
        } elseif ($userToRemoveId === $currentActorUserId) {
            $actionMessage = "You cannot remove yourself from the organization.";
            $actionMessageType = 'warning';
        } elseif ($memberToRemove['organization_role'] === 'organization_owner') {
            // Basic check to prevent removing the last owner. More robust check would count owners.
            $ownerCount = $orgMembersStore->count([
                ['organization_id', '=', $organizationId],
                'AND',
                ['organization_role', '=', 'organization_owner']
            ]);
            if ($ownerCount <= 1) {
                $actionMessage = "Cannot remove the last organization owner.";
                $actionMessageType = 'warning';
            } elseif ($currentActorOrgRole === 'organization_owner') {
                 // This case should ideally not happen if UI prevents owner from removing another owner easily.
                 // For now, let's assume an owner might be able to remove another (non-last) owner if UI allowed.
                 // $orgMembersStore->deleteById($memberToRemove['_id']); // Placeholder for complex owner removal
                 $actionMessage = "Removing other owners is a sensitive operation (not fully implemented).";
                 $actionMessageType = 'warning';
            } else {
                 $actionMessage = "Only an organization owner can remove another owner.";
                 $actionMessageType = 'danger';
            }
        } elseif ($currentActorOrgRole === 'organization_owner') {
            // Owner can remove admins and members
            if (in_array($memberToRemove['organization_role'], ['organization_admin', 'organization_member'])) {
                $orgMembersStore->deleteById($memberToRemove['_id']);
                // Also remove from user_to_organization_links if their global role is ROLE_ORGANIZATION_USER
                $userGlobalData = $usersStore->findById($userToRemoveId);
                if ($userGlobalData && $userGlobalData['role'] === Auth::ROLE_ORGANIZATION_USER) {
                    $userOrgLink = $userOrgStore->findOneBy([['user_id', '=', $userToRemoveId], ['organization_id', '=', $organizationId]]);
                    if ($userOrgLink) $userOrgStore->deleteById($userOrgLink['_id']);
                }
                $actionMessage = "User removed from organization.";
                $actionMessageType = 'success';
            } else {
                $actionMessage = "Owners can only remove Admins or Members."; // Should not happen if UI is correct
                $actionMessageType = 'warning';
            }
        } elseif ($currentActorOrgRole === 'organization_admin') {
            // Admin can only remove members
            if ($memberToRemove['organization_role'] === 'organization_member') {
                $orgMembersStore->deleteById($memberToRemove['_id']);
                $userGlobalData = $usersStore->findById($userToRemoveId);
                if ($userGlobalData && $userGlobalData['role'] === Auth::ROLE_ORGANIZATION_USER) {
                     $userOrgLink = $userOrgStore->findOneBy([['user_id', '=', $userToRemoveId], ['organization_id', '=', $organizationId]]);
                    if ($userOrgLink) $userOrgStore->deleteById($userOrgLink['_id']);
                }
                $actionMessage = "User removed from organization.";
                $actionMessageType = 'success';
            } else {
                $actionMessage = "Admins can only remove Members.";
                $actionMessageType = 'warning';
            }
        } else {
            $actionMessage = "You do not have permission to remove members.";
            $actionMessageType = 'danger';
        }
    }
}


// --- Handle Adding New User to Organization ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_organization_user_submit'])) {
    if (!isset($userOrgStore) || !isset($organizationsStore) || !isset($orgMembersStore) || !isset($usersStore)) {
        $actionMessage = "Database not available for adding user.";
        $actionMessageType = 'danger';
    } else {
        $orgIdForMember = $_POST['organization_id_for_member'] ?? null;
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $newOrgRole = $_POST['organization_role_for_new_user'] ?? 'organization_member'; // Default to member

        // Fetch current organization details again to ensure context, especially $currentUserOrgRole
        if ($orgIdForMember) {
             $currentOrganizationForAction = $organizationsStore->findById($orgIdForMember);
             if ($currentOrganizationForAction) {
                $memberLinkCheck = $orgMembersStore->findOneBy([
                    ['organization_id', '=', $currentOrganizationForAction['_id']],
                    'AND',
                    ['user_id', '=', $currentUser['_id']]
                ]);
                if ($memberLinkCheck) {
                    $currentUserOrgRoleForAction = $memberLinkCheck['organization_role'];
                     // Permission Check: Only org owner or admin can add users
                    if ($currentUserOrgRoleForAction !== 'organization_owner' && $currentUserOrgRoleForAction !== 'organization_admin') {
                        $actionMessage = "You do not have permission to add users to this organization.";
                        $actionMessageType = 'danger';
                    } elseif (empty($newUsername) || !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $newUsername)) {
                        $actionMessage = "Invalid username. Must be 3-20 letters, numbers, or underscores.";
                        $actionMessageType = 'danger';
                    } elseif (empty($newPassword) || strlen($newPassword) < 8) {
                        $actionMessage = "Password must be at least 8 characters long.";
                        $actionMessageType = 'danger';
                    } elseif (!in_array($newOrgRole, ['organization_member', 'organization_admin'])) { // Add 'organization_owner' if owners can create owners
                        $actionMessage = "Invalid organization role selected.";
                        $actionMessageType = 'danger';
                    } else {
                        // Proceed with creating the user
                        try {
                            // 1. Create global user with ROLE_ORGANIZATION_USER
                            $newUserGlobal = auth()->register($newUsername, $newPassword, ['role' => Auth::ROLE_ORGANIZATION_USER]);

                            if ($newUserGlobal && isset($newUserGlobal['_id'])) {
                                $newlyCreatedUserId = $newUserGlobal['_id'];

                                // 2. Link to user_to_organization_links (primary org link for this restricted user)
                                $userOrgStore->insert([
                                    'user_id' => $newlyCreatedUserId,
                                    'organization_id' => $currentOrganizationForAction['_id']
                                ]);

                                // 3. Add to organization_members
                                $orgMembersStore->insert([
                                    'organization_id' => $currentOrganizationForAction['_id'],
                                    'user_id' => $newlyCreatedUserId,
                                    'organization_role' => $newOrgRole,
                                    'added_at' => time(),
                                    'status' => 'active'
                                ]);
                                $actionMessage = "User '{$newUsername}' created and added to organization as {$newOrgRole}.";
                                $actionMessageType = 'success';
                            } else {
                                $actionMessage = "Failed to create the new user. Username might already exist or another system error occurred.";
                                $actionMessageType = 'danger';
                            }
                        } catch (\Exception $e) {
                            $actionMessage = "Error adding user: " . $e->getMessage();
                            $actionMessageType = 'danger';
                        }
                    }
                } else {
                     $actionMessage = "Error: Your role in this organization could not be determined.";
                     $actionMessageType = 'danger';
                }
             } else {
                $actionMessage = "Error: Target organization not found.";
                $actionMessageType = 'danger';
             }
        } else {
            $actionMessage = "Error: Organization ID missing for adding member.";
            $actionMessageType = 'danger';
        }
    }
    // After processing, ensure $organization and $organizationMembers etc. are re-fetched if not already.
    // The general fetch logic below should handle this.
}


// --- Fetch Organization Details if User is Linked ---
if (isset($userOrgStore) && !$userOrganizationLink) { // Check if not already fetched after creation or other POST actions
    $userOrganizationLink = $userOrgStore->findOneBy(['user_id', '=', $currentUser['_id']]);
}

if ($userOrganizationLink && isset($userOrganizationLink['organization_id']) && isset($organizationsStore)) {
    $organization = $organizationsStore->findById($userOrganizationLink['organization_id']);

    // Fetch forms belonging to this organization
    if ($organization && isset($orgMembersStore) && isset($usersStore)) {
        // Determine current user's role in this organization
        $memberLink = $orgMembersStore->findOneBy([
            ['organization_id', '=', $organization['_id']],
            'AND',
            ['user_id', '=', $currentUser['_id']]
        ]);
        if ($memberLink) {
            $currentUserOrgRole = $memberLink['organization_role'];
        }

        // Fetch all members of this organization for display
        $rawMembers = $orgMembersStore->findBy(['organization_id', '=', $organization['_id']]);
        foreach ($rawMembers as $rawMember) {
            $memberUser = $usersStore->findById($rawMember['user_id']);
            if ($memberUser) {
                $organizationMembers[] = [
                    'user_id' => $rawMember['user_id'],
                    'username' => $memberUser['username'] ?? 'Unknown User',
                    'organization_role' => $rawMember['organization_role'],
                    'added_at' => $rawMember['added_at'] ?? null,
                ];
            }
        }
        usort($organizationMembers, function($a, $b) {
            return strcasecmp($a['username'], $b['username']);
        });


        $formsBasePath = STORAGE_DIR . '/forms/';
        if (is_dir($formsBasePath)) {
            $allFormFiles = scandir($formsBasePath);
            foreach ($allFormFiles as $formFile) {
                if (strpos($formFile, '_schema.json') !== false) {
                    $formId = str_replace('_schema.json', '', $formFile);
                    $filePath = $formsBasePath . $formFile;
                    if (is_readable($filePath)) {
                        $formDataJson = file_get_contents($filePath);
                        $formData = json_decode($formDataJson, true);

                        if (isset($formData['organization_id']) && $formData['organization_id'] === $organization['_id']) {
                            $organizationForms[] = [
                                'form_id' => $formId,
                                'form_name' => $formData['formName'] ?? 'Unnamed Form',
                                'created' => $formData['created'] ?? null,
                                'updated' => $formData['updated'] ?? null,
                            ];
                        }
                    }
                }
            }
            // Sort forms by name or date if needed
            usort($organizationForms, function($a, $b) {
                return strcasecmp($a['form_name'], $b['form_name']);
            });
        }
    }
}


// Define the page content
ob_start();
?>

<div class="container-admin mt-4">
    <div class="page-header">
        <h1>Organization Management</h1>
        <div>
            <?php if (auth()->getUser()['role'] !== Auth::ROLE_ORGANIZATION_USER): ?>
                <a href="<?php echo site_url('profile'); ?>" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Profile
                </a>
            <?php endif; ?>
            <a href="<?php echo site_url('account'); ?>" class="btn btn-outline-info me-2">
                <i class="bi bi-gear"></i> Account Settings
            </a>
            <a href="<?php echo site_url('logout'); ?>" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <?php if ($actionMessage): ?>
        <div class="alert alert-<?php echo $actionMessageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($actionMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($organization): ?>
        <!-- User is in an Organization -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Your Organization: <?php echo htmlspecialchars($organization['organization_name']); ?></h4>
            </div>
            <div class="card-body">
                <p><strong>Organization ID:</strong> <?php echo htmlspecialchars($organization['_id']); ?></p>
                <p><strong>Owner ID:</strong> <?php echo htmlspecialchars($organization['owner_user_id']); ?> (<?php echo $organization['owner_user_id'] === $currentUser['_id'] ? 'You' : 'Another User'; ?>)</p>
                <p><strong>Created:</strong> <?php echo date('Y-m-d H:i:s', $organization['created_at']); ?></p>
                <hr>
                <a href="<?php echo site_url('builder?form_context=organization'); ?>" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Create New Form for Organization
                </a>
            </div>
        </div>

        <?php
        // Only show Member Management if the current user is an owner or admin of this organization
        $canManageMembers = ($currentUserOrgRole === 'organization_owner' || $currentUserOrgRole === 'organization_admin');
        if ($canManageMembers):
        ?>
        <div class="row mt-4">
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Organization Members (<?php echo count($organizationMembers); ?>)</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($organizationMembers)): ?>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Organization Role</th>
                                            <th>Added At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($organizationMembers as $member): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($member['username']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $member['organization_role']))); ?></span>
                                                </td>
                                                <td><?php echo $member['added_at'] ? date('Y-m-d H:i', $member['added_at']) : 'N/A'; ?></td>
                                                <td class="member-actions">
                                                    <?php 
                                                    $canEditTarget = false;
                                                    $canRemoveTarget = false;

                                                    if ($currentUser['_id'] !== $member['user_id']) { // Cannot act on self
                                                        if ($currentUserOrgRole === 'organization_owner') {
                                                            if ($member['organization_role'] !== 'organization_owner') { // Owner cannot edit/remove another owner via this simple interface
                                                                $canEditTarget = true;
                                                                $canRemoveTarget = true;
                                                            }
                                                        } elseif ($currentUserOrgRole === 'organization_admin') {
                                                            if ($member['organization_role'] === 'organization_member') { // Admin can only manage members
                                                                $canEditTarget = true;
                                                                $canRemoveTarget = true;
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <?php if ($canEditTarget): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary edit-member-role-btn" 
                                                                data-bs-toggle="modal" data-bs-target="#editRoleModal"
                                                                data-user-id="<?php echo htmlspecialchars($member['user_id']); ?>"
                                                                data-username="<?php echo htmlspecialchars($member['username']); ?>"
                                                                data-current-role="<?php echo htmlspecialchars($member['organization_role']); ?>">
                                                            <i class="bi bi-pencil-square"></i> Edit Role
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($canRemoveTarget): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-member-btn"
                                                                data-bs-toggle="modal" data-bs-target="#removeMemberModal"
                                                                data-user-id="<?php echo htmlspecialchars($member['user_id']); ?>"
                                                                data-username="<?php echo htmlspecialchars($member['username']); ?>">
                                                            <i class="bi bi-trash"></i> Remove
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (!$canEditTarget && !$canRemoveTarget && $currentUser['_id'] === $member['user_id']): ?>
                                                        <span class="text-muted fst-italic">You</span>
                                                    <?php elseif(!$canEditTarget && !$canRemoveTarget): ?>
                                                         <span class="text-muted fst-italic">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No members found for this organization yet (besides the owner).</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Add New Organization User</h4>
                    </div>
                    <div class="card-body">
                        <p><small>This will create a new system user with the global role 'Organization User' and add them to this organization with the specified organization role.</small></p>
                        <form method="POST" action="<?php echo site_url('organization/management'); ?>">
                            <input type="hidden" name="action" value="add_organization_user">
                            <input type="hidden" name="organization_id_for_member" value="<?php echo htmlspecialchars($organization['_id']); ?>">
                            <div class="mb-3">
                                <label for="new_username" class="form-label">New User's Username</label>
                                <input type="text" class="form-control" id="new_username" name="new_username" required pattern="[a-zA-Z0-9_]{3,20}">
                                <div class="form-text">3-20 chars, letters, numbers, underscore.</div>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New User's Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                <div class="form-text">Min 8 characters. User should change this on first login.</div>
                            </div>
                            <div class="mb-3">
                                <label for="organization_role_for_new_user" class="form-label">Assign Organization Role</label>
                                <select class="form-select" id="organization_role_for_new_user" name="organization_role_for_new_user">
                                    <option value="organization_member" selected>Member</option>
                                    <?php if ($currentUserOrgRole === 'organization_owner'): ?>
                                        <option value="organization_admin">Admin</option>
                                    <?php endif; ?>
                                    <?php // Owners typically don't create other owners directly via this simple form ?>
                                </select>
                            </div>
                            <button type="submit" name="add_new_organization_user_submit" class="btn btn-primary">
                                <i class="bi bi-person-plus-fill"></i> Add User to Organization
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; // End $canManageMembers check ?>


        <div class="card mt-4">
            <div class="card-header">
                <h4 class="mb-0">Organization Forms (<?php echo count($organizationForms); ?>)</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($organizationForms)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Form Name</th>
                                    <th>Form ID</th>
                                    <th>Created</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($organizationForms as $form): ?>
                                    <tr>
                                        <td class="truncate"><?php echo htmlspecialchars($form['form_name']); ?></td>
                                        <td class="truncate"><?php echo htmlspecialchars($form['form_id']); ?></td>
                                        <td><?php echo $form['created'] ? date('Y-m-d H:i:s', $form['created']) : 'N/A'; ?></td>
                                        <td><?php echo $form['updated'] ? date('Y-m-d H:i:s', $form['updated']) : 'N/A'; ?></td>
                                        <td class="form-actions">
                                            <a href="<?php echo site_url('form?f=') . htmlspecialchars($form['form_id']); ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="View Form">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?php echo site_url('edit?f=') . htmlspecialchars($form['form_id']); ?>" class="btn btn-sm btn-outline-secondary" title="Edit Form">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <!-- Add other actions like share, delete if applicable to org forms -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="bi bi-journal-x" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="mt-3">This organization does not have any forms yet.</p>
                        <a href="<?php echo site_url('builder?form_context=organization'); ?>" class="btn btn-primary">
                            Create the first form for this organization
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- User is NOT in an Organization -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Create Your Organization</h4>
            </div>
            <div class="card-body">
                <p>You are not currently part of an organization. Create one to manage shared forms and resources.</p>
                <form method="POST" action="<?php echo site_url('organization/management'); ?>">
                    <div class="mb-3">
                        <label for="organization_name" class="form-label">Organization Name</label>
                        <input type="text" class="form-control" id="organization_name" name="organization_name" required>
                    </div>
                    <button type="submit" name="create_organization" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Organization
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();
$GLOBALS['page_title'] = 'Organization Management';
// Add any page-specific CSS or JS if needed
// $GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/organization.css') .'?v=' . APP_VERSION . '">';
?>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo site_url('organization/management'); ?>">
                <input type="hidden" name="action" value="edit_org_member_role">
                <input type="hidden" name="organization_id_for_edit" value="<?php echo htmlspecialchars($organization['_id'] ?? ''); ?>">
                <input type="hidden" name="user_to_edit_id" id="editRole_userId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRoleModalLabel">Edit Role for <span id="editRole_username"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editRole_newRole" class="form-label">New Organization Role</label>
                        <select class="form-select" id="editRole_newRole" name="new_organization_role">
                            <?php // Options will be populated by JavaScript based on actor's role ?>
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

<!-- Remove Member Modal -->
<div class="modal fade" id="removeMemberModal" tabindex="-1" aria-labelledby="removeMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo site_url('organization/management'); ?>">
                <input type="hidden" name="action" value="remove_org_member">
                <input type="hidden" name="organization_id_for_remove" value="<?php echo htmlspecialchars($organization['_id'] ?? ''); ?>">
                <input type="hidden" name="user_to_remove_id" id="removeMember_userId">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeMemberModalLabel">Confirm Removal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove user <strong id="removeMember_username"></strong> from this organization?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Remove User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentUserOrgRole = <?php echo json_encode($currentUserOrgRole); ?>;

    // Edit Role Modal
    const editRoleModal = document.getElementById('editRoleModal');
    if (editRoleModal) {
        editRoleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const currentRole = button.getAttribute('data-current-role');

            document.getElementById('editRole_userId').value = userId;
            document.getElementById('editRole_username').textContent = username;
            
            const roleSelect = document.getElementById('editRole_newRole');
            roleSelect.innerHTML = ''; // Clear existing options

            // Populate roles based on current user's org role
            if (currentUserOrgRole === 'organization_owner') {
                if (currentRole === 'organization_admin' || currentRole === 'organization_member') {
                    roleSelect.options.add(new Option('Admin', 'organization_admin', false, currentRole === 'organization_admin'));
                    roleSelect.options.add(new Option('Member', 'organization_member', false, currentRole === 'organization_member'));
                } else {
                     roleSelect.options.add(new Option(currentRole.replace('_', ' '), currentRole, true, true)); // Should not happen for owner
                }
            } else if (currentUserOrgRole === 'organization_admin') {
                if (currentRole === 'organization_member') { // Admin can only manage members
                    roleSelect.options.add(new Option('Member', 'organization_member', false, currentRole === 'organization_member'));
                } else {
                     roleSelect.options.add(new Option(currentRole.replace('_', ' '), currentRole, true, true)); // Should not happen for admin editing non-member
                }
            }
            // If no options added (e.g. admin trying to edit admin), disable select or show message - current logic prevents button display
        });
    }

    // Remove Member Modal
    const removeMemberModal = document.getElementById('removeMemberModal');
    if (removeMemberModal) {
        removeMemberModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');

            document.getElementById('removeMember_userId').value = userId;
            document.getElementById('removeMember_username').textContent = username;
        });
    }
});
</script>

<?php
require_once ROOT_DIR . '/includes/master.php';
?>
