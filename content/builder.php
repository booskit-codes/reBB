<?php
/**
 * reBB - Builder
 *
 * This file serves as the renderer for the form builder.
 */

$existingSchema = null;
$existingFormName = '';
$existingTemplate = '';
$existingTemplateTitle = ''; // New variable for template title
$existingTemplateLink = '';  // New variable for template link
$enableTemplateTitle = false; // New variable for title toggle
$enableTemplateLink = false;  // New variable for link toggle
$enablePublicLink = false;
$existingFormStyle = 'default'; // Default style
$editMode = isset($_GET['edit_mode']) && $_GET['edit_mode'] === 'true';
$formCreator = null;
$formWidth = 45;
$isOwnForm = false;
$useBasicBuilder = isset($_GET['b']) && $_GET['b'] === 'basic';

if (isset($_GET['f']) && !empty($_GET['f'])) {
    // Allow JSON view only if enabled or user is admin
    if(ENABLE_JSON_VIEW || (function_exists('auth') && auth()->hasRole('admin'))) {
        $formId = $_GET['f'];
        $filename = STORAGE_DIR . '/forms/' . $formId . '_schema.json';

        if (file_exists($filename)) {
            $fileContent = file_get_contents($filename);
            $formData = json_decode($fileContent, true);
            if ($formData && isset($formData['schema'])) {
                // Ensure schema is valid JSON before encoding
                if (is_array($formData['schema']) || is_object($formData['schema'])) {
                     $existingSchema = json_encode($formData['schema']);
                } else {
                    // Handle cases where schema might not be valid JSON - set to null or empty object
                    $existingSchema = 'null';
                    error_log("Warning: Invalid schema data found for form ID: " . $formId);
                }
                $existingFormName = isset($formData['formName']) ? $formData['formName'] : '';
                $existingTemplate = isset($formData['template']) ? $formData['template'] : '';
                $existingTemplateTitle = isset($formData['templateTitle']) ? $formData['templateTitle'] : '';
                $existingTemplateLink = isset($formData['templateLink']) ? $formData['templateLink'] : '';
                $enableTemplateTitle = isset($formData['enableTemplateTitle']) ? $formData['enableTemplateTitle'] : false;
                $enableTemplateLink = isset($formData['enableTemplateLink']) ? $formData['enableTemplateLink'] : false;
                $enablePublicLink = isset($formData['enablePublicLink']) ? $formData['enablePublicLink'] : false;
                $existingFormStyle = isset($formData['formStyle']) ? $formData['formStyle'] : 'default';
                $formWidth = isset($formData['formWidth']) ? $formData['formWidth'] : 45;
                $formCreator = isset($formData['createdBy']) ? $formData['createdBy'] : null;

                // Check if the current user is the creator of this form
                if (function_exists('auth') && auth()->isLoggedIn()) {
                    $currentUser = auth()->getUser();
                    $isOwnForm = ($formCreator === $currentUser['_id'] || $currentUser['role'] === 'admin');
                }
            }
        }
    }
}

$formStyles = [
    [
        'id' => 'styleDefault',
        'value' => 'default',
        'label' => 'Default',
        'description' => 'Standard form layout with clean design.',
        'default' => true
    ],
    [
        'id' => 'stylePaperwork',
        'value' => 'paperwork',
        'label' => 'Paperwork',
        'description' => 'Form styled like an official document or paperwork.'
    ],
    [
        'id' => 'styleVector',
        'value' => 'vector',
        'label' => 'Vector',
        'description' => 'Clean, professional style resembling a fillable PDF document.'
    ],
    [
        'id' => 'styleRetro',
        'value' => 'retro',
        'label' => 'Retro',
        'description' => 'Classic, nostalgic retro-style theme resembling an old program.'
    ],
    [
        'id' => 'styleModern',
        'value' => 'modern',
        'label' => 'Modern',
        'description' => 'Modern, slick style with a clean, minimalist aesthetic.'
    ]
];

// Check if this is in edit mode and if the user has permission
if ($editMode && !$isOwnForm) {
    // Define the page content to be yielded in the master layout
    ob_start();
    echo '<div class="container">';
    echo '<div class="alert alert-danger mt-4">';
    echo '<h4><i class="bi bi-exclamation-triangle"></i> Permission Denied</h4>';
    echo '<p>You do not have permission to edit this form. You must be logged in as the form creator or an administrator.</p>';
    echo '<a href="' . site_url() . '" class="btn btn-primary">Return to Home</a>';
    echo '</div>';
    echo '</div>';
    $GLOBALS['page_content'] = ob_get_clean();
    $GLOBALS['page_title'] = 'Permission Denied';
    require_once ROOT_DIR . '/includes/master.php';
    exit;
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<div id="content-wrapper">
    <?php if ($editMode && $isOwnForm): ?>
    <div class="alert alert-info">
        <i class="bi bi-pencil-square"></i> <strong>Edit Mode:</strong> You are editing your form. Changes will be saved to the original form.
    </div>
    <?php endif; ?>

    <?php if ($useBasicBuilder): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> <strong>Basic Builder:</strong> You are using the simplified form builder. For advanced features, use the <a href="?b=standard">standard builder</a>. Note that the basic builder is in it's early stages, you may encounter bugs.
    </div>
    <?php endif; ?>

    <div class="row">
        <?php if ($useBasicBuilder): ?>
        <div class="col-xl-2 col-sm-3 col-12 input-selector-container mb-3">
            <h5 class="header mb-2">Field Types</h5>
            <div class="input-selector">
                <div class="input-types">
                    <button type="button" class="field-type" data-field-type="textfield">
                        <i class="bi bi-type me-2"></i>
                        <span class="d-block d-sm-none d-md-block">Text Field</span>
                    </button>
                    <button type="button" class="field-type" data-field-type="textarea">
                        <i class="bi bi-textarea-t me-2"></i>
                        <span class="d-block d-sm-none d-md-block">Text Area</span>
                    </button>
                    <button type="button" class="field-type" data-field-type="select">
                        <i class="bi bi-menu-button-wide me-2"></i>
                        <span class="d-block d-sm-none d-md-block">Dropdown</span>
                    </button>
                    <button type="button" class="field-type" data-field-type="checkbox">
                        <i class="bi bi-check-square me-2"></i>
                        <span class="d-block d-sm-none d-md-block">Checkbox</span>
                    </button>
                    <button type="button" class="field-type" data-field-type="datetime">
                        <i class="bi bi-calendar-date-fill me-2"></i>
                        <span class="d-block d-sm-none d-md-block">Date & Time</span>
                    </button>
                    <button type="button" class="field-type" data-field-type="time">
                        <i class="bi bi-clock me-2"></i>
                        <span class="d-block d-sm-none d-md-block">Time</span>
                    </button>
                    <button type="button" class="field-type" data-field-type="url">
                        <i class="bi bi-link me-2"></i>
                        <span class="d-block d-sm-none d-md-block">URL</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-xl-10 col-sm-9 col-12 preview-container">
            <h5 class="header mb-2">Form Preview</h5>
            <form class="">
                <ul style="padding: 0px; list-style-type: none;"></ul>
                 </form>
        </div>
        <?php else: ?>
        <div class="col-12">
            <div id='builder'></div>
        </div>
        <?php endif; ?>
    </div>

    <div id='form-name-container' style="margin-top: 20px;">
        <h3>Form Name:</h3>
        <input type='text' id='formName' class='form-control' placeholder='Enter form name' value="<?php echo htmlspecialchars($existingFormName); ?>">
    </div>

    <div id='form-style-container' style="margin-top: 20px;">
        <h3>Form Style:</h3>
        <div class="form-style-options">
        <?php
        foreach ($formStyles as $style) {?>
            <label class="style-option" for="<?php echo $style['id']; ?>">
                <input class="form-check-input" type="radio" name="formStyle"
                       id="<?php echo $style['id']; ?>" value="<?php echo $style['value']; ?>"
                       <?php echo ($existingFormStyle === $style['value'] || (!$existingFormStyle && $style['default'])) ? 'checked' : ''; ?>>
                <span class="form-check-label"><?php echo $style['label']; ?></span>
                <div class="style-tooltip">
                    <i class="bi bi-info-circle"></i>
                    <div class="tooltip-content">
                        <p><?php echo $style['description']; ?></p>
                        <div class="tooltip-image">
                            <img src="<?php echo asset_path('images/form-types/' . $style['value'] . '.png'); ?>"
                                 alt="<?php echo $style['label']; ?> style preview">
                        </div>
                    </div>
                </div>
            </label>
            <?php
        }
        ?>
        </div>
    </div>

    <div id='template-container'>
        <div id='wildcard-container'>
            <h3>Available Wildcards:</h3>
            <div id='wildcard-list'></div>
             <div id="dataset-help-text" class="alert alert-info mt-2" style="display: none;">
                 <small><strong>Note:</strong> Dataset wildcards (highlighted in red) must be included in your template before saving. They define where repeating content will appear.</small>
             </div>
        </div>
        <h3>Form Template:</h3>
        <textarea id='formTemplate' class='form-control' rows='5'
                    placeholder='Paste your BBCode / HTML / Template here, use the wildcards above, example: [b]Name:[/b] {NAME_ABC1}.'><?php echo htmlspecialchars($existingTemplate); ?></textarea>
    </div>

    <div id='template-extra-container' style="margin-top: 20px;">
    <h3>Additional Form Options:</h3>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 me-2">Custom Form Width</h5>
                    <div class="style-tooltip">
                        <i class="bi bi-info-circle"></i>
                        <div class="tooltip-content">
                            <p>Adjust the width of your form to better fit your needs. A narrower form works well for simple forms, while wider forms are better for complex layouts.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <small class="form-text text-muted d-block mb-2">Set the maximum width for your form (default: 45%)</small>
                <div class="d-flex align-items-center">
                    <input type="range" class="form-range me-2" id="formWidthSlider"
                        min="20" max="100" step="5" value="<?php echo $formWidth; ?>">
                    <div class="input-group" style="width: 120px;">
                        <input type="number" class="form-control" id="formWidthInput"
                            min="20" max="100" step="5" value="<?php echo $formWidth; ?>">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 me-2">Dynamic Title Field</h5>
                    <div class="style-tooltip">
                        <i class="bi bi-info-circle"></i>
                        <div class="tooltip-content">
                            <p>The Dynamic Title Field allows you to generate custom titles for your form. You can use wildcards to create dynamic and flexible title templates.</p>
                            <div class="tooltip-image">
                                <img src="<?php echo asset_path('images/form-options/dynamic-title.png'); ?>"
                                     alt="Dynamic Title Field preview">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="templateTitleToggle"
                        <?php echo $enableTemplateTitle ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="templateTitleToggle">Enable</label>
                </div>
            </div>
            <div class="card-body" id="templateTitleSection" style="<?php echo $enableTemplateTitle ? '' : 'display: none;'; ?>">
                <small class="form-text text-muted d-block mb-2">Offer generated titles for users to copy and paste a title into their generated form.</small>
                <input type='text' id='templateTitle' class='form-control'
                       placeholder='Generally used to create dynamic topic names'
                       value="<?php echo htmlspecialchars($existingTemplateTitle); ?>">
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                        <h5 class="mb-0 me-2">External Link Button</h5>
                        <div class="style-tooltip">
                            <i class="bi bi-info-circle"></i>
                            <div class="tooltip-content">
                                <p>Offers a link to users as to where they can submit their generated content.</p>
                                <div class="tooltip-image">
                                    <img src="<?php echo asset_path('images/form-options/post-content.png'); ?>"
                                            alt="Dynamic Title Field preview">
                                </div>
                            </div>
                        </div>
                    </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="templateLinkToggle"
                                <?php echo $enableTemplateLink ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="templateLinkToggle">Enable</label>
                        </div>
                    </div>
                    <div class="card-body" id="templateLinkSection" style="<?php echo $enableTemplateLink ? '' : 'display: none;'; ?>">
                        <small class="form-text text-muted d-block mb-2">Offer users a link to post their generated content, you <b>cannot</b> use wildcards.</small>
                        <input type='text' id='templateLink' class='form-control'
                            placeholder='Generally used to offer the user a link as to where to post the generated content'
                            value="<?php echo htmlspecialchars($existingTemplateLink); ?>">
                    </div>
                </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 me-2">Public Directory Listing</h5>
                    <div class="style-tooltip">
                        <i class="bi bi-info-circle"></i>
                        <div class="tooltip-content">
                            <p>Display the form on a public directory listing, which has a list of forms that anyone can view.</p>
                            <div class="tooltip-image">
                                <img src="<?php echo asset_path('images/form-options/post-content.png'); ?>"
                                    alt="Public Directory Listing">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="templatePublicToggle"
                        <?php echo $enablePublicLink ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="templatePublicToggle">Enable</label>
                </div>
            </div>
            <div class="card-body" id="templatePublicSection" style="<?php echo $enablePublicLink ? '' : 'display: none;'; ?>">
                <div class="alert alert-warning mb-3">
                    <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Important:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Your form is <em>already publicly accessible</em> via its direct link without enabling this option.</li>
                        <li>Enabling this option will add your form to a public directory that is accessible by everyone.</li>
                        <li>This directory is searchable and your form will be visible to all users.</li>
                    </ul>
                </div>
                <div class="alert alert-info">
                    <strong><i class="bi bi-info-circle me-2"></i>Note:</strong> After submission, it may take up to 48 hours for your form to appear in the public directory. All submissions are reviewed before being published.
                </div>
            </div>
        </div>
    </div>

    <div id='button-container'>
        <input type="hidden" id="formType" value="<?php echo isset($_GET['type']) ? htmlspecialchars($_GET['type']) : ''; // Retaining for now, though new logic uses formContext ?>">
        <input type="hidden" id="formContext" value="<?php echo isset($_GET['form_context']) ? htmlspecialchars($_GET['form_context']) : ''; ?>">
        <?php if ($editMode && $isOwnForm): ?>
            <input type="hidden" id="editingForm" value="<?php echo htmlspecialchars($_GET['f']); ?>">
            <input type="hidden" id="editMode" value="true">
            <button id='saveFormButton' class='btn btn-primary'>Update Form</button>
        <?php else: ?>
            <button id='saveFormButton' class='btn btn-primary'>Save Form</button>
        <?php endif; ?>
    </div>

    <div id="documentation-link" class="text-center mt-3 mb-3">
        <a href="<?php echo DOCS_URL; ?>" target="_blank" class="btn btn-info">
            <i class="bi bi-book"></i> Documentation
        </a>
    </div>

    <div id="success-message" class="alert alert-success mt-3" style="display: none;"> <?php if ($editMode && $isOwnForm): ?>
            <p>Form updated successfully!</p>
            <a id="shareable-link" class="text-primary" target="_blank"></a>
        <?php else: ?>
            <p>Form saved successfully! Share this link:</p>
            <a id="shareable-link" class="text-primary" target="_blank"></a>
        <?php endif; ?>
        <div class="mt-2">
            <a id="go-to-form-button" class="btn btn-primary" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> Go to Form
            </a>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = $editMode ? 'Edit Form' : 'Form Builder';

// Page-specific settings
$GLOBALS['page_settings'] = [
    'formio_assets' => !$useBasicBuilder, // Only load FormIO assets for the standard builder
];

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/builder.css') .'?v=' . APP_VERSION . '">';
if($useBasicBuilder) {
    $GLOBALS['page_css'] .= '<link rel="stylesheet" href="'. asset_path('css/pages/builder-basic.css') .'?v=' . APP_VERSION . '">';
}

// Add page-specific JavaScript
$existingSchemaJS = $existingSchema ? $existingSchema : 'null'; // Use JS null if no schema
$existingTemplateJS = json_encode($existingTemplate, JSON_UNESCAPED_SLASHES);
$existingTemplateTitleJS = json_encode($existingTemplateTitle, JSON_UNESCAPED_SLASHES);
$existingTemplateLinkJS = json_encode($existingTemplateLink, JSON_UNESCAPED_SLASHES);
$enableTemplateTitleJS = $enableTemplateTitle ? 'true' : 'false';
$enableTemplateLinkJS = $enableTemplateLink ? 'true' : 'false';
$enablePublicLinkJS = $enablePublicLink ? 'true' : 'false';
$existingStyleJS = json_encode($existingFormStyle);
$siteURL = site_url();
$jsonURL = JSON_URL;
$isEditModeJS = $editMode && $isOwnForm ? 'true' : 'false';
$useBasicBuilderJS = $useBasicBuilder ? 'true' : 'false';
$assets_base_path = asset_path('js/');

// Add this inside your PHP file, before the closing body tag
$GLOBALS['page_js_vars'] = <<<JSVARS
let existingFormData = $existingSchemaJS;
let existingFormNamePHP = "$existingFormName";
let existingTemplatePHP = $existingTemplateJS;
let existingTemplateTitlePHP = $existingTemplateTitleJS;
let existingTemplateLinkPHP = $existingTemplateLinkJS;
let enableTemplateTitlePHP = $enableTemplateTitleJS;
let enableTemplateLinkPHP = $enableTemplateLinkJS;
let enablePublicLinkPHP = $enableTemplateLinkJS;
let existingFormStyle = $existingStyleJS;
let siteURL = "$siteURL";
let jsonURL = "$jsonURL";
let isEditMode = $isEditModeJS;
let ASSETS_BASE_PATH = "$assets_base_path";
let useBasicBuilder = $useBasicBuilderJS;
JSVARS;

// Set up JavaScript based on builder mode
if ($useBasicBuilder) { // Check the boolean variable directly
    // Basic builder - load only what's needed
    $GLOBALS['page_javascript'] = '
    <script src="'. asset_path('js/app/custom.no.js') .'?v=' . APP_VERSION . '"></script>

    <script src="'. asset_path('js/app/builder-basic.js') .'?v=' . APP_VERSION . '"></script>
    ';
} else {
    // Standard FormIO builder
    $GLOBALS['page_javascript'] = '
    <script src="'. asset_path('js/components/components.js') .'?v=' . APP_VERSION . '"></script>

    <script src="'. asset_path('js/app/editor.js') .'?v=' . APP_VERSION . '"></script>

    <script src="'. asset_path('js/app/custom.no.js') .'?v=' . APP_VERSION . '"></script>

    <script src="'. asset_path('js/app/builder.js') .'?v=' . APP_VERSION . '"></script>
    ';
}

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';