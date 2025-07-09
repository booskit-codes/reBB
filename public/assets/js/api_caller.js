document.addEventListener('DOMContentLoaded', function () {
    const selectApiElement = document.getElementById('selectedApi');
    const apiCallForm = document.getElementById('apiCallForm');
    const apiInputFieldsContainer = document.getElementById('apiInputFieldsContainer');
    const currentApiNameElement = document.getElementById('currentApiName');
    const apiOutputContainer = document.getElementById('apiOutputContainer');
    const generatedBbcodeElement = document.getElementById('generatedBbcode');
    const copyBbcodeBtn = document.getElementById('copyBbcodeBtn');
    const noApiSelectedMessage = document.getElementById('noApiSelectedMessage');

    let currentSelectedApi = null;

    if (selectApiElement) {
        selectApiElement.addEventListener('change', function () {
            currentSelectedApi = this.value;
            if (currentSelectedApi) {
                loadApiFields(currentSelectedApi);
                noApiSelectedMessage.style.display = 'none';
                apiCallForm.style.display = 'block';
                apiOutputContainer.style.display = 'none'; // Hide previous output
                generatedBbcodeElement.value = ''; // Clear previous output
            } else {
                apiInputFieldsContainer.innerHTML = ''; // Clear fields
                currentApiNameElement.textContent = '';
                apiCallForm.style.display = 'none';
                noApiSelectedMessage.style.display = 'block';
                apiOutputContainer.style.display = 'none';
            }
        });
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
                currentApiNameElement.textContent = `API: ${result.schema.api_name.replace(/_/g, ' ')}`;
                apiInputFieldsContainer.innerHTML = ''; // Clear previous fields
                result.schema.fields.forEach(field => {
                    const fieldGroup = document.createElement('div');
                    fieldGroup.classList.add('mb-3');

                    const label = document.createElement('label');
                    label.classList.add('form-label');
                    label.htmlFor = `api_field_${field.name}`;
                    label.textContent = field.name.replace(/_/g, ' ') + ':';

                    const input = document.createElement('input');
                    input.type = 'text'; // For simplicity, all fields are text for now
                    input.classList.add('form-control');
                    input.id = `api_field_${field.name}`;
                    input.name = field.name; // Use field name as input name
                    input.placeholder = `Enter value for ${field.name}`;

                    fieldGroup.appendChild(label);
                    fieldGroup.appendChild(input);
                    apiInputFieldsContainer.appendChild(fieldGroup);
                });
                apiCallForm.style.display = 'block';
            } else {
                alert('Error loading API fields: ' + (result.error || 'Unknown error'));
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

            const formData = new FormData(apiCallForm);
            const fieldValues = {};
            // FormData in this case directly gives field_name: value pairs due to input names
            for (let [name, value] of formData.entries()) {
                fieldValues[name] = value;
            }

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
