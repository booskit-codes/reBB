/**
 * editor.js - Custom extensions for Form.io component editor
 * 
 * This file modifies the Form.io component editor to:
 * 1. Add a "Preserve Key" checkbox in the API tab
 * 2. Add a "Disable wildcard generation" checkbox in the API tab
 * 3. Add an "Autogrammar" option to the Text Case radio group
 */

(function() {
    // Store the original editForm functions before we override them
    const originalComponentEditForm = Formio.Components.components.component.editForm;
    window.editorExtensionsLoaded = "init";
    
    // Override the base component editForm
    Formio.Components.components.component.editForm = function() {
        // Get the original edit form configuration
        const editForm = originalComponentEditForm.call(this);
        
        // PART 1: Add "Preserve Key" checkbox to the API tab
        // Find the api tab in the edit form
        const apiTab = editForm.components.find(tab => 
            tab.key === 'tabs' && 
            tab.components.find(c => c.key === 'api')
        );
        
        if (apiTab) {
            // Get the actual API tab panel
            const apiPanel = apiTab.components.find(c => c.key === 'api');
            
            if (apiPanel && apiPanel.components) {
                // Find the key field in the API tab to position our checkbox after it
                const keyFieldIndex = apiPanel.components.findIndex(c => c.key === 'key');
                
                if (keyFieldIndex !== -1) {
                    // Create our Preserve Key checkbox
                    const preserveKeyCheckbox = {
                        type: 'checkbox',
                        input: true,
                        key: 'uniqueKey',
                        label: 'Preserve Key',
                        tooltip: 'When enabled, the key will not be regenerated when the label changes.',
                        weight: apiPanel.components[keyFieldIndex].weight + 1, // Position right after the key field
                        defaultValue: false
                    };
                    
                    // Insert the checkbox after the key field
                    apiPanel.components.splice(keyFieldIndex + 1, 0, preserveKeyCheckbox);
                    
                    // Create the Disable Wildcard checkbox
                    const disableWildcardCheckbox = {
                        type: 'checkbox',
                        input: true,
                        key: 'disableWildcard',
                        label: 'Disable wildcard generation',
                        tooltip: 'When enabled, this component will not generate wildcards in the template system.',
                        weight: apiPanel.components[keyFieldIndex].weight + 2, // Position after preserve key checkbox
                        defaultValue: false
                    };
                    
                    // Insert the disable wildcard checkbox after the preserve key checkbox
                    apiPanel.components.splice(keyFieldIndex + 2, 0, disableWildcardCheckbox);

                    // Create the Save to Local Storage checkbox
                    const saveToLocalStorageCheckbox = {
                        type: 'checkbox',
                        input: true,
                        key: 'saveToLocalStorage',
                        label: 'Save to Local Storage',
                        tooltip: 'When enabled, the component\'s value will be saved to local storage using the component\'s key.',
                        weight: apiPanel.components[keyFieldIndex].weight + 3, // Position after disable wildcard checkbox
                        defaultValue: false
                    };

                    // Insert the save to local storage checkbox after the disable wildcard checkbox
                    apiPanel.components.splice(keyFieldIndex + 3, 0, saveToLocalStorageCheckbox);
                }
            }
        }
        
        return editForm;
    };
    
    // Apply these changes to all component types that have an edit form
    Object.keys(Formio.Components.components).forEach(type => {
        const component = Formio.Components.components[type];
        
        // Skip the base component as we've already modified it
        if (component !== Formio.Components.components.component && component.editForm) {
            // Store the original editForm for this specific component type
            const originalEditForm = component.editForm;
            
            // Override the editForm method
            component.editForm = function() {
                // Call the original editForm method first
                const editForm = originalEditForm.call(this);
                
                // Now apply our custom modifications
                
                // Find the tabs component
                const tabsComponent = editForm.components.find(c => c.key === 'tabs');
                
                if (tabsComponent) {
                    // Find the API tab
                    const apiTab = tabsComponent.components.find(c => c.key === 'api');
                    
                    if (apiTab && apiTab.components) {
                        // Find the key field
                        const keyFieldIndex = apiTab.components.findIndex(c => c.key === 'key');
                        
                        if (keyFieldIndex !== -1) {
                            // Check if Preserve Key already exists
                            const preserveKeyExists = apiTab.components.some(c => c.key === 'uniqueKey');
                            const disableWildcardExists = apiTab.components.some(c => c.key === 'disableWildcard');
                            const saveToLocalStorageExists = apiTab.components.some(c => c.key === 'saveToLocalStorage');
                            
                            if (!preserveKeyExists) {
                                // Add the Preserve Key checkbox
                                const preserveKeyCheckbox = {
                                    type: 'checkbox',
                                    input: true,
                                    key: 'uniqueKey',
                                    label: 'Preserve Key',
                                    tooltip: 'When enabled, the key will not be regenerated when the label changes.',
                                    weight: apiTab.components[keyFieldIndex].weight + 1,
                                    defaultValue: false
                                };
                                
                                // Insert after the key field
                                apiTab.components.splice(keyFieldIndex + 1, 0, preserveKeyCheckbox);
                            }
                            
                            if (!disableWildcardExists) {
                                // Add the Disable Wildcard checkbox (after Preserve Key)
                                const disableWildcardCheckbox = {
                                    type: 'checkbox',
                                    input: true,
                                    key: 'disableWildcard',
                                    label: 'Disable wildcard generation',
                                    tooltip: 'When enabled, this component will not generate wildcards in the template system.',
                                    weight: apiTab.components[keyFieldIndex].weight + 2,
                                    defaultValue: false
                                };
                                
                                // Insert after the preserve key checkbox
                                const preserveKeyIndex = apiTab.components.findIndex(c => c.key === 'uniqueKey');
                                const insertIndex = preserveKeyIndex !== -1 ? preserveKeyIndex + 1 : keyFieldIndex + 1;
                                apiTab.components.splice(insertIndex, 0, disableWildcardCheckbox);
                            }

                            if (!saveToLocalStorageExists) {
                                // Add the Save to Local Storage checkbox (after Disable Wildcard)
                                const saveToLocalStorageCheckbox = {
                                    type: 'checkbox',
                                    input: true,
                                    key: 'saveToLocalStorage',
                                    label: 'Save to Local Storage',
                                    tooltip: 'When enabled, the component\'s value will be saved to local storage using the component\'s key.',
                                    weight: apiTab.components[keyFieldIndex].weight + 3,
                                    defaultValue: false
                                };

                                // Insert after the disable wildcard checkbox
                                const disableWildcardIndex = apiTab.components.findIndex(c => c.key === 'disableWildcard');
                                const insertIndexSave = disableWildcardIndex !== -1 ? disableWildcardIndex + 1 : (preserveKeyIndex !== -1 ? preserveKeyIndex + 2 : keyFieldIndex + 2);
                                apiTab.components.splice(insertIndexSave, 0, saveToLocalStorageCheckbox);
                            }
                        }
                    }
                    
                    // MODIFY THE TEXT CASE RADIO GROUP FOR TEXT FIELDS AND TEXTAREAS
                    if (type === 'textfield' || type === 'textarea') {
                        // Find the data tab
                        const dataTab = tabsComponent.components.find(c => c.key === 'data');
                        
                        if (dataTab && dataTab.components) {
                            // Find the 'case' radio component (text case options)
                            const caseComponentIndex = dataTab.components.findIndex(c => c.key === 'case');
                            
                            if (caseComponentIndex !== -1) {
                                const caseComponent = dataTab.components[caseComponentIndex];
                                
                                // Check if the autogrammar option already exists
                                const hasAutogrammarOption = caseComponent.values && 
                                                            caseComponent.values.some(v => v.value === 'autogrammar');
                                
                                if (!hasAutogrammarOption && caseComponent.values) {
                                    // Add the Autogrammar option to the existing radio values
                                    caseComponent.values.push({
                                        label: 'Autogrammar (First letter capitalized)',
                                        value: 'autogrammar'
                                    });
                                }
                            }
                        }
                    }
                }
                
                return editForm;
            };
        }
    });
    
    console.log('Form.io component editor successfully extended with custom features');
})();