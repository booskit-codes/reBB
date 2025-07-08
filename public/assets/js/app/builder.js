(function() {
    const builderElement = document.getElementById('builder');
    const saveButton = document.getElementById('saveFormButton');
    const templateInput = document.getElementById('formTemplate');
    const wildcardList = document.getElementById('wildcard-list');
    const formNameInput = document.getElementById('formName');
    let componentCounter = 0;
    let builderInstance;
    let predefinedKeys = new Set();

    // Add this to the beginning of your builder.js file, after the initial variable declarations
    document.addEventListener('DOMContentLoaded', function() {
        // Hook into Form.io's component edit form
        Formio.Components.components.component.editForm = function() {
        let editForm = Formio.Components.components.component.baseEditForm();
        
        // Find the API tab in the edit form
        const apiTab = editForm.components.find(tab => tab.key === 'api');
        
        if (apiTab && apiTab.components) {
            // Add the Preserve Key checkbox right after the "Property Name" (key) field
            const keyIndex = apiTab.components.findIndex(comp => comp.key === 'key');
            
            if (keyIndex !== -1) {
            // Create the Preserve Key checkbox component
            const preserveKeyCheckbox = {
                type: 'checkbox',
                input: true,
                key: 'uniqueKey',
                weight: apiTab.components[keyIndex].weight + 1, // Position right after the key field
                label: 'Preserve Key',
                tooltip: 'When enabled, the key will not be regenerated when the label changes.',
                customClass: 'preserve-key-checkbox',
                defaultValue: false
            };
            
            // Insert our new checkbox after the key field
            apiTab.components.splice(keyIndex + 1, 0, preserveKeyCheckbox);
            }
        }
        
        return editForm;
        };
        
        // Now the uniqueKey property will be part of the component settings
        // and will be properly saved and loaded with the form
    });

    function collectKeys(schema, keysSet) {
        // Only add key to predefinedKeys if uniqueKey is true
        if (schema.key && schema.uniqueKey === true) {
            keysSet.add(schema.key);
        }
        if (schema.components) {
            schema.components.forEach(c => collectKeys(c, keysSet));
        }
        if (schema.columns) {
            schema.columns.forEach(col => {
                if (col.components) {
                    col.components.forEach(c => collectKeys(c, keysSet));
                }
            });
        }
    }

    // Initial Builder Options
    let builderOptions = {
        builder: {
            resource: false,
            premium: false,
            advanced: {
                weight: 20,
                components: {
                    email: false,
                    url: true,
                    phoneNumber: false,
                    tags: false,
                    address: false,
                    currency: false,
                    survey: true,
                    signature: false
                }
            },
            basic: {
                weight: 10,
                components: {
                    password: false,
                    number: false
                }
            },
            data: {
                components: {
                    container: false,
                    datamap: false,
                    editgrid: false
                }
            }
        }
    };

    // Function to initialize the builder with the ComponentRegistry
    function initializeBuilderWithRegistry() {
        try {
            // Get component groups from registry and add them to builder options
            Object.assign(builderOptions.builder, ComponentRegistry.getBuilderGroups());
            
            console.log('Initializing builder with ComponentRegistry...');
            
            // Check if the editor extensions are loaded
            if (typeof window.editorExtensionsLoaded === 'undefined') {
                // Set a flag that our editor extensions have been applied
                // This would be set by editor.js, but we'll check here just in case
                console.log('Editor extensions not detected, they should be loaded via editor.js');
            }
            
            // Initialize the Form.io builder
            Formio.builder(builderElement, existingFormData, builderOptions)
                .then(builder => {
                    builderInstance = builder;
                    initializeBuilder();
                    
                    if (existingFormNamePHP) {
                        formNameInput.value = existingFormNamePHP;
                    }
                    if (existingTemplatePHP) {
                        templateInput.value = existingTemplatePHP;
                    }
                })
                .catch(error => {
                    console.error('Error initializing builder:', error);
                    alert('Error initializing form builder. Please check the console for details.');
                });

            console.log('Form.io initialized!');
        } catch (error) {
            console.error('Error setting up form builder:', error);
            alert('There was an error setting up the form builder. Please check the console for details.');
        }
    }

    // Determine the asset base path based on the current script
    function getAssetBasePath() {
        // Try to get the base path from a global variable first
        if (typeof ASSETS_BASE_PATH !== 'undefined') {
            return ASSETS_BASE_PATH;
        }
        
        // Default to a relative path
        return '';
    }

    // Initialize the form builder
    function startBuilderInitialization() {
        // If ComponentRegistry is available, use it to load components
        if (window.ComponentRegistry) {
            const basePath = getAssetBasePath();
            
            // Initialize the registry with the base path
            ComponentRegistry.init(basePath)
                .then(() => {
                    // When components are loaded, initialize the builder
                    initializeBuilderWithRegistry();
                })
                .catch(error => {
                    console.error('Error initializing ComponentRegistry:', error);
                    alert('Error loading components. Please check the console for details.');
                });
        } else {
            console.error('ComponentRegistry not available, cannot initialize builder');
            alert('Component system not available. Please ensure ComponentRegistry.js is loaded before builder.js.');
        }
    }

    // Start the initialization process
    startBuilderInitialization();

    function initializeBuilder() {
        builderInstance.on('change', updateWildcards);
        builderInstance.on('updateComponent', handleComponentUpdate);
        saveButton.addEventListener('click', saveForm);
        setupTemplateListener();
        updateWildcards();
        
        // Set up template title toggle
        const templateTitleToggle = document.getElementById('templateTitleToggle');
        const templateTitleSection = document.getElementById('templateTitleSection');
        const templateTitle = document.getElementById('templateTitle');
        
        if (templateTitleToggle) {
            // Initialize from PHP value
            if (typeof enableTemplateTitlePHP !== 'undefined') {
                templateTitleToggle.checked = enableTemplateTitlePHP;
                templateTitleSection.style.display = enableTemplateTitlePHP ? 'block' : 'none';
            }
            
            // Set up toggle event listener
            templateTitleToggle.addEventListener('change', function() {
                templateTitleSection.style.display = this.checked ? 'block' : 'none';
                // Clear the field if disabled
                if (!this.checked) {
                    templateTitle.value = '';
                }
            });
        }

        // Set up Public Directory toggle
        const templatePublicToggle = document.getElementById('templatePublicToggle');
        const templatePublicSection = document.getElementById('templatePublicSection');

        if (templatePublicToggle) {
            // Initialize from PHP value
            if (typeof enablePublicLinkPHP !== 'undefined') {
                templatePublicToggle.checked = enablePublicLinkPHP;
                templatePublicSection.style.display = enablePublicLinkPHP ? 'block' : 'none';
            }
            
            // Set up toggle event listener
            templatePublicToggle.addEventListener('change', function() {
                templatePublicSection.style.display = this.checked ? 'block' : 'none';
            });
        }
        
        // Set up template link toggle
        const templateLinkToggle = document.getElementById('templateLinkToggle');
        const templateLinkSection = document.getElementById('templateLinkSection');
        const templateLink = document.getElementById('templateLink');
        
        if (templateLinkToggle) {
            // Initialize from PHP value
            if (typeof enableTemplateLinkPHP !== 'undefined') {
                templateLinkToggle.checked = enableTemplateLinkPHP;
                templateLinkSection.style.display = enableTemplateLinkPHP ? 'block' : 'none';
            }
            
            // Set up toggle event listener
            templateLinkToggle.addEventListener('change', function() {
                templateLinkSection.style.display = this.checked ? 'block' : 'none';
                // Clear the field if disabled
                if (!this.checked) {
                    templateLink.value = '';
                }
            });
        }
        
        // Initialize values from PHP if they exist
        if (typeof existingTemplateTitlePHP !== 'undefined' && existingTemplateTitlePHP) {
            templateTitle.value = existingTemplateTitlePHP;
        }
        
        if (typeof existingTemplateLinkPHP !== 'undefined' && existingTemplateLinkPHP) {
            templateLink.value = existingTemplateLinkPHP;
        }
        
        // Create a help text element that we'll show/hide as needed
        const wildcardContainer = document.getElementById('wildcard-container');
        const helpText = document.createElement('div');
        helpText.id = 'dataset-help-text';
        helpText.className = 'alert alert-info mt-2';
        helpText.style.display = 'none'; // Hidden by default
        helpText.innerHTML = '<small><strong>Note:</strong> Dataset wildcards (highlighted in red) must be included in your template before saving. They define where repeating content will appear.</small>';
        wildcardContainer.appendChild(helpText);
        enhanceBuilderInit();
    }

    function generateUniqueId() {
        componentCounter++;
        return `${componentCounter}${Math.random().toString(36).substring(2, 6).toUpperCase()}`;
    }

    function generateKey(label, component) {
        if (component.type === 'button' && component.action === 'submit') return component.key || '';
        const cleanLabel = label.toUpperCase().replace(/ /g, '_').replace(/[^A-Z0-9_]/g, '');
        return `${cleanLabel}_${generateUniqueId()}`;
    }

    function updateWildcards() {
        const components = builderInstance?.form?.components || [];
        const wildcardArray = components.flatMap(getComponentKeys);
        
        // Check if there are any dataset wildcards
        const hasDatasetWildcards = wildcardArray.some(key => 
            key.startsWith('@START_') || key.startsWith('@END_')
        );
        
        // Show or hide help text based on whether we have dataset wildcards
        const helpText = document.getElementById('dataset-help-text');
        if (helpText) {
            helpText.style.display = hasDatasetWildcards ? 'block' : 'none';
        }
        
        wildcardList.innerHTML = wildcardArray
            .map(key => {
                const wildcardText = `{${key}}`;
                const isDatasetWildcard = key.startsWith('@START_') || key.startsWith('@END_');
                const datasetClass = isDatasetWildcard ? 'wildcard-dataset' : '';
                const dangerClass = isDatasetWildcard ? 'wildcard-danger' : '';
                
                return `
                    <span class="wildcard ${datasetClass} ${dangerClass}" data-wildcard="${key}">
                        ${wildcardText}
                        <button class="copy-btn" data-clipboard="${wildcardText}" title="Copy to clipboard">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </span>
                `;
            })
            .join('');
        
        // Add event listeners to the copy buttons
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const textToCopy = this.getAttribute('data-clipboard');
                
                // Try to copy text to clipboard using the best available method
                copyToClipboard(textToCopy)
                    .then(() => {
                        // Show feedback that copy was successful
                        const originalIcon = this.innerHTML;
                        this.innerHTML = '<i class="bi bi-check-lg"></i>';
                        setTimeout(() => {
                            this.innerHTML = originalIcon;
                        }, 1000);
                    })
                    .catch(err => {
                        console.error('Copy failed:', err);
                        alert('Could not copy to clipboard. Please try selecting and copying manually.');
                    });
            });
        });
        
        // Check for used wildcards in the template
        checkUsedWildcards();
    }
    
    /**
     * Cross-browser clipboard copy function
     * @param {string} text - The text to copy
     * @returns {Promise} - Resolves when copy is successful
     */
    function copyToClipboard(text) {
        // Modern approach - Clipboard API (not supported in all browsers)
        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
        }
        
        // Fallback approach - Create a temporary element and copy from it
        return new Promise((resolve, reject) => {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';  // Avoid scrolling to bottom
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                
                // Execute the copy command
                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);
                
                if (successful) {
                    resolve();
                } else {
                    reject(new Error('Unable to copy'));
                }
            } catch (err) {
                reject(err);
            }
        });
    }

    function getComponentKeys(component) {
        if (component.type === 'button' && component.action === 'submit') return [];
        let keys = [];
    
        if (component.type === 'datagrid' && component.key) {
            keys.push(`@START_${component.key}@`, `@END_${component.key}@`);
        }
    
        // Special handling for survey components
        if (component.type === 'survey' && component.key) {
            // Generate wildcards for each question in the survey
            if (component.questions && Array.isArray(component.questions)) {
                component.questions.forEach((question, index) => {
                    if (question.value) {
                        // Create a more concise wildcard format
                        // Extract first few characters of the value for readability
                        const shortValue = question.value.substring(0, 15).replace(/[^A-Za-z0-9]/g, '');
                        // Add question number (1-based index)
                        keys.push(`${component.key}_${shortValue}${index + 1}`);
                    }
                });
            }
        }
    
        // NEW: Special handling for select components to generate option-specific wildcards
        if (component.type === 'selectboxes' && component.key) {
            console.log("pass")
            // Check if the component has data.values (options array)
            if (component.values && component.values && Array.isArray(component.values)) {
                component.values.forEach(option => {
                    if (option.value) {
                        // Create a wildcard for each option
                        // Format: componentKey_optionValue (sanitized)
                        const optionKey = String(option.value).substring(0, 15).replace(/[^A-Za-z0-9_]/g, '');
                        keys.push(`${component.key}_${optionKey}`);
                    }
                });
            }
        }
    
        // Define layout components that should NOT generate wildcards
        const layoutComponents = [
            'columns', 'column', 'fieldset', 'panel', 'table', 
            'tabs', 'tab', 'well', 'html', 'content',
            'data', 'container', 'editgrid'
        ];
    
        // Handle standard input components
        if (['textfield', 'textarea', 'checkbox', 'select', 'radio', 'hidden', 'datetime', 'day', 'time'].includes(component.type)) {
            if (component.key) {
                keys.push(component.key);
            }
        }
        
        // For any other component with a key, include it as well
        // This ensures any custom component added later will still work
        // BUT exclude layout components that don't represent user input
        if (component.key && !keys.includes(component.key) && 
            component.type !== 'button' && 
            component.type !== 'datagrid' &&
            component.type !== 'survey' &&
            !layoutComponents.includes(component.type)) {
            keys.push(component.key);
        }
    
        // Recursively process components and columns, regardless of the current component's type
        if (component.components) keys.push(...component.components.flatMap(getComponentKeys));
        if (component.columns) keys.push(...component.columns.flatMap(col => col.components?.flatMap(getComponentKeys) || []));
    
        return keys;
    }

    // Function to check if all dataset wildcards are used
    function checkAllDatasetWildcardsUsed() {
        const datasetWildcards = document.querySelectorAll('.wildcard-dataset');
        
        // If there are no dataset wildcards, return true (no blocking)
        if (datasetWildcards.length === 0) return true;
        
        // Check if all dataset wildcards are used
        for (const wildcard of datasetWildcards) {
            if (!wildcard.classList.contains('wildcard-used')) {
                return false;
            }
        }
        
        return true;
    }

    // Update the save button state based on dataset wildcards
    function updateSaveButtonState() {
        const allDatasetWildcardsUsed = checkAllDatasetWildcardsUsed();
        saveButton.disabled = !allDatasetWildcardsUsed;
        
        if (!allDatasetWildcardsUsed) {
            saveButton.title = 'All dataset wildcards must be used in the template before saving';
        } else {
            saveButton.title = 'Save form';
        }
    }

    // Function to check which wildcards are being used in the template
    function checkUsedWildcards() {
        const templateText = templateInput.value;
        const wildcardElements = document.querySelectorAll('.wildcard');
        
        wildcardElements.forEach(element => {
            const key = element.getAttribute('data-wildcard');
            const wildcardPattern = new RegExp(`\\{${key}\\}`, 'g');
            
            if (wildcardPattern.test(templateText)) {
                // Wildcard is being used in the template
                element.classList.add('wildcard-used');
                if(element.classList.contains('wildcard-dataset')) {
                    element.classList.remove('wildcard-danger');
                }
            } else {
                // Wildcard is not being used
                element.classList.remove('wildcard-used');
                if(element.classList.contains('wildcard-dataset')) {
                    element.classList.add('wildcard-danger');
                }
            }
        });
        
        // Update save button state
        updateSaveButtonState();
    }

    // Add event listener to the template textarea to detect changes
    function setupTemplateListener() {
        templateInput.addEventListener('input', checkUsedWildcards);
        // Initial check for save button state
        setTimeout(updateSaveButtonState, 500);
    }

    function handleComponentUpdate(component) {
        if (component.action === 'submit') return;
        
        // Skip key generation for components with uniqueKey=true
        if (component.uniqueKey === true) {
            return;
        }
        
        // Also check if component has uniqueKey=false explicitly set (force key generation)
        if (component.uniqueKey === false) {
            const newKey = generateKey(component.label, component);
            if (component.key !== newKey) {
                component.key = newKey;
                builderInstance.redraw();
                setTimeout(updateWildcards, 0);
            }
            return;
        }
        
        // Check predefined keys for backward compatibility
        if (predefinedKeys.has(component.key)) {
            return; // Skip key generation for predefined components
        }

        const newKey = generateKey(component.label, component);
        if (component.key !== newKey) {
            component.key = newKey;
            builderInstance.redraw();
            setTimeout(updateWildcards, 0);
        }

        function trackComponentUsage(componentType) {
            fetch('ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'analytics',
                    action: 'track_component',
                    component: componentType
                })
            }).catch(err => console.warn('Analytics error:', err));
        }

        trackComponentUsage(component.type);
    }

    async function saveForm() {
        const formSchema = builderInstance?.form;
        if (!formSchema) return alert('No form schema found');

        const formName = formNameInput.value;
        const formStyle = document.querySelector('input[name="formStyle"]:checked').value;
        
        // Get template title and link values with toggle states
        const templateTitleToggle = document.getElementById('templateTitleToggle');
        const templateLinkToggle = document.getElementById('templateLinkToggle');
        const templatePublicToggle = document.getElementById('templatePublicToggle');
        const enableTemplateTitle = templateTitleToggle ? templateTitleToggle.checked : false;
        const enableTemplateLink = templateLinkToggle ? templateLinkToggle.checked : false;
        const enablePublicLink = templatePublicToggle ? templatePublicToggle.checked : false;
        
        const templateTitle = enableTemplateTitle ? document.getElementById('templateTitle').value || '' : '';
        const templateLink = enableTemplateLink ? document.getElementById('templateLink').value || '' : '';

        // Track theme usage
        fetch('ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'analytics',
                action: 'track_theme',
                theme: formStyle
            })
        }).catch(err => console.warn('Analytics error:', err));

        // Check if this is an edit operation
        isEditMode = typeof isEditMode !== 'undefined' && isEditMode;
        const editingForm = document.getElementById('editingForm')?.value;
        const formWidth = parseInt(document.getElementById('formWidthInput').value, 10);
        const formType = document.getElementById('formType')?.value || ''; // Read the formType

        try {
            const response = await fetch('ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'schema',
                    schema: formSchema,
                    template: templateInput.value,
                    templateTitle: templateTitle,
                    templateLink: templateLink,
                    enableTemplateTitle: enableTemplateTitle,
                    enableTemplateLink: enableTemplateLink,
                    enablePublicLink: enablePublicLink,
                    formName: formName,
                    formWidth: formWidth,
                    formStyle: formStyle,
                    editMode: isEditMode,
                    editingForm: editingForm,
                    builder: 'standard',
                    form_type: formType // Add form_type to the payload
                })
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.error);

            // Extract form ID - either the one from the update or a new one
            let formId = data.formId || data.filename.replace('forms/', '').replace('_schema.json', '');
            
            // Make sure we don't include any directory paths in the formId
            if (formId.includes('/') || formId.includes('\\')) {
                formId = formId.split(/[\/\\]/).pop();
            }
            
            const shareUrl = siteURL + `form?f=${formId}`;
            document.getElementById('shareable-link').textContent = shareUrl;
            document.getElementById('shareable-link').href = shareUrl;
            document.getElementById('go-to-form-button').href = shareUrl;
            document.getElementById('success-message').style.display = 'block';
        } catch (error) {
            console.error('Save error:', error);
            alert('Error saving form: ' + (error.message || 'Unknown error'));
        }
    }

    // Add an initialization function to set the form style when editing an existing form
    function initFormStyle() {
        if (typeof existingFormStyle !== 'undefined' && existingFormStyle) {
            const styleRadio = document.querySelector(`input[name="formStyle"][value="${existingFormStyle}"]`);
            if (styleRadio) {
                styleRadio.checked = true;
            }
        }
    }

    // Call this after the form builder is initialized
    function enhanceBuilderInit() {
        // Set up the form style radio buttons for better UX
        document.querySelectorAll('.style-option').forEach(option => {
            option.addEventListener('click', function(e) {
                // If clicking on the div but not directly on the radio button, check the radio button
                if (e.target.type !== 'radio') {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        // Uncheck all other radio buttons first
                        document.querySelectorAll('input[name="formStyle"]').forEach(r => {
                            r.checked = false;
                        });
                        
                        // Check this radio button
                        radio.checked = true;
                        
                        // Trigger a change event to ensure any listeners are notified
                        radio.dispatchEvent(new Event('change'));
                    }
                }
            });
        });
        // Initialize form style from existing data
        initFormStyle();
    }

    document.getElementById('formWidthSlider').addEventListener('input', function() {
        document.getElementById('formWidthInput').value = this.value;
    });
    
    document.getElementById('formWidthInput').addEventListener('input', function() {
        document.getElementById('formWidthSlider').value = this.value;
    });
})();