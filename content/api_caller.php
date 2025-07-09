<?php
/**
 * reBB - API Caller
 *
 * Page for selecting an API and generating BBCode based on its schema.
 */

// Define the page content to be yielded in the master layout
ob_start();

$apisDir = ROOT_DIR . '/apis';
$apiFiles = [];
if (is_dir($apisDir)) {
    $files = scandir($apisDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $apiFiles[] = pathinfo($file, PATHINFO_FILENAME);
        }
    }
}
sort($apiFiles);

?>

<div class="container mt-5">
    <h1>API Caller</h1>
    <p>Select an API, fill in the fields, and generate the BBCode.</p>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    Select API
                </div>
                <div class="card-body">
                    <?php if (empty($apiFiles)): ?>
                        <p class="text-muted">No API schemas found in the '<?php echo basename($apisDir); ?>' directory. <a href="<?php echo site_url('api_builder'); ?>">Create one first!</a></p>
                    <?php else: ?>
                        <form id="selectApiForm">
                            <div class="mb-3">
                                <label for="selectedApi" class="form-label">Available APIs:</label>
                                <select class="form-select" id="selectedApi" name="selected_api">
                                    <option value="">-- Select an API --</option>
                                    <?php foreach ($apiFiles as $apiName): ?>
                                        <option value="<?php echo htmlspecialchars($apiName); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($apiName))); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    API Fields & Output
                </div>
                <div class="card-body">
                    <form id="apiCallForm" style="display: none;"> <!-- Hidden until an API is selected -->
                        <h3 id="currentApiName"></h3>
                        <div id="apiInputFieldsContainer" class="mb-3">
                            <!-- Dynamic input fields will be loaded here -->
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-code-square"></i> Generate BBCode
                        </button>
                    </form>

                    <div id="apiOutputContainer" class="mt-4" style="display: none;">
                        <h4>Generated BBCode:</h4>
                        <textarea id="generatedBbcode" class="form-control" rows="10" readonly></textarea>
                        <button id="copyBbcodeBtn" class="btn btn-sm btn-secondary mt-2">
                            <i class="bi bi-clipboard"></i> Copy to Clipboard
                        </button>
                    </div>

                    <div id="noApiSelectedMessage" class="text-muted">
                        <p>Please select an API from the list to see its fields.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
     <div class="mt-4">
        <a href="<?php echo site_url('api_builder'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil-square"></i> Back to API Builder
        </a>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Add page-specific CSS (optional, if needed)
// $GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/api-caller.css') .'?v=' . APP_VERSION . '">';

// Add page-specific JavaScript
// We'll create api_caller.js in a subsequent step
$GLOBALS['page_js_vars'] = "var ajaxUrl = '" . site_url('ajax') . "';"; // Pass ajax URL to JS
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/api_caller.js') .'?v=' . APP_VERSION . '"></script>';


// Include the master layout
require_once ROOT_DIR . '/includes/master.php';

?>
