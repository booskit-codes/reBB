<?php
/**
 * reBB - API Caller
 *
 * Page for selecting an API and generating BBCode based on its schema.
 */

// Define the page content to be yielded in the master layout
ob_start();

$apiIdentifier = null;
$apiSchemaExists = false;
$errorMessage = '';
$page_js_vars_array = ['ajaxUrl' => site_url('ajax')]; // Initialize with ajaxUrl

if (isset($_GET['api'])) {
    $apiIdentifier = trim($_GET['api']);
    // Validate the identifier format (api_ followed by 16 hex characters)
    if (preg_match('/^api_[a-f0-9]{16}$/', $apiIdentifier)) {
        $apisDir = STORAGE_DIR . '/apis'; // Changed to STORAGE_DIR
        $apiFilename = $apisDir . '/' . $apiIdentifier . '.json';
        if (file_exists($apiFilename)) {
            $apiSchemaExists = true;
            // Pass the identifier to JavaScript so it can fetch the schema details
            $page_js_vars_array['apiIdentifierToLoad'] = $apiIdentifier;
        } else {
            $errorMessage = "API schema not found for identifier: " . htmlspecialchars($apiIdentifier);
        }
    } else {
        $errorMessage = "Invalid API identifier format provided.";
    }
} else {
    $errorMessage = "No API specified. Please provide an API identifier in the URL (e.g., ?api=api_youridentifier).";
}

?>

<div class="container mt-5">
    <h1>API Caller</h1>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <?php echo $errorMessage; ?>
            <?php if (!isset($_GET['api']) || empty($_GET['api'])): ?>
                 You can <a href="<?php echo site_url('api_builder'); ?>">create a new API</a> or check the <a href="<?php echo site_url('api_docs'); ?>">API Documentation</a> for available API identifiers.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($apiSchemaExists): ?>
        <p>Fill in the fields for the loaded API and generate the BBCode.</p>
        <div class="card">
            <div class="card-header">
                <h3 id="currentApiNameLoading">Loading API Details...</h3> <!-- JS will update this -->
            </div>
            <div class="card-body">
                <form id="apiCallForm"> <!-- Initially visible, JS will populate or hide if error -->
                    <div id="apiInputFieldsContainer" class="mb-3">
                        <!-- Dynamic input fields will be loaded here by JS -->
                        <p class="text-muted">Loading fields...</p>
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
            </div>
        </div>
    <?php endif; ?>

     <div class="mt-4">
        <a href="<?php echo site_url('api_builder'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil-square"></i> Go to API Builder
        </a>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Add page-specific CSS (optional, if needed)
// $GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/api-caller.css') .'?v=' . APP_VERSION . '">';

// Add page-specific JavaScript
$jsVarsString = "";
foreach ($page_js_vars_array as $key => $value) {
    $jsVarsString .= "var " . $key . " = " . json_encode($value) . ";\n";
}
$GLOBALS['page_js_vars'] = $jsVarsString;
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/api_caller.js') .'?v=' . APP_VERSION . '"></script>';


// Include the master layout
require_once ROOT_DIR . '/includes/master.php';

?>
