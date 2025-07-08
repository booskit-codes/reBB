<?php
/**
 * reBB - User Profile/Dashboard
 * 
 * This file serves as the user dashboard for viewing and managing personal forms.
 * Users must be logged in to access this page.
 */

// Require authentication before processing anything else
auth()->requireAuth('login');

// Since we've passed the auth check, we can safely get the current user
$currentUser = auth()->getUser();

// Initialize variables
$actionMessage = '';
$actionMessageType = 'info';
$userForms = [];

// Handle unlinking a form from user
if (isset($_POST['unlink_form']) && isset($_POST['form_id'])) {
    $formId = $_POST['form_id'];
    
    try {
        // Initialize the SleekDB store for user forms
        $dbPath = ROOT_DIR . '/db';
        $userFormsStore = new \SleekDB\Store('user_forms', $dbPath, [
            'auto_cache' => false,
            'timeout' => false
        ]);
        
        // Find the user-form relationship
        $userForm = $userFormsStore->findOneBy([
            ['user_id', '=', $currentUser['_id']],
            'AND',
            ['form_id', '=', $formId]
        ]);
        
        if ($userForm) {
            // Delete the relationship entry
            $userFormsStore->deleteById($userForm['_id']);
            
            $actionMessage = "Form has been unlinked from your account.";
            $actionMessageType = 'success';
        } else {
            $actionMessage = "Form not found or doesn't belong to you.";
            $actionMessageType = 'danger';
        }
    } catch (\Exception $e) {
        $actionMessage = "Error unlinking form: " . $e->getMessage();
        $actionMessageType = 'danger';
    }
}

// Access the forms database
try {
    // Initialize the SleekDB store for user forms
    $dbPath = ROOT_DIR . '/db';
    $userFormsStore = new \SleekDB\Store('user_forms', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Fetch all forms for this user
    $userForms = $userFormsStore->findBy([
        ['user_id', '=', $currentUser['_id']]
    ]);
    
    // Sort by last updated (newest first)
    usort($userForms, function($a, $b) {
        return $b['last_updated'] - $a['last_updated'];
    });
    
    // Fetch additional data for each form from the schema files
    foreach ($userForms as $key => $form) {
        $formId = $form['form_id'];
        $schemaPath = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
        
        if (file_exists($schemaPath)) {
            $schemaData = json_decode(file_get_contents($schemaPath), true);
            
            // Add additional data from schema file
            $userForms[$key]['style'] = $schemaData['formStyle'] ?? 'default';
            $userForms[$key]['created'] = $schemaData['created'] ?? $form['created_at'];
            $userForms[$key]['updated'] = $schemaData['updated'] ?? $form['last_updated'];
            
            // Check file size
            $userForms[$key]['size'] = filesize($schemaPath);
        } else {
            // Mark as potentially missing file
            $userForms[$key]['missing_file'] = true;
        }
    }
} catch (\Exception $e) {
    $actionMessage = "Error fetching your forms: " . $e->getMessage();
    $actionMessageType = 'danger';
}

// Get form usage statistics if analytics is enabled
$formStats = [];
if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS && defined('TRACK_FORM_USAGE') && TRACK_FORM_USAGE) {
    try {
        $analytics = new Analytics();
        if ($analytics->isEnabled()) {
            // Get popular forms data from analytics
            $allForms = $analytics->getPopularForms(100); // Get a large number to ensure we get all forms
            
            // Extract stats for the user's forms
            foreach ($userForms as $key => $form) {
                $formId = $form['form_id'];
                if (isset($allForms[$formId])) {
                    $userForms[$key]['views'] = $allForms[$formId]['views'];
                    $userForms[$key]['submissions'] = $allForms[$formId]['submissions'];
                    
                    // Calculate conversion rate
                    if ($allForms[$formId]['views'] > 0) {
                        $userForms[$key]['conversion'] = round(($allForms[$formId]['submissions'] / $allForms[$formId]['views']) * 100, 1);
                    } else {
                        $userForms[$key]['conversion'] = 0;
                    }
                } else {
                    $userForms[$key]['views'] = 0;
                    $userForms[$key]['submissions'] = 0;
                    $userForms[$key]['conversion'] = 0;
                }
            }
        }
    } catch (\Exception $e) {
        // Silently handle analytics errors - analytics are optional
    }
}

// Get user's custom links limit
$userStore = new \SleekDB\Store('users', $dbPath, [
    'auto_cache' => false,
    'timeout' => false
]);
$userData = $userStore->findById($currentUser['_id']);
$maxLinks = isset($userData['max_unique_links']) ? $userData['max_unique_links'] : DEFAULT_MAX_UNIQUE_LINKS;

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container-admin">
    <div class="page-header">
        <h1>My Forms Dashboard</h1>
        <div>
            <span class="text-muted me-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="<?php echo site_url('builder'); ?>" class="btn btn-success me-2">
                <i class="bi bi-plus-circle"></i> Create New Form
            </a>
            <a href="<?php echo site_url(); ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-house"></i> Home
            </a>
            <a href="<?php echo site_url('lists'); ?>" class="btn btn-outline-primary me-2">
                <i class="bi bi-collection"></i> Form Lists
            </a>
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
    
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="stat-value"><?php echo count($userForms); ?></div>
                    <div class="stat-label">My Forms</div>
                </div>
            </div>
        </div>
        <?php if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS): ?>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value">
                            <?php 
                                $totalViews = 0;
                                foreach ($userForms as $form) {
                                    $totalViews += isset($form['views']) ? $form['views'] : 0;
                                }
                                echo $totalViews;
                            ?>
                        </div>
                        <div class="stat-label">Total Form Views</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value">
                            <?php 
                                $totalSubmissions = 0;
                                foreach ($userForms as $form) {
                                    $totalSubmissions += isset($form['submissions']) ? $form['submissions'] : 0;
                                }
                                echo $totalSubmissions;
                            ?>
                        </div>
                        <div class="stat-label">Total Form Submissions</div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-md-8 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value">
                            <?php 
                                $recentForms = 0;
                                $oneDayAgo = time() - (24 * 60 * 60);
                                foreach ($userForms as $form) {
                                    if (isset($form['created']) && $form['created'] > $oneDayAgo) {
                                        $recentForms++;
                                    }
                                }
                                echo $recentForms;
                            ?>
                        </div>
                        <div class="stat-label">Forms Created (Last 24h)</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="forms-tab" data-bs-toggle="tab" data-bs-target="#forms-content" type="button" role="tab" aria-controls="forms-content" aria-selected="true">
                <i class="bi bi-clipboard-check"></i> My Forms
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="links-tab" data-bs-toggle="tab" data-bs-target="#links-content" type="button" role="tab" aria-controls="links-content" aria-selected="false">
                <i class="bi bi-link-45deg"></i> Custom Links
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="organization-tab" data-bs-toggle="tab" data-bs-target="#organization-content" type="button" role="tab" aria-controls="organization-content" aria-selected="false">
                <i class="bi bi-diagram-3"></i> Organization Management
            </button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="profileTabsContent">
        <!-- Forms Tab -->
        <div class="tab-pane fade show active" id="forms-content" role="tabpanel" aria-labelledby="forms-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">My Forms</h4>
                    <a href="<?php echo site_url('builder'); ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle"></i> Create New Form
                    </a>
                </div>
                <div class="card-body">
                    <!-- Search box -->
                    <div class="search-box">
                        <input type="text" id="formSearch" class="form-control" placeholder="Search my forms...">
                    </div>
                    
                    <?php if (empty($userForms)): ?>
                        <div class="form-list-empty">
                            <p><i class="bi bi-clipboard-check"></i></p>
                            <p>You haven't created any forms yet.</p>
                            <a href="<?php echo site_url('builder'); ?>" class="btn btn-primary">Create your first form</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Form Name</th>
                                        <th>Form ID</th>
                                        <th>Created</th>
                                        <th>Last Updated</th>
                                        <?php if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS): ?>
                                            <th>Views</th>
                                            <th>Submissions</th>
                                            <th>Conversion</th>
                                        <?php endif; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="formsList">
                                    <?php foreach ($userForms as $form): ?>
                                        <?php 
                                            // Skip forms with missing files
                                            if (isset($form['missing_file']) && $form['missing_file'] === true) {
                                                continue;
                                            }
                                        ?>
                                        <tr>
                                            <td class="truncate"><?php echo htmlspecialchars($form['form_name'] ?: 'Unnamed Form'); ?></td>
                                            <td class="truncate"><?php echo htmlspecialchars($form['form_id']); ?></td>
                                            <td><?php echo isset($form['created']) ? date('Y-m-d H:i:s', $form['created']) : 'Unknown'; ?></td>
                                            <td><?php echo isset($form['last_updated']) ? date('Y-m-d H:i:s', $form['last_updated']) : 'Unknown'; ?></td>
                                            <?php if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS): ?>
                                                <td><?php echo isset($form['views']) ? number_format($form['views']) : '0'; ?></td>
                                                <td><?php echo isset($form['submissions']) ? number_format($form['submissions']) : '0'; ?></td>
                                                <td>
                                                    <?php if (isset($form['conversion'])): ?>
                                                        <span class="<?php echo $form['conversion'] > 50 ? 'conversion-high' : ($form['conversion'] > 20 ? 'conversion-medium' : 'conversion-low'); ?>">
                                                            <?php echo $form['conversion']; ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        0%
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="form-actions">
                                                <a href="<?php echo site_url('form?f=') . htmlspecialchars($form['form_id']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="<?php echo site_url('edit?f=') . htmlspecialchars($form['form_id']); ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#shareModal" 
                                                        data-formid="<?php echo htmlspecialchars($form['form_id']); ?>"
                                                        data-formname="<?php echo htmlspecialchars($form['form_name'] ?: 'Unnamed Form'); ?>">
                                                    <i class="bi bi-share"></i> Share
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-success create-custom-link" 
                                                        data-formid="<?php echo htmlspecialchars($form['form_id']); ?>"
                                                        data-formname="<?php echo htmlspecialchars($form['form_name'] ?: 'Unnamed Form'); ?>">
                                                    <i class="bi bi-link"></i> Custom Link
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger unlink-form-btn"
                                                        data-formid="<?php echo htmlspecialchars($form['form_id']); ?>"
                                                        data-formname="<?php echo htmlspecialchars($form['form_name'] ?: 'Unnamed Form'); ?>">
                                                    <i class="bi bi-x-circle"></i> Delete
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
        </div>
        
        <!-- Custom Links Tab -->
        <div class="tab-pane fade" id="links-content" role="tabpanel" aria-labelledby="links-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Custom Shareable Links</h4>
                    <div>
                        <span class="badge bg-info me-2" id="linksUsage">0 / <?php echo $maxLinks; ?></span>
                        <button type="button" class="btn btn-sm btn-success" id="createLinkBtn">
                            <i class="bi bi-plus-circle"></i> Create New Link
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Custom links provide memorable, branded URLs for your forms. For example: <code><?php echo site_url('u'); ?>?f=my-amazing-form</code>
                    </div>
                    
                    <div id="customLinksContainer">
                        <div class="text-center my-5 loading-indicator">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading your custom links...</p>
                        </div>
                        
                        <div id="noLinksMessage" class="text-center my-5" style="display: none;">
                            <i class="bi bi-link-45deg" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3">You haven't created any custom links yet.</p>
                            <button type="button" class="btn btn-primary" id="createFirstLinkBtn">
                                <i class="bi bi-plus-circle"></i> Create Your First Custom Link
                            </button>
                        </div>
                        
                        <div class="table-responsive" id="linksTable" style="display: none;">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Custom Link</th>
                                        <th>Form</th>
                                        <th>Created</th>
                                        <th>Uses</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="linksList">
                                    <!-- Links will be loaded via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Organization Management Tab -->
        <div class="tab-pane fade" id="organization-content" role="tabpanel" aria-labelledby="organization-tab">
            <?php
            // Attempt to include the organization content.
            // This approach is simple. For more complex scenarios, consider a routing or controller mechanism.
            $org_content_path = ROOT_DIR . '/content/organization/organization.php';
            if (file_exists($org_content_path)) {
                include $org_content_path;
            } else {
                echo '<div class="alert alert-danger">Organization management content is currently unavailable.</div>';
            }
            ?>
        </div>
    </div>
    
    <!-- Share Form Modal -->
    <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareModalLabel">Share Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Share this link with others to let them use your form:</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="shareLink" readonly>
                        <button class="btn btn-outline-primary" type="button" id="copyShareLink">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="mt-3">
                        <p><strong>QR Code:</strong></p>
                        <div class="text-center">
                            <img id="qrCode" src="" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Custom Link Modal -->
    <div class="modal fade" id="customLinkModal" tabindex="-1" aria-labelledby="customLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customLinkModalLabel">Create Custom Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Create a memorable custom link for your form.
                    </div>
                    
                    <form id="customLinkForm">
                        <input type="hidden" id="formIdInput" name="formId">
                        
                        <div class="mb-3">
                            <label for="formNameDisplay" class="form-label">Form</label>
                            <input type="text" class="form-control" id="formNameDisplay" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="customLinkInput" class="form-label">Custom Link</label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo site_url('u'); ?>?f=</span>
                                <input type="text" class="form-control" id="customLinkInput" 
                                       placeholder="my-amazing-form" required 
                                       pattern="[a-zA-Z0-9\-_]+" 
                                       minlength="<?php echo defined('CUSTOM_LINK_MIN_LENGTH') ? CUSTOM_LINK_MIN_LENGTH : 3; ?>" 
                                       maxlength="<?php echo defined('CUSTOM_LINK_MAX_LENGTH') ? CUSTOM_LINK_MAX_LENGTH : 30; ?>">
                            </div>
                            <div class="form-text">
                                Use only letters, numbers, hyphens and underscores.
                                <span id="charCount">0</span> / <?php echo defined('CUSTOM_LINK_MAX_LENGTH') ? CUSTOM_LINK_MAX_LENGTH : 30; ?> characters
                            </div>
                        </div>
                        
                        <div id="customLinkError" class="alert alert-danger" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCustomLinkBtn">Create Link</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Custom Link Confirmation Modal -->
    <div class="modal fade" id="deleteLinkModal" tabindex="-1" aria-labelledby="deleteLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteLinkModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the custom link:</p>
                    <p><code id="linkToDelete"></code></p>
                    <p>This action cannot be undone. Anyone using this link will no longer be able to access your form.</p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="customLinkToDelete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteLinkBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Unlink Form Confirmation Modal -->
    <div class="modal fade" id="unlinkFormModal" tabindex="-1" aria-labelledby="unlinkFormModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="unlinkFormModalLabel">Remove Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove "<span id="form-name-to-unlink"></span>" from your forms?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i> This will only remove the form from your account. The form itself will still exist and be accessible via direct link or to other users if shared.
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="post" action="<?php echo site_url('profile'); ?>">
                        <input type="hidden" name="form_id" id="form-id-to-unlink">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="unlink_form" class="btn btn-danger">Remove</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form search functionality
    const searchBox = document.getElementById('formSearch');
    if (searchBox) {
        searchBox.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#formsList tr');
            
            rows.forEach(row => {
                const formName = row.querySelector('td:first-child').textContent.toLowerCase();
                const formId = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                
                if (formName.includes(searchTerm) || formId.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // Share modal handler
    const shareModal = document.getElementById('shareModal');
    if (shareModal) {
        shareModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const formId = button.getAttribute('data-formid');
            const formName = button.getAttribute('data-formname');
            
            const modalTitle = shareModal.querySelector('.modal-title');
            const shareLink = document.getElementById('shareLink');
            const qrCode = document.getElementById('qrCode');
            
            // Update modal content
            modalTitle.textContent = 'Share: ' + formName;
            
            // Build the share URL
            const shareUrl = `${window.location.origin}/form?f=${formId}`;
            shareLink.value = shareUrl;
            
            // Generate QR code URL (using Google Charts API)
            qrCode.src = `https://chart.googleapis.com/chart?cht=qr&chl=${encodeURIComponent(shareUrl)}&chs=200x200&chld=L|0`;
        });
        
        // Copy share link button
        const copyShareLinkBtn = document.getElementById('copyShareLink');
        if (copyShareLinkBtn) {
            copyShareLinkBtn.addEventListener('click', function() {
                const shareLink = document.getElementById('shareLink');
                shareLink.select();
                document.execCommand('copy');
                
                // Show feedback
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check"></i> Copied!';
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 2000);
            });
        }
    }
    
    // Unlink form modal handler
    const unlinkButtons = document.querySelectorAll('.unlink-form-btn');
    
    unlinkButtons.forEach(button => {
        button.addEventListener('click', function() {
            const formId = this.getAttribute('data-formid');
            const formName = this.getAttribute('data-formname');
            
            document.getElementById('form-id-to-unlink').value = formId;
            document.getElementById('form-name-to-unlink').textContent = formName;
            
            const unlinkFormModal = new bootstrap.Modal(document.getElementById('unlinkFormModal'));
            unlinkFormModal.show();
        });
    });
    
    // Load custom links when tab is shown
    const linksTab = document.getElementById('links-tab');
    if (linksTab) {
        linksTab.addEventListener('shown.bs.tab', function() {
            loadCustomLinks();
        });
    }
    
    // Create custom link button handler
    const createLinkBtns = document.querySelectorAll('.create-custom-link');
    createLinkBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const formId = this.getAttribute('data-formid');
            const formName = this.getAttribute('data-formname');
            
            // Set form data in modal
            document.getElementById('formIdInput').value = formId;
            document.getElementById('formNameDisplay').value = formName;
            
            // Clear previous input
            document.getElementById('customLinkInput').value = '';
            document.getElementById('charCount').textContent = '0';
            document.getElementById('customLinkError').style.display = 'none';
            
            // Generate suggested link based on form name
            let suggestedLink = formName.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '') // Remove special chars except spaces and hyphens
                .replace(/\s+/g, '-')         // Replace spaces with hyphens
                .substring(0, <?php echo defined('CUSTOM_LINK_MAX_LENGTH') ? CUSTOM_LINK_MAX_LENGTH : 30; ?>);
                
            document.getElementById('customLinkInput').value = suggestedLink;
            document.getElementById('charCount').textContent = suggestedLink.length;
            
            // Show modal
            const customLinkModal = new bootstrap.Modal(document.getElementById('customLinkModal'));
            customLinkModal.show();
        });
    });
    
    // Create custom link from links tab
    document.getElementById('createLinkBtn').addEventListener('click', function() {
        // Get the first form from the forms list
        const firstFormRow = document.querySelector('#formsList tr');
        if (!firstFormRow) {
            alert('You need to create a form first before creating a custom link.');
            
            // Switch to forms tab
            const formsTab = document.getElementById('forms-tab');
            bootstrap.Tab.getInstance(formsTab).show();
            return;
        }
        
        const formName = firstFormRow.querySelector('td:first-child').textContent;
        const formId = firstFormRow.querySelector('td:nth-child(2)').textContent;
        
        // Set form data in modal
        document.getElementById('formIdInput').value = formId;
        document.getElementById('formNameDisplay').value = formName;
        
        // Clear previous input
        document.getElementById('customLinkInput').value = '';
        document.getElementById('charCount').textContent = '0';
        document.getElementById('customLinkError').style.display = 'none';
        
        // Generate suggested link based on form name
        let suggestedLink = formName.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '') // Remove special chars except spaces and hyphens
            .replace(/\s+/g, '-')         // Replace spaces with hyphens
            .substring(0, <?php echo defined('CUSTOM_LINK_MAX_LENGTH') ? CUSTOM_LINK_MAX_LENGTH : 30; ?>);
            
        document.getElementById('customLinkInput').value = suggestedLink;
        document.getElementById('charCount').textContent = suggestedLink.length;
        
        // Show modal
        const customLinkModal = new bootstrap.Modal(document.getElementById('customLinkModal'));
        customLinkModal.show();
    });
    
    // Same for "Create First Link" button
    document.getElementById('createFirstLinkBtn').addEventListener('click', function() {
        document.getElementById('createLinkBtn').click();
    });
    
    // Character counter for custom link input
    document.getElementById('customLinkInput').addEventListener('input', function() {
        const charCount = this.value.length;
        document.getElementById('charCount').textContent = charCount;
        
        // Validate the input
        if (this.validity.patternMismatch) {
            this.setCustomValidity('Only letters, numbers, hyphens and underscores are allowed.');
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Save custom link button handler
    document.getElementById('saveCustomLinkBtn').addEventListener('click', function() {
        const formId = document.getElementById('formIdInput').value;
        const customLink = document.getElementById('customLinkInput').value;
        const errorDiv = document.getElementById('customLinkError');
        
        // Basic validation
        if (!customLink) {
            errorDiv.textContent = 'Please enter a custom link.';
            errorDiv.style.display = 'block';
            return;
        }
        
        // Pattern validation (should match the pattern in the input field)
        if (!/^[a-zA-Z0-9\-_]+$/.test(customLink)) {
            errorDiv.textContent = 'Custom link can only contain letters, numbers, hyphens and underscores.';
            errorDiv.style.display = 'block';
            return;
        }
        
        // Length validation
        const minLength = <?php echo defined('CUSTOM_LINK_MIN_LENGTH') ? CUSTOM_LINK_MIN_LENGTH : 3; ?>;
        const maxLength = <?php echo defined('CUSTOM_LINK_MAX_LENGTH') ? CUSTOM_LINK_MAX_LENGTH : 30; ?>;
        
        if (customLink.length < minLength || customLink.length > maxLength) {
            errorDiv.textContent = `Custom link must be between ${minLength} and ${maxLength} characters.`;
            errorDiv.style.display = 'block';
            return;
        }
        
        // Create the custom link via AJAX
        fetch('ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'custom_link',
                action: 'create',
                form_id: formId,
                custom_link: customLink
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close the modal
                bootstrap.Modal.getInstance(document.getElementById('customLinkModal')).hide();
                
                // Show success message
                alert('Custom link created successfully!');
                
                // Reload custom links
                loadCustomLinks();
                
                // Switch to links tab
                const linksTab = document.getElementById('links-tab');
                bootstrap.Tab.getInstance(linksTab).show();
            } else {
                // Show error message
                errorDiv.textContent = data.error || 'Failed to create custom link. Please try again.';
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error creating custom link:', error);
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
        });
    });
    
    // Load custom links function
    function loadCustomLinks() {
        const loadingIndicator = document.querySelector('.loading-indicator');
        const noLinksMessage = document.getElementById('noLinksMessage');
        const linksTable = document.getElementById('linksTable');
        const linksList = document.getElementById('linksList');
        
        // Show loading indicator
        loadingIndicator.style.display = 'block';
        noLinksMessage.style.display = 'none';
        linksTable.style.display = 'none';
        
        // Fetch custom links via AJAX
        fetch('ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'custom_link',
                action: 'list'
            })
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading indicator
            loadingIndicator.style.display = 'none';
            
            // Update links usage counter
            const linksUsage = document.getElementById('linksUsage');
            linksUsage.textContent = `${data.data.links_used} / ${data.data.max_links}`;
            
            if (data.success && data.data.links.length > 0) {
                // Clear previous links
                linksList.innerHTML = '';
                
                // Add links to the table
                data.data.links.forEach(link => {
                    const row = document.createElement('tr');
                    
                    // Format date
                    const createdDate = new Date(link.created_at * 1000);
                    const formattedDate = createdDate.toLocaleString();
                    
                    // Create row content
                    row.innerHTML = `
                        <td>
                            <a href="${link.full_url}" target="_blank">${link.custom_link}</a>
                        </td>
                        <td>${link.form_name}</td>
                        <td>${formattedDate}</td>
                        <td>${link.use_count || 0}</td>
                        <td class="form-actions">
                            <button type="button" class="btn btn-sm btn-outline-primary copy-link-btn" 
                                    data-link="${link.full_url}">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-link-btn"
                                    data-link="${link.custom_link}">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </td>
                    `;
                    
                    linksList.appendChild(row);
                });
                
                // Show links table
                linksTable.style.display = 'block';
                
                // Add event listeners to copy and delete buttons
                document.querySelectorAll('.copy-link-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const link = this.getAttribute('data-link');
                        navigator.clipboard.writeText(link)
                            .then(() => {
                                // Show feedback
                                const originalHTML = this.innerHTML;
                                this.innerHTML = '<i class="bi bi-check"></i> Copied!';
                                setTimeout(() => {
                                    this.innerHTML = originalHTML;
                                }, 2000);
                            })
                            .catch(err => {
                                console.error('Error copying link:', err);
                                alert('Failed to copy link to clipboard.');
                            });
                    });
                });
                
                document.querySelectorAll('.delete-link-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const link = this.getAttribute('data-link');
                        
                        // Set link in delete confirmation modal
                        document.getElementById('linkToDelete').textContent = link;
                        document.getElementById('customLinkToDelete').value = link;
                        
                        // Show delete confirmation modal
                        const deleteModal = new bootstrap.Modal(document.getElementById('deleteLinkModal'));
                        deleteModal.show();
                    });
                });
            } else {
                // Show no links message
                noLinksMessage.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading custom links:', error);
            loadingIndicator.style.display = 'none';
            alert('An error occurred while loading your custom links. Please try again.');
        });
    }
    
    // Delete custom link button handler
    document.getElementById('confirmDeleteLinkBtn').addEventListener('click', function() {
        const customLink = document.getElementById('customLinkToDelete').value;
        
        // Delete the custom link via AJAX
        fetch('ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'custom_link',
                action: 'delete',
                custom_link: customLink
            })
        })
        .then(response => response.json())
        .then(data => {
            // Close the modal
            bootstrap.Modal.getInstance(document.getElementById('deleteLinkModal')).hide();
            
            if (data.success) {
                // Show success message
                alert('Custom link deleted successfully!');
                
                // Reload custom links
                loadCustomLinks();
            } else {
                // Show error message
                alert(data.error || 'Failed to delete custom link. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error deleting custom link:', error);
            bootstrap.Modal.getInstance(document.getElementById('deleteLinkModal')).hide();
            alert('An error occurred while deleting the custom link. Please try again.');
        });
    });
});
</script>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'My Forms';

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">
<link rel="stylesheet" href="'. asset_path('css/pages/analytics.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';