/**
 * Textarea with Cookies Component
 * A textarea that remembers user input using browser cookies
 */
(function() {
  // Get the base TextAreaComponent class from Form.io
  const TextAreaComponent = Formio.Components.components.textarea;

  // Define our component class using ES6 class syntax
  class TextareaCookiesComponent extends TextAreaComponent {
    // Define the builder info as a static getter
    static get builderInfo() {
      return {
        title: 'Textarea (localStorage)',
        group: 'custom',
        icon: 'type',
        weight: 20,
        schema: TextareaCookiesComponent.schema()
      };
    }

    // Define the schema factory method
    static schema() {
      return TextAreaComponent.schema({
        type: 'textareaCookies',
        label: 'Textarea with localStorage',
        key: 'textareaCookies',
        uniqueKey: false,
        saveToLocalStorage: true,
      });
    }

    // Inherit the edit form from the parent
    static editForm() {
      return TextAreaComponent.editForm();
    }

    // Constructor to initialize the component
    constructor(component, options, data) {
      super(component, options, data);
      // Additional initialization can be added here
    }
  }

  // Register the component
  if (window.ComponentRegistry) {
    window.ComponentRegistry.register('textareaCookies', TextareaCookiesComponent);
  } else {
    // Fallback to direct registration if ComponentRegistry is unavailable
    Formio.Components.addComponent('textareaCookies', TextareaCookiesComponent);
  }

  // Make it available globally for debugging and manual usage
  window.TextareaCookiesComponent = TextareaCookiesComponent;
})();