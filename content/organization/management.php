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
$actionMessage = '';
$actionMessageType = 'info';

// Initialize SleekDB stores
try {
    $userOrgStore = new \SleekDB\Store('user_to_organization_links', $dbPath, ['auto_cache' => false, 'timeout' => false]);
    $organizationsStore = new \SleekDB\Store('organizations', $dbPath, ['auto_cache' => false, 'timeout' => false]);
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
                        $actionMessage = "Organization '{$orgName}' created successfully!";
                        $actionMessageType = 'success';
                        // Re-fetch user's org link
                        $userOrganizationLink = $userOrgStore->findOneBy(['user_id', '=', $currentUser['_id']]);
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

// --- Fetch Organization Details if User is Linked ---
if (isset($userOrgStore) && !$userOrganizationLink) { // Check if not already fetched after creation
    $userOrganizationLink = $userOrgStore->findOneBy(['user_id', '=', $currentUser['_id']]);
}

if ($userOrganizationLink && isset($userOrganizationLink['organization_id']) && isset($organizationsStore)) {
    $organization = $organizationsStore->findById($userOrganizationLink['organization_id']);

    // Fetch forms belonging to this organization
    if ($organization) {
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
        <a href="<?php echo site_url('profile'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Profile
        </a>
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

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Organization Forms</h4>
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

require_once ROOT_DIR . '/includes/master.php';
?>
