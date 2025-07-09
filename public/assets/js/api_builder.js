document.addEventListener('DOMContentLoaded', function () {
    const apiFieldsContainer = document.getElementById('apiFieldsContainer');
    const addFieldBtn = document.getElementById('addFieldBtn');
    const apiBuilderForm = document.getElementById('apiBuilderForm');
    let fieldCounter = 0;

    // Function to add a new field
    function addField() {
        fieldCounter++;
        const template = apiFieldsContainer.querySelector('.api-field-template');
        const newField = template.cloneNode(true);

        newField.classList.remove('api-field-template');
        newField.style.display = 'block'; // Make it visible

        // Update IDs and names to be unique
        newField.querySelector('.field-number').textContent = fieldCounter;

        const fieldNameInput = newField.querySelector('.field-name-input');
        fieldNameInput.id = `fieldName_${fieldCounter}`;
        fieldNameInput.name = `fields[${fieldCounter-1}][name]`;
        fieldNameInput.placeholder = `e.g., field_name_${fieldCounter}`;
        fieldNameInput.value = ''; // Clear any template value

        const fieldWrapperInput = newField.querySelector('.field-wrapper-input');
        fieldWrapperInput.id = `fieldWrapper_${fieldCounter}`;
        fieldWrapperInput.name = `fields[${fieldCounter-1}][wrapper]`;
        fieldWrapperInput.value = ''; // Clear any template value

        // Update labels
        newField.querySelector(`label[for='fieldName_1']`).htmlFor = `fieldName_${fieldCounter}`;
        newField.querySelector(`label[for='fieldWrapper_1']`).htmlFor = `fieldWrapper_${fieldCounter}`;


        // Add event listener for the remove button on the new field
        newField.querySelector('.remove-field-btn').addEventListener('click', function () {
            newField.remove();
            // Optionally, re-number fields if necessary, though backend should handle array indexing
        });

        apiFieldsContainer.appendChild(newField);
    }

    // Add initial field
    addField();

    // Event listener for the "Add Field" button
    if (addFieldBtn) {
        addFieldBtn.addEventListener('click', addField);
    }

    // Handle form submission (basic placeholder for now, will be expanded in a later step)
    if (apiBuilderForm) {
        apiBuilderForm.addEventListener('submit', function (event) {
            event.preventDefault(); // Prevent default form submission for now

            // Basic validation for API Name
            const apiNameInput = document.getElementById('apiName');
            if (!apiNameInput.value.match(/^[a-zA-Z0-9_]+$/)) {
                alert('API Name can only contain letters, numbers, and underscores.');
                apiNameInput.focus();
                return;
            }

            // TODO: In a later step, gather all form data and send via AJAX
            const formData = new FormData(apiBuilderForm);
            const payload = {
                type: 'save_api_schema',
                api_name: formData.get('api_name'),
                overall_wrapper: formData.get('overall_wrapper'),
                fields: []
            };

            // Consolidate fields - FormData can be tricky with complex array names
            // So we iterate through the field containers themselves
            const fieldElements = apiFieldsContainer.querySelectorAll('.api-field-template:not([style*="display: none"]), .api-field-template.show'); // Select visible fields

            // If no template is found (e.g. if it was removed or display none is not set as expected), try another way
            // Correctly select all cloned field containers that are not the template itself
            let actualFields = apiFieldsContainer.querySelectorAll('div.border.rounded:not(.api-field-template[style*="display: none"]):not(.api-field-template)');


            actualFields.forEach(fieldDiv => {
                // Ensure we are not processing the template if it somehow got included
                if (fieldDiv.classList.contains('api-field-template') && fieldDiv.style.display === 'none') {
                    return;
                }
                const nameInput = fieldDiv.querySelector('input[name*="[name]"]');
                const wrapperInput = fieldDiv.querySelector('textarea[name*="[wrapper]"]');
                if (nameInput && nameInput.value.trim() !== '') {
                    payload.fields.push({
                        name: nameInput.value.trim(),
                        wrapper: wrapperInput ? wrapperInput.value : '{field_value}'
                    });
                }
            });

            if (payload.fields.length === 0) {
                alert('Please add at least one valid field to the API.');
                return;
            }

            // Perform AJAX request
            fetch('ajax', { // Assuming 'ajax' is correctly routed to content/ajax.php
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' // Common header for AJAX requests
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message || 'API Schema saved successfully!');
                    // Optionally, redirect or clear form:
                    // apiBuilderForm.reset();
                    // while(apiFieldsContainer.children.length > 1) { // Keep template
                    //    if(!apiFieldsContainer.lastChild.classList.contains('api-field-template')) {
                    //        apiFieldsContainer.removeChild(apiFieldsContainer.lastChild);
                    //    }
                    // }
                    // fieldCounter = 0;
                    // addField(); // Add one fresh field
                } else {
                    alert('Error: ' + (result.error || 'Could not save API Schema.'));
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                alert('An unexpected error occurred. Please check the console.');
            });
        });
    }
});
