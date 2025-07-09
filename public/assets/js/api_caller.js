document.addEventListener('DOMContentLoaded', function () {
    // const selectApiElement = document.getElementById('selectedApi'); // No longer exists
    const apiCallForm = document.getElementById('apiCallForm'); // Might not exist if API load fails
    const apiInputFieldsContainer = document.getElementById('apiInputFieldsContainer'); // Might not exist
    const currentApiNameElement = document.getElementById('currentApiNameLoading'); // Renamed in PHP, might not exist
    const apiOutputContainer = document.getElementById('apiOutputContainer'); // Might not exist
    const generatedBbcodeElement = document.getElementById('generatedBbcode'); // Might not exist
    const copyBbcodeBtn = document.getElementById('copyBbcodeBtn'); // Might not exist
    // const noApiSelectedMessage = document.getElementById('noApiSelectedMessage'); // No longer exists

    let currentSelectedApi = null; // This will store the FULL api_identifier (e.g., api_xxxx)

    // Load API fields if a raw identifier is provided by PHP
    // Ensure `rawApiIdToLoad` is defined by PHP script block before this script runs.
    if (typeof rawApiIdToLoad !== 'undefined' && rawApiIdToLoad && /^[a-f0-9]{16}$/.test(rawApiIdToLoad)) {
        currentSelectedApi = "api_" + rawApiIdToLoad; // Prepend "api_"
        if (apiCallForm && apiInputFieldsContainer) { // Check if form elements are on page
             loadApiFields(currentSelectedApi); // Pass the full identifier
        } else if (!apiCallForm || !apiInputFieldsContainer) {
            // This case implies PHP determined an error before rendering the form,
            // so JS doesn't need to do much other than not erroring out.
            console.log("API Caller form elements not found, likely due to PHP error message.");
        }
    } else {
        // If apiIdentifierToLoad is not set, PHP should have displayed an error message.
        // We can hide the form if it somehow got rendered without an API.
        if(apiCallForm) apiCallForm.style.display = 'none';
    }

    function addMultiEntryInputItem(container, fieldName, isFirstItem = false) {
        const itemDiv = document.createElement('div');
        itemDiv.classList.add('d-flex', 'mb-1', 'multi-entry-item');

        const input = document.createElement('input');
        input.type = 'text';
        input.classList.add('form-control', 'form-control-sm', 'api-field-value'); // Shared class for value retrieval
        input.name = `${fieldName}[]`; // Use array notation for name
        input.placeholder = `Enter item for ${fieldName.replace(/_/g, ' ')}`;
        input.style.flexGrow = '1';
        itemDiv.appendChild(input);

        if (!isFirstItem) { // Only add remove button for non-first items, or always add if you prefer
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.classList.add('btn', 'btn-outline-danger', 'btn-sm', 'ms-1');
            removeBtn.innerHTML = '<i class="bi bi-trash"></i>';
            removeBtn.title = "Remove item";
            removeBtn.addEventListener('click', () => {
                itemDiv.remove();
            });
            itemDiv.appendChild(removeBtn);
        } else {
            // Optionally add a non-functional placeholder or a different button for the first item
            // Or simply ensure the first item cannot be removed this way easily
        }
        container.appendChild(itemDiv);
    }

    function loadApiFields(apiName) {
        fetch(ajaxUrl, { // ajaxUrl should be defined globally in the PHP template
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                type: 'get_api_schema_details',
                api_name: apiName
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success && result.schema && result.schema.fields) {
                // Update API display name, using the 'display_name' from schema
                if(currentApiNameElement) {
                    currentApiNameElement.textContent = `API: ${result.schema.display_name || result.schema.api_identifier.replace(/_/g, ' ')}`;
                }
                apiInputFieldsContainer.innerHTML = ''; // Clear "Loading fields..." message or previous fields

                result.schema.fields.forEach((field, index) => {
                    const fieldGroup = document.createElement('div');
                    fieldGroup.classList.add('mb-3', 'api-field-group');
                    fieldGroup.dataset.fieldName = field.name;
                    fieldGroup.dataset.isMultiEntry = field.is_multi_entry || false;

                    const label = document.createElement('label');
                    label.classList.add('form-label');
                    // No htmlFor here as ID might be complex for multi-entry
                    label.textContent = field.name.replace(/_/g, ' ') + ':';
                    fieldGroup.appendChild(label);

                    if (field.is_multi_entry) {
                        const itemsContainer = document.createElement('div');
                        itemsContainer.classList.add('multi-entry-items-container');
                        itemsContainer.id = `multi-items-container-${field.name}`;

                        addMultiEntryInputItem(itemsContainer, field.name, true); // Add one initial item

                        const addItemBtn = document.createElement('button');
                        addItemBtn.type = 'button';
                        addItemBtn.classList.add('btn', 'btn-outline-success', 'btn-sm', 'mt-1');
                        addItemBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Add Item';
                        addItemBtn.addEventListener('click', () => addMultiEntryInputItem(itemsContainer, field.name));

                        fieldGroup.appendChild(itemsContainer);
                        fieldGroup.appendChild(addItemBtn);
                    } else {
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.classList.add('form-control', 'api-field-value');
                        input.id = `api_field_${field.name}`;
                        input.name = field.name;
                        input.placeholder = `Enter value for ${field.name}`;
                        fieldGroup.appendChild(input);
                    }
                    apiInputFieldsContainer.appendChild(fieldGroup);
                });
                apiCallForm.style.display = 'block';
            } else {
                alert('Error loading API fields: ' + (result.error || 'Unknown error'));
                currentApiNameElement.textContent = '';
                apiInputFieldsContainer.innerHTML = '<p class="text-danger">Could not load fields for this API.</p>';
                apiCallForm.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('AJAX Error loading API fields:', error);
            alert('An unexpected error occurred while loading API fields.');
            apiInputFieldsContainer.innerHTML = '<p class="text-danger">Could not load fields due to a network or server error.</p>';
            apiCallForm.style.display = 'none';
        });
    }

    if (apiCallForm) {
        apiCallForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!currentSelectedApi) {
                alert('Please select an API first.');
                return;
            }

            const fieldValues = {};
            apiInputFieldsContainer.querySelectorAll('.api-field-group').forEach(group => {
                const fieldName = group.dataset.fieldName;
                const isMulti = group.dataset.isMultiEntry === 'true';

                if (isMulti) {
                    fieldValues[fieldName] = [];
                    group.querySelectorAll('.multi-entry-item .api-field-value').forEach(input => {
                        if (input.value.trim() !== '') { // Only add non-empty values
                            fieldValues[fieldName].push(input.value);
                        }
                    });
                } else {
                    const input = group.querySelector('.api-field-value');
                    if (input) {
                        fieldValues[fieldName] = input.value;
                    }
                }
            });

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    type: 'generate_api_bbcode',
                    api_name: currentSelectedApi,
                    field_values: fieldValues
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    generatedBbcodeElement.value = result.bbcode;
                    apiOutputContainer.style.display = 'block';
                } else {
                    alert('Error generating BBCode: ' + (result.error || 'Unknown error'));
                    generatedBbcodeElement.value = 'Error: Could not generate BBCode.';
                    apiOutputContainer.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('AJAX Error generating BBCode:', error);
                alert('An unexpected error occurred while generating BBCode.');
                generatedBbcodeElement.value = 'Error: Could not generate BBCode due to a network or server error.';
                apiOutputContainer.style.display = 'block';
            });
        });
    }

    if (copyBbcodeBtn) {
        copyBbcodeBtn.addEventListener('click', function () {
            if (generatedBbcodeElement.value) {
                generatedBbcodeElement.select();
                document.execCommand('copy'); // Deprecated, but common and simple
                // Consider navigator.clipboard.writeText for modern browsers if preferred
                alert('BBCode copied to clipboard!');
            } else {
                alert('Nothing to copy.');
            }
        });
    }
});
