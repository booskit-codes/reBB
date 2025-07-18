document.addEventListener('DOMContentLoaded', function () {
    const apiFieldsContainer = document.getElementById('apiFieldsContainer');
    const addFieldBtn = document.getElementById('addFieldBtn');
    const apiBuilderForm = document.getElementById('apiBuilderForm');
    const mainBbcodeTemplateInput = document.getElementById('mainBbcodeTemplate');
    const availableWildcardsDisplay = document.getElementById('availableWildcardsDisplay');
    const alertsContainer = document.getElementById('apiBuilderAlertsContainer');

    const livePreviewSampleInputsContainer = document.getElementById('livePreviewSampleInputsContainer');
    const livePreviewOutputTextarea = document.getElementById('livePreviewOutput');
    const livePreviewPlaceholderText = livePreviewSampleInputsContainer ? livePreviewSampleInputsContainer.querySelector('.placeholder-text') : null;

    let fieldCounter = 0;
    let debounceTimer;

    // --- Field Management ---
    function addField() {
        fieldCounter++;
        const template = apiFieldsContainer.querySelector('.api-field-template');
        const newField = template.cloneNode(true);

        newField.classList.remove('api-field-template');
        newField.style.display = 'block';

        newField.querySelector('.field-number').textContent = fieldCounter;

        const fieldNameInput = newField.querySelector('.field-name-input');
        fieldNameInput.name = `fields[${fieldCounter-1}][name]`;
        fieldNameInput.id = `fieldName_${fieldCounter}`;
        fieldNameInput.value = '';
        fieldNameInput.addEventListener('input', () => {
            updateAvailableWildcards();
            // When field name changes, sample input labels need to update.
            // Simplest is to regenerate sample inputs, then update preview.
            debouncedGenerateSampleInputsAndUpdatePreview();
        });

        const fieldWrapperInput = newField.querySelector('.field-wrapper-input');
        fieldWrapperInput.name = `fields[${fieldCounter-1}][wrapper]`;
        fieldWrapperInput.value = '';
        fieldWrapperInput.addEventListener('input', debouncedUpdateOnlyPreview); // Only update preview text

        const multiEntryCheckbox = newField.querySelector('.multi-entry-checkbox');
        multiEntryCheckbox.name = `fields[${fieldCounter-1}][is_multi_entry]`;
        multiEntryCheckbox.id = `multiEntryCheck_${fieldCounter-1}`;
        newField.querySelector('label[for="multiEntryCheck_0"]').htmlFor = `multiEntryCheck_${fieldCounter-1}`;

        const multiEntryWrappersDiv = newField.querySelector('.multi-entry-wrappers');
        multiEntryCheckbox.addEventListener('change', function () {
            multiEntryWrappersDiv.style.display = this.checked ? 'block' : 'none';
            // Structure changed, so regenerate sample inputs then update preview
            generatePreviewSampleInputs();
            updateLivePreview(); // Immediate update after structure change
        });

        const multiStartWrapperInput = newField.querySelector('.multi-start-wrapper-input');
        multiStartWrapperInput.name = `fields[${fieldCounter-1}][multi_start_wrapper]`;
        multiStartWrapperInput.value = '';
        multiStartWrapperInput.addEventListener('input', debouncedUpdateOnlyPreview); // Only update preview text

        const multiEndWrapperInput = newField.querySelector('.multi-end-wrapper-input');
        multiEndWrapperInput.name = `fields[${fieldCounter-1}][multi_end_wrapper]`;
        multiEndWrapperInput.value = '';
        multiEndWrapperInput.addEventListener('input', debouncedUpdateOnlyPreview); // Only update preview text

        newField.querySelector('.remove-field-btn').addEventListener('click', function () {
            newField.remove();
            updateAvailableWildcards();
            // Structure changed
            generatePreviewSampleInputs();
            updateLivePreview(); // Immediate update
        });

        apiFieldsContainer.appendChild(newField);
        updateAvailableWildcards();
        // Structure changed
        generatePreviewSampleInputs();
        updateLivePreview(); // Immediate update
    }

    // --- Wildcard Display ---
    function updateAvailableWildcards() {
        const fieldNameInputs = apiFieldsContainer.querySelectorAll('.field-name-input:not(.api-field-template .field-name-input)');
        let wildcards = [];
        fieldNameInputs.forEach(input => {
            const name = input.value.trim().replace(/\s+/g, '_'); // Sanitize for typical wildcard usage
            if (name) {
                wildcards.push(`<code>{${name}}</code>`);
            }
        });
        availableWildcardsDisplay.innerHTML = wildcards.length ? wildcards.join(', ') : '(none yet)';
    }

    // --- Live Preview Sample Inputs Generation ---
    function generatePreviewSampleInputs() {
        if (!livePreviewSampleInputsContainer) return;
        livePreviewSampleInputsContainer.innerHTML = ''; // Clear previous sample inputs
        let hasFields = false;

        const fieldDivs = apiFieldsContainer.querySelectorAll('div.border.rounded:not(.api-field-template)');
        fieldDivs.forEach((fieldDiv, index) => {
            hasFields = true;
            const fieldNameInput = fieldDiv.querySelector('.field-name-input');
            const fieldName = fieldNameInput.value.trim() || `field_${index + 1}`;
            const isMultiEntry = fieldDiv.querySelector('.multi-entry-checkbox').checked;

            const group = document.createElement('div');
            group.classList.add('mb-3', 'sample-input-group');
            group.dataset.fieldName = fieldName; // Store original name for preview logic

            const label = document.createElement('label');
            label.classList.add('form-label', 'form-label-sm');
            label.textContent = `${fieldName.replace(/_/g, ' ')} Sample Value(s):`;
            group.appendChild(label);

            if (isMultiEntry) {
                group.dataset.isMulti = "true";
                const itemsContainer = document.createElement('div');
                itemsContainer.classList.add('multi-sample-items-container');
                group.appendChild(itemsContainer);

                addSampleInputToMultiEntry(itemsContainer, fieldName); // Add first item

                const addItemBtn = document.createElement('button');
                addItemBtn.type = 'button';
                addItemBtn.classList.add('btn', 'btn-outline-secondary', 'btn-sm', 'mt-1');
                addItemBtn.textContent = 'Add Sample Item';
                addItemBtn.addEventListener('click', () => addSampleInputToMultiEntry(itemsContainer, fieldName));
                group.appendChild(addItemBtn);

            } else {
                group.dataset.isMulti = "false";
                const input = document.createElement('input');
                input.type = 'text';
                input.classList.add('form-control', 'form-control-sm', 'live-preview-sample-value');
                input.placeholder = `Sample for ${fieldName.replace(/_/g, ' ')}`;
                input.addEventListener('input', debouncedUpdateOnlyPreview); // Only update preview text
                group.appendChild(input);
            }
            livePreviewSampleInputsContainer.appendChild(group);
        });

        if (!hasFields && livePreviewPlaceholderText) {
             livePreviewSampleInputsContainer.appendChild(livePreviewPlaceholderText.cloneNode(true));
        }
    }

    function addSampleInputToMultiEntry(container, fieldName) {
        const itemDiv = document.createElement('div');
        itemDiv.classList.add('d-flex', 'mb-1', 'multi-sample-item');

        const input = document.createElement('input');
        input.type = 'text';
        input.classList.add('form-control', 'form-control-sm', 'live-preview-sample-value');
        input.placeholder = `Sample item for ${fieldName.replace(/_/g, ' ')}`;
        input.style.flexGrow = '1';
        input.addEventListener('input', debouncedUpdateOnlyPreview); // Only update preview text
        itemDiv.appendChild(input);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.classList.add('btn', 'btn-outline-danger', 'btn-sm', 'ms-1');
        removeBtn.innerHTML = '&times;';
        removeBtn.addEventListener('click', () => {
            itemDiv.remove();
            updateLivePreview(); // Immediate update after removing a sample item
        });
        itemDiv.appendChild(removeBtn);
        container.appendChild(itemDiv);
        updateLivePreview(); // Immediate update after adding a sample item
    }


    // --- Client-Side Preview Generation Logic ---
    function updateLivePreview() {
        if (!livePreviewOutputTextarea || !mainBbcodeTemplateInput) return;

        let mainTemplate = mainBbcodeTemplateInput.value;
        const apiName = document.getElementById('apiName').value.trim() || "your_api";
        mainTemplate = mainTemplate.replace(/{api_name}/g, apiName);

        const definedFields = [];
        apiFieldsContainer.querySelectorAll('div.border.rounded:not(.api-field-template)').forEach(fieldDiv => {
            const nameInput = fieldDiv.querySelector('.field-name-input');
            if (nameInput && nameInput.value.trim()) {
                definedFields.push({
                    name: nameInput.value.trim().replace(/\s+/g, '_'),
                    individualWrapper: fieldDiv.querySelector('.field-wrapper-input').value,
                    isMultiEntry: fieldDiv.querySelector('.multi-entry-checkbox').checked,
                    multiStartWrapper: fieldDiv.querySelector('.multi-start-wrapper-input').value,
                    multiEndWrapper: fieldDiv.querySelector('.multi-end-wrapper-input').value
                });
            }
        });

        definedFields.forEach(field => {
            const placeholder = new RegExp(`{${field.name}}`, 'g');
            let replacement = '';

            const sampleGroup = livePreviewSampleInputsContainer.querySelector(`.sample-input-group[data-field-name="${field.name.replace(/\s+/g, '_')}"]`);
            if (!sampleGroup) { // If sample input group not found for a field, replace placeholder with empty string or placeholder itself.
                mainTemplate = mainTemplate.replace(placeholder, ''); // Or keep placeholder: `{${field.name}}`
                return; // continue to next field
            }

            if (field.isMultiEntry) {
                let itemsContent = '';
                sampleGroup.querySelectorAll('.multi-sample-item .live-preview-sample-value').forEach(input => {
                    const itemValue = input.value;
                    itemsContent += field.individualWrapper.replace(/{field_value}/g, itemValue);
                });
                replacement = field.multiStartWrapper + itemsContent + field.multiEndWrapper;
            } else {
                const sampleInput = sampleGroup.querySelector('.live-preview-sample-value');
                const sampleValue = sampleInput ? sampleInput.value : '';
                replacement = field.individualWrapper.replace(/{field_value}/g, sampleValue);
            }
            mainTemplate = mainTemplate.replace(placeholder, replacement);
        });

        // Replace any remaining (undefined) field placeholders with empty string
        mainTemplate = mainTemplate.replace(/{[a-zA-Z0-9_]+}/g, '');


        livePreviewOutputTextarea.value = mainTemplate;
    }

    // Debounce wrapper for functions that only update the preview text
    function debouncedUpdateOnlyPreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updateLivePreview, 300);
    }

    // Debounce wrapper for functions that regenerate sample inputs and then update preview text
    function debouncedGenerateSampleInputsAndUpdatePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            generatePreviewSampleInputs();
            updateLivePreview();
        }, 300);
    }


    // --- Initial Setup ---
    if (addFieldBtn) {
        addFieldBtn.addEventListener('click', () => {
            // addField itself calls generatePreviewSampleInputs and updateLivePreview
            addField();
        });
    }
    addField(); // Add one initial field on page load which also triggers first preview

    if (mainBbcodeTemplateInput) {
        mainBbcodeTemplateInput.addEventListener('input', debouncedUpdateOnlyPreview);
    }
    document.getElementById('apiName').addEventListener('input', debouncedUpdateOnlyPreview);

    // --- Alert Function ---
    function showApiBuilderAlert(message, type = 'info', dismissible = true, autoDismissDelay = 0) {
        if (!alertsContainer) return;

        alertsContainer.innerHTML = ''; // Clear previous alerts

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} fade show`;
        alertDiv.setAttribute('role', 'alert');

        if (dismissible) {
            alertDiv.classList.add('alert-dismissible');
            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'btn-close';
            closeButton.setAttribute('data-bs-dismiss', 'alert');
            closeButton.setAttribute('aria-label', 'Close');
            alertDiv.appendChild(closeButton);
        }

        // To handle multi-line messages from \n
        message.split('\n').forEach((line, index) => {
            if (index > 0) alertDiv.appendChild(document.createElement('br'));
            alertDiv.appendChild(document.createTextNode(line));
        });

        alertsContainer.appendChild(alertDiv);

        if (autoDismissDelay > 0) {
            setTimeout(() => {
                // Check if alert still exists before trying to close with Bootstrap's JS
                if (alertDiv && alertDiv.parentNode) {
                     // Use Bootstrap's alert instance to close, if available and loaded
                    const bsAlert = bootstrap.Alert.getInstance(alertDiv);
                    if (bsAlert) {
                        bsAlert.close();
                    } else {
                        // Fallback if Bootstrap JS for Alert not loaded or alert already removed
                        alertDiv.remove();
                    }
                }
            }, autoDismissDelay);
        }
    }


    // --- Form Submission ---
    if (apiBuilderForm) {
        apiBuilderForm.addEventListener('submit', function (event) {
            event.preventDefault();
            showApiBuilderAlert(''); // Clear any previous alerts by passing empty message

            const apiNameInput = document.getElementById('apiName');
            if (!apiNameInput.value.trim()) { // Simplified check, backend does regex
                showApiBuilderAlert('API Name (Display Name) is required.', 'danger');
                apiNameInput.focus();
                return;
            }
            // Backend will validate format: /^[a-zA-Z0-9_]+$/
            // For now, we only check if it's empty on client side.
            // Or, we can replicate the regex:
            if (!apiNameInput.value.trim().match(/^[a-zA-Z0-9_ ]+$/)) {
                 showApiBuilderAlert('API Name can only contain letters, numbers, spaces, and underscores.', 'danger');
                 apiNameInput.focus();
                 return;
            }

            const payload = {
                type: 'save_api_schema',
                api_name: apiNameInput.value.trim(),
                main_bbcode_template: mainBbcodeTemplateInput.value,
                fields: []
            };

            let hasAtLeastOneField = false;
            apiFieldsContainer.querySelectorAll('div.border.rounded:not(.api-field-template)').forEach(fieldDiv => {
                const nameInput = fieldDiv.querySelector('.field-name-input');
                if (nameInput && nameInput.value.trim()) {
                    hasAtLeastOneField = true;
                    payload.fields.push({
                        name: nameInput.value.trim().replace(/\s+/g, '_'),
                        individual_wrapper: fieldDiv.querySelector('.field-wrapper-input').value,
                        is_multi_entry: fieldDiv.querySelector('.multi-entry-checkbox').checked,
                        multi_start_wrapper: fieldDiv.querySelector('.multi-start-wrapper-input').value,
                        multi_end_wrapper: fieldDiv.querySelector('.multi-end-wrapper-input').value
                    });
                }
            });

            if (!hasAtLeastOneField) {
                showApiBuilderAlert('Please define at least one field for the API.', 'danger');
                // Potentially focus the "Add Field" button or the last field name input
                const lastFieldNameInput = apiFieldsContainer.querySelector('div.border.rounded:not(.api-field-template):last-child .field-name-input');
                if (lastFieldNameInput) {
                    lastFieldNameInput.focus();
                } else {
                    addFieldBtn.focus();
                }
                return;
            }

            fetch(ajaxUrl, { // ajaxUrl is defined in global scope by PHP
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    let successMessage = (result.message || 'API Schema saved successfully!') +
                                       `\nDisplay Name: ${result.display_name}` +
                                       `\nAPI Identifier: ${result.api_identifier}`;

                    // Attempt to get base URL for api_caller link construction
                    // This assumes site_url() functionality or a global base URL variable might be available or needed.
                    // For simplicity, using a relative path. A robust solution might need `siteUrl` from PHP like in api_caller.php.
                    let apiCallerBaseUrl = './api_caller'; // Simple relative path
                    try {
                        // If a global site_url function or variable is exposed from PHP to JS, use it.
                        // For example, if PHP set <script>var baseSiteUrl = "<?php echo site_url(); ?>";</script>
                        if (typeof baseSiteUrl !== 'undefined') {
                            apiCallerBaseUrl = baseSiteUrl + (baseSiteUrl.endsWith('/') ? '' : '/') + 'api_caller';
                        } else {
                             // Fallback if PHP did not provide a global var.
                             // Constructing a relative URL assuming api_builder and api_caller are in the same directory.
                             // Or, if your routing handles 'api_caller' directly from root:
                             const currentPath = window.location.pathname;
                             const pathSegments = currentPath.split('/');
                             pathSegments.pop(); // remove current file/page
                             // if (pathSegments.length > 0 && pathSegments[pathSegments.length -1] === 'content') {
                             //    pathSegments.pop(); // if it's inside a /content/ directory, go up one more
                             // }
                             // This is a guess, a proper site_url JS equivalent would be better.
                             // For now, using a simpler relative URL that might work depending on server setup.
                             apiCallerBaseUrl = 'api_caller';
                        }
                    } catch (e) { /* ignore if baseSiteUrl is not defined */ }

                    const callerUrl = `${apiCallerBaseUrl}?api=${result.api_identifier.replace(/^api_/, '')}`; // Use raw hex for URL
                    successMessage += `\n\nAccess it at: ${callerUrl}`;

                    showApiBuilderAlert(successMessage, 'success', true, 7000); // Auto-dismiss after 7 seconds
                } else {
                    showApiBuilderAlert('Error: ' + (result.error || 'Could not save API Schema.'), 'danger', true);
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                showApiBuilderAlert('An unexpected error occurred while saving. Please check the console.', 'danger', true);
            });
        });
    }
});
