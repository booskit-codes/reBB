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

    <div id="apiBuilderAlertsContainer" class="mt-3">
        <!-- Dynamic alerts will be injected here by JavaScript -->
    </div>

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
                    <label for="mainBbcodeTemplate" class="form-label">Main BBCode Template</label>
                    <textarea class="form-control" id="mainBbcodeTemplate" name="main_bbcode_template" rows="4" placeholder="e.g., [article={api_name}]\n{title}\n{content_body}\n[/article]"></textarea>
                    <div class="form-text">
                        Use <code>{api_name}</code> for the API name. Available field wildcards: <code id="availableWildcardsDisplay">(none yet)</code>.
                        These wildcards (e.g. <code>{field_name}</code>) will be replaced by the processed content of each corresponding field.
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                API Fields Definition
            </div>
            <div class="card-body">
                <div id="apiFieldsContainer">
                    <!-- Fields will be added here by JavaScript -->
                    <div class="api-field-template mb-4 p-3 border rounded shadow-sm" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5>Field <span class="field-number">1</span></h5>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-field-btn">
                                <i class="bi bi-trash"></i> Remove Field
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Field Name</label>
                                <input type="text" class="form-control field-name-input" name="fields[0][name]" placeholder="e.g., character_name (no spaces/special chars)">
                                <div class="form-text">Used as the wildcard in the Main BBCode Template (e.g. <code>{character_name}</code>).</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Individual Item BBCode Wrapper</label>
                                <textarea class="form-control field-wrapper-input" name="fields[0][wrapper]" rows="2" placeholder="e.g., [b]{field_value}[/b]"></textarea>
                                <div class="form-text">Wraps each value/item. Use <code>{field_value}</code>.</div>
                            </div>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input multi-entry-checkbox" type="checkbox" value="" id="multiEntryCheck_0">
                            <label class="form-check-label" for="multiEntryCheck_0">
                                Multi-entry Field (allows multiple values for this field)
                            </label>
                        </div>

                        <div class="multi-entry-wrappers" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Multi-entry Start Wrapper</label>
                                    <input type="text" class="form-control multi-start-wrapper-input" name="fields[0][multi_start_wrapper]" placeholder="e.g., [list]">
                                    <div class="form-text">Appears once before all items of this multi-entry field.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Multi-entry End Wrapper</label>
                                    <input type="text" class="form-control multi-end-wrapper-input" name="fields[0][multi_end_wrapper]" placeholder="e.g., [/list]">
                                    <div class="form-text">Appears once after all items of this multi-entry field.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" id="addFieldBtn" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Add Field
                </button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                Live Preview
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Sample Inputs</h5>
                        <div id="livePreviewSampleInputsContainer" class="p-2 border rounded bg-light" style="min-height: 100px;">
                            <p class="text-muted placeholder-text">Define fields above to see sample inputs here.</p>
                            <!-- Sample inputs will be dynamically generated here by JS -->
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Preview Output</h5>
                        <textarea id="livePreviewOutput" class="form-control" rows="10" readonly placeholder="BBCode preview will appear here..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 mb-5">
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
// ajaxUrl will be used by api_builder.js for fetching/posting
$GLOBALS['page_js_vars'] = "var ajaxUrl = '" . site_url('ajax') . "';";
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/api_builder.js') .'?v=' . APP_VERSION . '"></script>';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';

?>
