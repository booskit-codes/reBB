<?php
/**
 * reBB - Admin - Edit Form JSON
 *
 * Allows system administrators to directly edit the JSON schema of a form.
 */

// Require admin authentication
auth()->requireRole(Auth::ROLE_ADMIN, 'login');
$currentUser = auth()->getUser();

$formId = null;
$formPath = null;
$jsonContent = '';
$actionMessage = '';
$actionMessageType = 'info';

if (isset($_GET['form_id'])) {
    $formId = trim($_GET['form_id']);
    // Basic validation for form ID (alphanumeric, maybe dashes/underscores)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $formId)) {
        $actionMessage = "Invalid Form ID format.";
        $actionMessageType = 'danger';
        $formId = null; // Invalidate to prevent further processing
    } else {
        $formPath = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
        if (!file_exists($formPath)) {
            $actionMessage = "Form JSON file not found for ID: " . htmlspecialchars($formId);
            $actionMessageType = 'danger';
            $formPath = null; // Invalidate
        }
    }
} else {
    $actionMessage = "No Form ID specified.";
    $actionMessageType = 'danger';
}

// Handle JSON save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_json']) && $formPath && $formId) {
    $submittedJson = $_POST['json_content'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!auth()->verifyCsrfToken($csrfToken)) {
        $actionMessage = "Invalid form submission (CSRF token mismatch). Please try again.";
        $actionMessageType = 'danger';
    } else {
        // Validate if the submitted content is valid JSON
        json_decode($submittedJson);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (file_put_contents($formPath, $submittedJson)) {
                $actionMessage = "Form JSON for ID '" . htmlspecialchars($formId) . "' updated successfully!";
                $actionMessageType = 'success';
                // logAdminAction is not defined in this file, this should be a global helper or defined here.
                // Assuming a global logAdminAction similar to other admin pages.
                if (function_exists('logAdminAction')) {
                    logAdminAction("Admin edited JSON for form ID: $formId");
                }
            } else {
                $actionMessage = "Error saving Form JSON for ID '" . htmlspecialchars($formId) . "'. Check file permissions.";
                $actionMessageType = 'danger';
                if (function_exists('logAdminAction')) {
                    logAdminAction("Failed to save JSON for form ID: $formId (file_put_contents failed)", false);
                }
            }
        } else {
            $actionMessage = "Invalid JSON provided. Changes not saved. Error: " . json_last_error_msg();
            $actionMessageType = 'danger';
            if (function_exists('logAdminAction')) {
                logAdminAction("Attempted to save invalid JSON for form ID: $formId", false);
            }
        }
    }
    // Reload content after attempt, whether success or fail, to show current state or user's (invalid) input
    $jsonContent = $submittedJson; // Show what user submitted if it was invalid
    if ($actionMessageType === 'success' && file_exists($formPath)) { // On success, reload from file
         $jsonContent = file_get_contents($formPath);
    }

} elseif ($formPath) { // Load for GET request
    $jsonContent = file_get_contents($formPath);
}

$csrfToken = auth()->generateCsrfToken();

// Define the page content
ob_start();
?>

<div class="container-admin mt-4">
    <div class="page-header">
        <h1>Edit Form JSON: <?php echo htmlspecialchars($formId ?? 'N/A'); ?></h1>
        <div>
            <a href="<?php echo site_url('admin'); ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-speedometer2"></i> Admin Dashboard
            </a>
             <a href="<?php echo site_url('admin/organizations'); ?>" class="btn btn-outline-info me-2">
                <i class="bi bi-diagram-3-fill"></i> Manage Organizations
            </a>
        </div>
    </div>

    <?php if ($actionMessage): ?>
        <div class="alert alert-<?php echo $actionMessageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($actionMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($formId && $formPath): ?>
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">JSON Content for <small class="text-muted"><?php echo htmlspecialchars($formId); ?>_schema.json</small></h4>
            </div>
            <div class="card-body">
                <form method="POST" action="<?php echo site_url('admin/forms/edit-json?form_id=' . htmlspecialchars($formId)); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="form_id" value="<?php echo htmlspecialchars($formId); ?>">
                    <div class="mb-3">
                        <textarea name="json_content" class="form-control" rows="25" style="font-family: monospace; white-space: pre;"><?php echo htmlspecialchars($jsonContent); ?></textarea>
                    </div>
                    <button type="submit" name="save_json" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save JSON
                    </button>
                    <a href="<?php echo site_url('form?f=' . htmlspecialchars($formId)); ?>" class="btn btn-info" target="_blank">
                        <i class="bi bi-eye"></i> View Form
                    </a>
                </form>
            </div>
        </div>
        <div class="alert alert-warning mt-3">
            <strong><i class="bi bi-exclamation-triangle-fill"></i> Warning:</strong> Editing JSON directly can break forms if not done carefully. Ensure your JSON is valid and maintains the expected schema structure.
        </div>
    <?php elseif(!$actionMessage): // Only show if no other critical error message (like No Form ID) is already up
        echo '<div class="alert alert-warning">Form data could not be loaded. Please check the Form ID and try again.</div>';
    endif; ?>

</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();
$GLOBALS['page_title'] = 'Edit Form JSON' . ($formId ? ' - ' . $formId : '');
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">';
// Potentially add CodeMirror or Ace editor JS/CSS here in the future
// $GLOBALS['page_javascript'] = '<script src="..."></script>';

require_once ROOT_DIR . '/includes/master.php';
?>
