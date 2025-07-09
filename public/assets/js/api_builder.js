document.addEventListener('DOMContentLoaded', function () {
    const apiFieldsContainer = document.getElementById('apiFieldsContainer');
    const addFieldBtn = document.getElementById('addFieldBtn');
    const apiBuilderForm = document.getElementById('apiBuilderForm');
    const mainBbcodeTemplateInput = document.getElementById('mainBbcodeTemplate');
    const availableWildcardsDisplay = document.getElementById('availableWildcardsDisplay');

    const livePreviewSampleInputsContainer = document.getElementById('livePreviewSampleInputsContainer');
    const livePreviewOutputTextarea = document.getElementById('livePreviewOutput');
    const livePreviewPlaceholderText = livePreviewSampleInputsContainer.querySelector('.placeholder-text');

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
        fieldNameInput.id = `fieldName_${fieldCounter}`; // For label if needed, though labels are static in current HTML
        fieldNameInput.value = '';
        fieldNameInput.addEventListener('input', () => { // Update wildcards and preview on name change
            updateAvailableWildcards();
            throttledUpdateLivePreview();
        });

        const fieldWrapperInput = newField.querySelector('.field-wrapper-input');
        fieldWrapperInput.name = `fields[${fieldCounter-1}][wrapper]`;
        fieldWrapperInput.value = '';
        fieldWrapperInput.addEventListener('input', throttledUpdateLivePreview);

        const multiEntryCheckbox = newField.querySelector('.multi-entry-checkbox');
        multiEntryCheckbox.name = `fields[${fieldCounter-1}][is_multi_entry]`;
        multiEntryCheckbox.id = `multiEntryCheck_${fieldCounter-1}`;
        newField.querySelector('label[for="multiEntryCheck_0"]').htmlFor = `multiEntryCheck_${fieldCounter-1}`;

        const multiEntryWrappersDiv = newField.querySelector('.multi-entry-wrappers');
        multiEntryCheckbox.addEventListener('change', function () {
            multiEntryWrappersDiv.style.display = this.checked ? 'block' : 'none';
            generatePreviewSampleInputs(); // Regenerate preview inputs on type change
            throttledUpdateLivePreview();
        });

        const multiStartWrapperInput = newField.querySelector('.multi-start-wrapper-input');
        multiStartWrapperInput.name = `fields[${fieldCounter-1}][multi_start_wrapper]`;
        multiStartWrapperInput.value = '';
        multiStartWrapperInput.addEventListener('input', throttledUpdateLivePreview);

        const multiEndWrapperInput = newField.querySelector('.multi-end-wrapper-input');
        multiEndWrapperInput.name = `fields[${fieldCounter-1}][multi_end_wrapper]`;
        multiEndWrapperInput.value = '';
        multiEndWrapperInput.addEventListener('input', throttledUpdateLivePreview);

        newField.querySelector('.remove-field-btn').addEventListener('click', function () {
            newField.remove();
            updateAvailableWildcards();
            generatePreviewSampleInputs(); // Regenerate preview inputs
            throttledUpdateLivePreview();
        });

        apiFieldsContainer.appendChild(newField);
        updateAvailableWildcards();
        generatePreviewSampleInputs(); // Regenerate preview inputs
        throttledUpdateLivePreview();
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
                input.placeholder = `Sample for ${fieldName}`;
                input.addEventListener('input', throttledUpdateLivePreview);
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
        input.placeholder = `Sample item for ${fieldName}`;
        input.style.flexGrow = '1';
        input.addEventListener('input', throttledUpdateLivePreview);
        itemDiv.appendChild(input);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.classList.add('btn', 'btn-outline-danger', 'btn-sm', 'ms-1');
        removeBtn.innerHTML = '&times;'; // Using times symbol for remove
        removeBtn.addEventListener('click', () => {
            itemDiv.remove();
            throttledUpdateLivePreview();
        });
        itemDiv.appendChild(removeBtn);
        container.appendChild(itemDiv);
        throttledUpdateLivePreview();
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

    function throttledUpdateLivePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            generatePreviewSampleInputs(); // Ensure sample inputs are up-to-date before previewing
            updateLivePreview();
        }, 300); // 300ms debounce
    }


    // --- Initial Setup ---
    if (addFieldBtn) {
        addFieldBtn.addEventListener('click', () => {
            addField();
        });
    }
    addField(); // Add one initial field on page load
    updateAvailableWildcards();
    generatePreviewSampleInputs();
    updateLivePreview();

    if (mainBbcodeTemplateInput) {
        mainBbcodeTemplateInput.addEventListener('input', throttledUpdateLivePreview);
    }
     document.getElementById('apiName').addEventListener('input', throttledUpdateLivePreview);


    // --- Form Submission ---
    if (apiBuilderForm) {
        apiBuilderForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const apiNameInput = document.getElementById('apiName');
            if (!apiNameInput.value.trim().match(/^[a-zA-Z0-9_]+$/)) {
                alert('API Name is required and can only contain letters, numbers, and underscores.');
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
                alert('Please define at least one field for the API.');
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
                    alert(result.message || 'API Schema saved successfully!');
                } else {
                    alert('Error: ' + (result.error || 'Could not save API Schema.'));
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                alert('An unexpected error occurred while saving. Please check the console.');
            });
        });
    }
});
