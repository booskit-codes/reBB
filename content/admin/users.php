<?php
/**
 * reBB - User Management Page
 * 
 * This file provides an interface for admins to manage user accounts.
 * This page requires authentication with admin role.
 */

// Require admin authentication before processing anything else
auth()->requireRole('admin', 'login');

// Since we've passed the auth check, we can safely get the current user
$currentUser = auth()->getUser();

// Initialize variables
$actionMessage = '';
$actionMessageType = 'info';
$users = [];
$editingUser = null;

// Set default max links if not defined
if (!defined('DEFAULT_MAX_UNIQUE_LINKS')) {
    define('DEFAULT_MAX_UNIQUE_LINKS', 5);
}

// Handle user creation
if (isset($_POST['create_user']) && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = isset($_POST['role']) ? $_POST['role'] : 'user';
    $maxLinks = isset($_POST['max_links']) ? (int)$_POST['max_links'] : DEFAULT_MAX_UNIQUE_LINKS;
    
    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $actionMessage = "Invalid username format. Use 3-20 characters (letters, numbers, underscore).";
        $actionMessageType = 'danger';
    }
    // Validate password length
    elseif (strlen($password) < 8) {
        $actionMessage = "Password must be at least 8 characters long.";
        $actionMessageType = 'danger';
    }
    // Validate max links value
    elseif ($maxLinks < 0) {
        $actionMessage = "Maximum links value cannot be negative.";
        $actionMessageType = 'danger';
    }
    else {
        // Try to create the user
        $result = auth()->register($username, $password, [
            'role' => $role,
            'max_unique_links' => $maxLinks
        ]);
        
        if ($result) {
            $actionMessage = "User '$username' created successfully.";
            $actionMessageType = 'success';
            logAdminAction("Created user: $username with role: $role and max links: $maxLinks");
        } else {
            $actionMessage = "Failed to create user. Username may already exist.";
            $actionMessageType = 'danger';
            logAdminAction("Failed to create user: $username", false);
        }
    }
}

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = $_POST['user_id'];
    
    // Don't allow deleting your own account
    if ($userId == $currentUser['_id']) {
        $actionMessage = "You cannot delete your own account.";
        $actionMessageType = 'danger';
    } else {
        // Access the database stores directly
        $dbPath = ROOT_DIR . '/db';
        $userStore = new \SleekDB\Store('users', $dbPath, [
            'auto_cache' => false,
            'timeout' => false
        ]);
        
        // Try to delete the user
        try {
            $deletedUser = $userStore->findById($userId);
            if ($deletedUser) {
                $username = $deletedUser['username'];
                $userStore->deleteById($userId);
                
                $actionMessage = "User '$username' deleted successfully.";
                $actionMessageType = 'success';
                logAdminAction("Deleted user: $username");
            } else {
                $actionMessage = "User not found.";
                $actionMessageType = 'danger';
            }
        } catch (\Exception $e) {
            $actionMessage = "Error deleting user: " . $e->getMessage();
            $actionMessageType = 'danger';
            logAdminAction("Error deleting user: " . $e->getMessage(), false);
        }
    }
}

// Handle user editing
if (isset($_POST['edit_user']) && isset($_POST['user_id'])) {
    $userId = $_POST['user_id'];
    $newRole = isset($_POST['role']) ? $_POST['role'] : 'user';
    $newPassword = isset($_POST['password']) && !empty($_POST['password']) ? $_POST['password'] : null;
    $maxLinks = isset($_POST['max_links']) ? (int)$_POST['max_links'] : DEFAULT_MAX_UNIQUE_LINKS;
    
    // Don't allow changing your own role
    if ($userId == $currentUser['_id'] && $newRole !== $currentUser['role']) {
        $actionMessage = "You cannot change your own role.";
        $actionMessageType = 'danger';
    } 
    // Validate max links value
    elseif ($maxLinks < 0) {
        $actionMessage = "Maximum links value cannot be negative.";
        $actionMessageType = 'danger';
    }
    else {
        // Access the database stores directly
        $dbPath = ROOT_DIR . '/db';
        $userStore = new \SleekDB\Store('users', $dbPath, [
            'auto_cache' => false,
            'timeout' => false
        ]);
        
        // Try to update the user
        try {
            $userData = [
                'role' => $newRole,
                'max_unique_links' => $maxLinks,
                'updated_at' => time()
            ];
            
            if ($newPassword !== null) {
                // Validate password length
                if (strlen($newPassword) < 8) {
                    $actionMessage = "Password must be at least 8 characters long.";
                    $actionMessageType = 'danger';
                } else {
                    $userData['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
                }
            }
            
            // Only proceed with update if we don't have a validation error
            if ($actionMessageType !== 'danger') {
                $updatedUser = $userStore->updateById($userId, $userData);
                if ($updatedUser) {
                    $username = $updatedUser['username'];
                    $actionMessage = "User '$username' updated successfully.";
                    $actionMessageType = 'success';
                    logAdminAction("Updated user: $username (max_links: $maxLinks)");
                } else {
                    $actionMessage = "User not found.";
                    $actionMessageType = 'danger';
                }
            }
        } catch (\Exception $e) {
            $actionMessage = "Error updating user: " . $e->getMessage();
            $actionMessageType = 'danger';
            logAdminAction("Error updating user: " . $e->getMessage(), false);
        }
    }
}

// Fetch user to edit
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $userId = $_GET['edit'];
    
    // Access the database stores directly
    $dbPath = ROOT_DIR . '/db';
    $userStore = new \SleekDB\Store('users', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    try {
        $editingUser = $userStore->findById($userId);
        if (!$editingUser) {
            $actionMessage = "User not found.";
            $actionMessageType = 'danger';
        }
    } catch (\Exception $e) {
        $actionMessage = "Error fetching user: " . $e->getMessage();
        $actionMessageType = 'danger';
    }
}

// Get all users
$dbPath = ROOT_DIR . '/db';
$userStore = new \SleekDB\Store('users', $dbPath, [
    'auto_cache' => false,
    'timeout' => false
]);

try {
    $allUsers = $userStore->findAll();
    
    // Process users for display, removing sensitive data
    foreach ($allUsers as $user) {
        $userData = $user;
        unset($userData['password_hash']); // Remove sensitive data
        
        // Format timestamps for readability
        if (isset($userData['created_at'])) {
            $userData['created_at_formatted'] = date('Y-m-d H:i:s', $userData['created_at']);
        }
        if (isset($userData['updated_at'])) {
            $userData['updated_at_formatted'] = date('Y-m-d H:i:s', $userData['updated_at']);
        }
        
        // Get custom links count
        try {
            $linkStore = new \SleekDB\Store('custom_links', $dbPath, [
                'auto_cache' => false,
                'timeout' => false
            ]);

            $linkStore = $linkStore->findBy([
                ['user_id', '=', $user['_id']]
            ]);
            $userData['custom_links_count'] = count($linkStore);
        } catch (\Exception $e) {
            $userData['custom_links_count'] = 0;
        }
        
        $users[] = $userData;
    }
} catch (\Exception $e) {
    $actionMessage = "Error fetching users: " . $e->getMessage();
    $actionMessageType = 'danger';
    logAdminAction("Error fetching users: " . $e->getMessage(), false);
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<!-- Admin dashboard - Using the special admin container class -->
<div class="container-admin">
    <div class="page-header">
        <h1>User Management</h1>
        <div>
            <span class="text-muted me-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="<?php echo site_url('admin'); ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Admin
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
    
    <div class="row">
        <!-- Left column - User list -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Manage Users</h4>
                </div>
                <div class="card-body">
                    <!-- Search box -->
                    <div class="search-box">
                        <input type="text" id="userSearch" class="form-control" placeholder="Search users by username...">
                    </div>
                    
                    <?php if (empty($users)): ?>
                        <div class="form-list-empty">
                            <p><i class="bi bi-people"></i></p>
                            <p>No users found. Create your first user.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Max Links</th>
                                        <th>Links Used</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="usersList">
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="truncate"><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td>
                                                <?php
                                                    $role = $user['role'];
                                                    $badgeClass = 'secondary'; // Default badge
                                                    if ($role === Auth::ROLE_ADMIN) {
                                                        $badgeClass = 'danger';
                                                    } elseif ($role === Auth::ROLE_EDITOR) {
                                                        $badgeClass = 'warning';
                                                    } elseif ($role === Auth::ROLE_USER) {
                                                        $badgeClass = 'success';
                                                    } elseif ($role === Auth::ROLE_ORGANIZATION_USER) {
                                                        $badgeClass = 'info';
                                                    } elseif ($role === 'trusted') { // Assuming 'trusted' is a role
                                                        $badgeClass = 'primary';
                                                    }
                                                ?>
                                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role))); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                    echo isset($user['max_unique_links']) ? 
                                                        $user['max_unique_links'] : DEFAULT_MAX_UNIQUE_LINKS; 
                                                ?>
                                            </td>
                                            <td><?php echo $user['custom_links_count']; ?></td>
                                            <td><?php echo $user['created_at_formatted'] ?? 'N/A'; ?></td>
                                            <td class="form-actions">
                                                <a href="?edit=<?php echo $user['_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                
                                                <?php if ($user['_id'] !== $currentUser['_id']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal" 
                                                            data-userid="<?php echo $user['_id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                        <i class="bi bi-person-check"></i> Current User
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right column - User form -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><?php echo $editingUser ? 'Edit User' : 'Create User'; ?></h4>
                </div>
                <div class="card-body">
                    <?php if ($editingUser): ?>
                        <!-- Edit User Form -->
                        <form method="post" action="<?php echo site_url('admin/users'); ?>">
                            <input type="hidden" name="user_id" value="<?php echo $editingUser['_id']; ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($editingUser['username']); ?>" disabled>
                                <div class="form-text">Username cannot be changed.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <div class="form-text">Leave blank to keep current password.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" <?php echo $editingUser['_id'] === $currentUser['_id'] ? 'disabled' : ''; ?>>
                                    <option value="<?php echo Auth::ROLE_ORGANIZATION_USER; ?>" <?php echo $editingUser['role'] === Auth::ROLE_ORGANIZATION_USER ? 'selected' : ''; ?>>Organization User</option>
                                    <option value="<?php echo Auth::ROLE_USER; ?>" <?php echo $editingUser['role'] === Auth::ROLE_USER ? 'selected' : ''; ?>>User</option>
                                    <option value="trusted" <?php echo $editingUser['role'] === 'trusted' ? 'selected' : ''; ?>>Trusted</option> <!-- Assuming 'trusted' is a defined role -->
                                    <option value="<?php echo Auth::ROLE_EDITOR; ?>" <?php echo $editingUser['role'] === Auth::ROLE_EDITOR ? 'selected' : ''; ?>>Editor</option>
                                    <option value="<?php echo Auth::ROLE_ADMIN; ?>" <?php echo $editingUser['role'] === Auth::ROLE_ADMIN ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <?php if ($editingUser['_id'] === $currentUser['_id']): ?>
                                    <div class="form-text">You cannot change your own role.</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="max_links" class="form-label">Maximum Custom Links</label>
                                <input type="number" class="form-control" id="max_links" name="max_links" min="0" value="<?php echo isset($editingUser['max_unique_links']) ? $editingUser['max_unique_links'] : DEFAULT_MAX_UNIQUE_LINKS; ?>">
                                <div class="form-text">Maximum number of custom links this user can create. Default: <?php echo DEFAULT_MAX_UNIQUE_LINKS; ?></div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="<?php echo site_url('admin/users'); ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="edit_user" class="btn btn-primary">Update User</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Create User Form -->
                        <form method="post" action="<?php echo site_url('admin/users'); ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required pattern="[a-zA-Z0-9_]{3,20}">
                                <div class="form-text">3-20 characters, letters, numbers, and underscores only.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="<?php echo Auth::ROLE_ORGANIZATION_USER; ?>">Organization User</option>
                                    <option value="<?php echo Auth::ROLE_USER; ?>" selected>User (Default)</option>
                                    <option value="trusted">Trusted</option> <!-- Assuming 'trusted' is a defined role -->
                                    <option value="<?php echo Auth::ROLE_EDITOR; ?>">Editor</option>
                                    <option value="<?php echo Auth::ROLE_ADMIN; ?>">Admin</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="max_links" class="form-label">Maximum Custom Links</label>
                                <input type="number" class="form-control" id="max_links" name="max_links" min="0" value="<?php echo DEFAULT_MAX_UNIQUE_LINKS; ?>">
                                <div class="form-text">Maximum number of custom links this user can create. Default: <?php echo DEFAULT_MAX_UNIQUE_LINKS; ?></div>
                            </div>
                            
                            <button type="submit" name="create_user" class="btn btn-success w-100">Create User</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$editingUser): ?>
                <!-- User Creation Instructions -->
                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> User Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Admin users</strong> can:</p>
                        <ul class="mb-3">
                            <li>Access the admin panel</li>
                            <li>Manage all forms</li>
                            <li>Create and manage users</li>
                            <li>View system analytics</li>
                        </ul>

                        <p><strong>Editor users</strong> can:</p>
                        <ul class="mb-3">
                            <li>Manage the documentation</li>
                            <li>Create new documentation entries</li>
                            <li>Modify existing documentation entries</li>
                            <li>Delete documentation entries</li>
                        </ul>

                        <p><strong>Trusted users</strong> can:</p>
                        <ul class="mb-3">
                            <li>Create forms that get automatically verified</li>
                        </ul>
                        
                        <p><strong>Regular users</strong> can:</p>
                        <ul class="mb-3">
                            <li>Create and manage their own forms</li>
                            <li>Use the form builder</li>
                            <li>Create custom short links (limited by Max Links setting)</li>
                            <li>Create and manage one organization</li>
                        </ul>

                        <p><strong>Organization users</strong> can:</p>
                        <ul>
                            <li>Only access forms and features related to their organization.</li>
                            <li>Cannot create personal forms or access general user profile sections.</li>
                            <li>(Future: Will be created/managed by Organization Owners/Admins).</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the user "<span id="usernameToDelete"></span>"?
                    <p class="text-danger mt-2">This action cannot be undone. All forms created by this user will remain.</p>
                </div>
                <div class="modal-footer">
                    <form method="post" action="<?php echo site_url('admin/users'); ?>">
                        <input type="hidden" name="user_id" id="userIdToDelete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom JavaScript for this page -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // User search functionality
        const searchBox = document.getElementById('userSearch');
        if (searchBox) {
            searchBox.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#usersList tr');
                
                rows.forEach(row => {
                    const username = row.querySelector('td:first-child').textContent.toLowerCase();
                    const role = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    
                    if (username.includes(searchTerm) || role.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Delete modal handler
        $('#deleteModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const userId = button.data('userid');
            const username = button.data('username');
            
            const modal = $(this);
            modal.find('#usernameToDelete').text(username);
            modal.find('#userIdToDelete').val(userId);
        });
    });
</script>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'User Management';

// Add page-specific CSS (using the admin styles)
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';

/**
 * Log admin actions - reusing from admin.php
 * @param string $action The action to log
 * @param bool $success Whether the action was successful
 */
function logAdminAction($action, $success = true) {
    $logFile = STORAGE_DIR . '/logs/admin_activity.log';
    $logsDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Get current authenticated user
    $user = auth()->getUser();
    $username = $user ? $user['username'] : 'Unknown';
    
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = "[$timestamp] [$status] [IP: $ip] [User: $username] $action" . PHP_EOL;
    
    // Try to write to log file, silently fail if unable
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>