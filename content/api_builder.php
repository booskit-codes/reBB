<?php
/**
 * reBB - API Builder
 *
 * Page for creating and defining API schemas.
 */

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container mt-5">
    <h1>API Builder</h1>
    <p>Define your API structure, fields, and BBCode wrappers.</p>

    <form id="apiBuilderForm">
        <div class="card mb-3">
            <div class="card-header">
                API Configuration
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="apiName" class="form-label">API Name</label>
                    <input type="text" class="form-control" id="apiName" name="api_name" placeholder="e.g., my_character_sheet_api" required>
                    <div class="form-text">This will be used for the filename (e.g., my_character_sheet_api.json) and for calling the API. Use letters, numbers, and underscores only.</div>
                </div>

                <div class="mb-3">
                    <label for="overallWrapper" class="form-label">Overall BBCode Wrapper</label>
                    <textarea class="form-control" id="overallWrapper" name="overall_wrapper" rows="3" placeholder="e.g., [section={api_name}]{content}[/section]"></textarea>
                    <div class="form-text">Use <code>{api_name}</code> for the API name and <code>{content}</code> for the combined fields.</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                API Fields
            </div>
            <div class="card-body">
                <div id="apiFieldsContainer">
                    <!-- Fields will be added here by JavaScript -->
                    <div class="api-field-template mb-3 p-3 border rounded" style="display: none;">
                        <h5>Field <span class="field-number">1</span></h5>
                        <div class="mb-3">
                            <label for="fieldName_1" class="form-label">Field Name</label>
                            <input type="text" class="form-control field-name-input" name="fields[0][name]" placeholder="e.g., character_name">
                        </div>
                        <div class="mb-3">
                            <label for="fieldWrapper_1" class="form-label">Field BBCode Wrapper</label>
                            <textarea class="form-control field-wrapper-input" name="fields[0][wrapper]" rows="2" placeholder="e.g., [b]{field_value}[/b] or [param={field_value}]Default Text[/param]"></textarea>
                            <div class="form-text">Use <code>{field_value}</code> for the user-supplied value for this field.</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-field-btn">Remove Field</button>
                    </div>
                </div>
                <button type="button" id="addFieldBtn" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Add Field
                </button>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save"></i> Save API Schema
            </button>
            <a href="<?php echo site_url('api_caller'); ?>" class="btn btn-info btn-lg ms-2">
                <i class="bi bi-lightning-charge"></i> Go to API Caller
            </a>
        </div>
    </form>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Add page-specific CSS (optional, if needed)
// $GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/api-builder.css') .'?v=' . APP_VERSION . '">';

// Add page-specific JavaScript
$GLOBALS['page_js_vars'] = ""; // No specific JS vars needed for now from PHP
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/api_builder.js') .'?v=' . APP_VERSION . '"></script>';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';

?>
