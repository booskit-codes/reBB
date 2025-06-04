/**
 * Textfield with Cookies Component
 * A textfield that remembers user input using browser cookies
 */
(function() {
  // Get the base TextFieldComponent class from Form.io
  const TextFieldComponent = Formio.Components.components.textfield;

  // Define our component class using ES6 class syntax
  class TextfieldCookiesComponent extends TextFieldComponent {
    // Define the builder info as a static getter
    static get builderInfo() {
      return {
        title: 'Textfield (localStorage)',
        group: 'custom',
        icon: 'terminal',
        weight: 10,
        schema: TextfieldCookiesComponent.schema()
      };
    }

    // Define the schema factory method
    static schema() {
      return TextFieldComponent.schema({
        type: 'textfieldCookies',
        label: 'Textfield with localStorage',
        key: 'textfieldCookies',
        saveToLocalStorage: true,
      });
    }

    // Inherit the edit form from the parent
    static editForm() {
      return TextFieldComponent.editForm();
    }

    // Constructor to initialize the component
    constructor(component, options, data) {
      super(component, options, data);
      // Additional initialization can be added here
    }

    // Optional: Override render method if needed
    /*
    render(element) {
      // Custom rendering logic, if needed
      return super.render(element);
    }
    */

    // Optional: Override attach method if needed
    /*
    attach(element) {
      const superAttach = super.attach(element);
      // Additional attachment functionality
      return superAttach;
    }
    */
  }

  // Register the component
  if (window.ComponentRegistry) {
    window.ComponentRegistry.register('textfieldCookies', TextfieldCookiesComponent);
  } else {
    // Fallback to direct registration if ComponentRegistry is unavailable
    Formio.Components.addComponent('textfieldCookies', TextfieldCookiesComponent);
  }

  // Make it available globally for debugging and manual usage
  window.TextfieldCookiesComponent = TextfieldCookiesComponent;
})();