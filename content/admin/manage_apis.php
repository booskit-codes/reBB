<?php
/**
 * reBB - Admin: Manage APIs Page
 */

// Require admin authentication
auth()->requireRole('admin', 'login');

$apisDir = STORAGE_DIR . '/apis';
$apiSchemas = [];
$actionMessage = $_SESSION['action_message'] ?? null; // For messages from AJAX redirects
unset($_SESSION['action_message']);


if (is_dir($apisDir)) {
    $files = scandir($apisDir);
    foreach ($files as $file) {
        if (str_starts_with($file, 'api_') && str_ends_with($file, '.json')) {
            $filePath = $apisDir . '/' . $file;
            $content = file_get_contents($filePath);
            $schema = json_decode($content, true);

            if ($schema && isset($schema['api_identifier'])) {
                $apiSchemas[] = [
                    'identifier' => $schema['api_identifier'],
                    'display_name' => $schema['display_name'] ?? 'N/A',
                    'created_at' => $schema['created_at'] ?? filectime($filePath),
                    'updated_at' => $schema['updated_at'] ?? filemtime($filePath),
                    'filename' => $file
                ];
            }
        }
    }
    // Sort by display name, then by identifier if display names are the same or N/A
    usort($apiSchemas, function($a, $b) {
        $nameComparison = strcmp(strtolower($a['display_name']), strtolower($b['display_name']));
        if ($nameComparison === 0) {
            return strcmp($a['identifier'], $b['identifier']);
        }
        return $nameComparison;
    });
} else {
    // Ensure the directory exists, if not, attempt to create it.
    if (!mkdir($apisDir, 0755, true) && !is_dir($apisDir)) {
         $actionMessage = "Error: The APIs storage directory ('" . htmlspecialchars($apisDir) . "') does not exist and could not be created.";
    } else {
        // Directory created or already existed but was empty initially
        $actionMessage = $actionMessage ?: "API storage directory is ready. No APIs found yet.";
    }
}


ob_start();
?>
<div class="container-admin">
    <div class="page-header">
        <h1>Manage APIs</h1>
        <div>
            <a href="<?php echo site_url('api_builder'); ?>" class="btn btn-success"><i class="bi bi-plus-circle"></i> Create New API</a>
            <a href="<?php echo site_url('admin'); ?>" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
    </div>

    <?php if ($actionMessage): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert" id="actionMessageAlert">
            <?php echo htmlspecialchars($actionMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div id="adminApiAlertsContainer" class="mt-3">
        <!-- Dynamic alerts from JS actions (delete, etc.) will be injected here -->
    </div>

    <div class="card">
        <div class="card-header">
            <h4>Existing API Schemas</h4>
        </div>
        <div class="card-body">
            <?php if (empty($apiSchemas)): ?>
                <p class="text-muted">No API schemas found in '<?php echo htmlspecialchars(basename(STORAGE_DIR) . '/apis'); ?>'.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Display Name</th>
                                <th>API Identifier</th>
                                <th>Last Updated</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="apiListTableBody">
                            <?php foreach ($apiSchemas as $api): ?>
                                <tr id="api-row-<?php echo htmlspecialchars($api['identifier']); ?>">
                                    <td><?php echo htmlspecialchars($api['display_name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($api['identifier']); ?></code></td>
                                    <td><?php echo date('Y-m-d H:i:s', $api['updated_at']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', $api['created_at']); ?></td>
                                    <td>
                                        <a href="<?php echo site_url('admin/apis/edit?id=' . htmlspecialchars(str_replace('api_', '', $api['identifier']))); ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-filetype-json"></i> Edit JSON
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-api-btn ms-1"
                                                data-api-id="<?php echo htmlspecialchars($api['identifier']); ?>"
                                                data-api-name="<?php echo htmlspecialchars($api['display_name']); ?>">
                                            <i class="bi bi-trash"></i> Delete
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

<!-- Delete Confirmation Modal (Generic, can be reused or made specific if needed) -->
<div class="modal fade" id="deleteApiConfirmModal" tabindex="-1" aria-labelledby="deleteApiConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteApiConfirmModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete the API: <strong id="apiNameToDelete"></strong> (<code><span id="apiIdentifierToDelete"></span></code>)?
        <p class="text-danger mt-2">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteApiBtn">Delete API</button>
      </div>
    </div>
  </div>
</div>


<?php
$GLOBALS['page_content'] = ob_get_clean();
$GLOBALS['page_title'] = 'Admin - Manage APIs';
// Link to a new JS file specifically for this page if needed, or add to a general admin JS
$GLOBALS['page_js_vars'] = "var ajaxUrl = '" . site_url('ajax') . "';\n";
$GLOBALS['page_js_vars'] .= "var csrfToken = '" . (function_exists('csrf_token') ? csrf_token() : '') . "';"; // Example if CSRF is used
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/admin_apis.js') .'?v=' . APP_VERSION . '"></script>'; // Assuming admin_apis.js will be created
require_once ROOT_DIR . '/includes/master.php';
?>
