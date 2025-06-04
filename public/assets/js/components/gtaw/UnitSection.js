/**
 * GTAW Unit Section Component
 * A pre-built form section for GTAW unit information with browser cookie support
 */
(function() {
    // Get the base ContainerComponent class from Form.io
    const ContainerComponent = Formio.Components.components.container;
  
    // Define our component class using ES6 class syntax
    class UnitSectionComponent extends ContainerComponent {
      // Define the builder info as a static getter
      static get builderInfo() {
        return {
          title: 'Unit Section (with localStorage)',
          group: 'gtaw',
          icon: 'person',
          weight: 0,
          schema: UnitSectionComponent.schema()
        };
      }
  
      // Define the schema factory method
      static schema() {
        return ContainerComponent.schema({
          label: 'Unit Section',
          type: 'unitSection',
          key: 'unitSection',
          input: true,
          tableView: true,
          components: [
            {
              type: 'fieldset',
              label: 'Unit Information',
              key: 'unitInfo',
              input: false,
              components: [
                {
                  label: 'Unit Columns',
                  columns: [
                    {
                      components: [
                        { 
                          label: 'Callsign', 
                          key: 'callsign', 
                          uniqueKey: true, 
                          type: 'textfield', 
                          input: true, 
                          tableView: true, 
                          saveToLocalStorage: true,
                        }
                      ],
                      width: 4,
                      offset: 0,
                      push: 0,
                      pull: 0,
                      size: 'md',
                      currentWidth: 4
                    },
                    {
                      components: [
                        { 
                          label: 'Name', 
                          key: 'name', 
                          uniqueKey: true, 
                          type: 'textfield', 
                          input: true, 
                          tableView: true, 
                          saveToLocalStorage: true,
                        }
                      ],
                      width: 4,
                      offset: 0,
                      push: 0,
                      pull: 0,
                      size: 'md',
                      currentWidth: 4
                    },
                    {
                      components: [
                        { 
                          label: 'Badge Number', 
                          key: 'badgeNumber', 
                          uniqueKey: true, 
                          type: 'textfield', 
                          input: true, 
                          tableView: true, 
                          saveToLocalStorage: true,
                        }
                      ],
                      size: 'md',
                      width: 4,
                      currentWidth: 4
                    }
                  ],
                  key: 'unitColumns',
                  type: 'columns',
                  input: false,
                  tableView: false
                }
              ]
            }
          ]
        });
      }
  
      // Inherit the edit form from the parent
      static editForm() {
        return ContainerComponent.editForm();
      }
  
      // Constructor to initialize the component
      constructor(component, options, data) {
        super(component, options, data);
        // Additional initialization can be added here
      }
  
      // Add any custom methods or overrides here as needed
    }
  
    // Register the component
    if (window.ComponentRegistry) {
      window.ComponentRegistry.register('unitSection', UnitSectionComponent);
    } else {
      // Fallback to direct registration if ComponentRegistry is unavailable
      Formio.Components.addComponent('unitSection', UnitSectionComponent);
    }
  
    // Make it available globally for debugging and manual usage
    window.UnitSectionComponent = UnitSectionComponent;
  })();