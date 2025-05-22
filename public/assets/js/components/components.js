/**
 * reBB Component Registry
 * A system for registering and organizing Form.io components with dynamic loading
 */
var ComponentRegistry = (function() {
    // Private variables
    var _components = {};
    var _loadedScripts = [];
    var _loading = false;
    var _onComponentsLoadedCallbacks = [];
    
    // Component groups for the builder
    var _groups = {
      gtaw: {
        title: 'Pre-Built (GTAW)',
        weight: 0,
        default: false,
        components: {}
      },
      custom: {
        title: 'Custom',
        weight: 100,
        default: false,
        components: {}
      }
    };
  
    // Component definitions - define your components here
    var _componentDefinitions = [
      {
        name: 'UnitSection',
        type: 'unitSection',
        group: 'gtaw',
        path: 'components/gtaw/unitSection.js'
      },
      {
        name: 'TextfieldCookies',
        type: 'textfieldCookies',
        group: 'custom',
        path: 'components/custom/TextfieldCookies.js'
      },
      {
        name: 'TextareaCookies',
        type: 'textareaCookies',
        group: 'custom',
        path: 'components/custom/TextareaCookies.js'
      },
      {
        name: 'ToggleSwitch',
        type: 'toggleSwitch',
        group: 'custom',
        path: 'components/custom/ToggleSwitch.js'
      },
      {
        name: 'EnhancedCheckbox',
        type: 'enhancedCheckbox',
        group: 'custom',
        path: 'components/custom/enhancedCheckbox.js'
      },
      {
        name: 'DistrictDropdown',
        type: 'districtDropdown',
        group: 'gtaw',
        path: 'components/gtaw/districtDropdown.js'
      },
      {
        name: 'StreetDropdown',
        type: 'streetDropdown',
        group: 'gtaw',
        path: 'components/gtaw/streetDropdown.js'
      },
      {
        name: 'PenalCodeSelector',
        type: 'penalCodeSelector',
        group: 'gtaw',
        path: 'components/gtaw/penalCodeSelector.js'
      }
      // Add additional components here
    ];
    
    /**
     * Load a JavaScript file dynamically
     * @param {string} src - Path to the JavaScript file
     * @returns {Promise} - Resolves when script is loaded
     */
    function _loadScript(src) {
      return new Promise(function(resolve, reject) {
        // Check if script is already loaded
        if (_loadedScripts.includes(src)) {
          resolve(src + ' already loaded');
          return;
        }
        
        // Create script element
        var script = document.createElement('script');
        script.src = src;
        script.async = true;
        
        // Set up load event
        script.onload = function() {
          _loadedScripts.push(src);
          resolve(src + ' loaded successfully');
        };
        
        // Set up error event
        script.onerror = function() {
          reject(new Error('Failed to load script: ' + src));
        };
        
        // Add to document
        document.head.appendChild(script);
      });
    }
    
    /**
     * Load all component scripts defined in _componentDefinitions
     * @param {string} basePath - Base path prefix for all component paths
     * @returns {Promise} - Resolves when all components are loaded
     */
    function _loadAllComponents(basePath) {
      _loading = true;
      
      // Get the asset path or use the default (relative to the current path)
      var path = basePath || '';
      
      // Add version to avoid caching if available
      var version = window.APP_VERSION ? '?v=' + window.APP_VERSION : '';
      
      var loadPromises = _componentDefinitions.map(function(component) {
        return _loadScript(path + component.path + version)
          .catch(function(error) {
            console.error('Error loading component:', component.name, error);
            return error; // Continue loading other components even if one fails
          });
      });
      
      return Promise.all(loadPromises)
        .then(function(results) {
          _loading = false;
          console.log('Component loading results:', results);
          
          // Execute any callbacks waiting for components to load
          _onComponentsLoadedCallbacks.forEach(function(callback) {
            callback();
          });
          
          _onComponentsLoadedCallbacks = []; // Clear the callback queue
          
          return results;
        });
    }
    
    // Public API
    return {
      /**
       * Register a component with Form.io and the registry
       * @param {string} type - The component type
       * @param {Object} component - The component class
       * @returns {boolean} - Success status
       */
      register: function(type, component) {
        if (!Formio) {
          console.error('Formio is not loaded, cannot register component:', type);
          return false;
        }
        
        try {
          // Register with Form.io
          Formio.Components.addComponent(type, component);
          
          // Store in our registry
          _components[type] = component;
          return true;
        } catch (error) {
          console.error('Error registering component:', type, error);
          return false;
        }
      },
      
      /**
       * Get all component groups for the builder
       * @returns {Object} - Component groups configuration
       */
      getBuilderGroups: function() {
        return _groups;
      },
      
      /**
       * Get a registered component by type
       * @param {string} type - Component type
       * @returns {Object|null} - The component class or null if not found
       */
      getComponent: function(type) {
        return _components[type] || null;
      },
      
      /**
       * Get all registered components
       * @returns {Object} - All registered components
       */
      getAllComponents: function() {
        return _components;
      },
      
      /**
       * Check if a component type is registered
       * @param {string} type - Component type
       * @returns {boolean} - True if registered
       */
      hasComponent: function(type) {
        return !!_components[type];
      },
      
      /**
       * Add a new component definition
       * @param {Object} definition - Component definition
       * @returns {void}
       */
      addComponentDefinition: function(definition) {
        _componentDefinitions.push(definition);
      },
      
      /**
       * Get all component definitions
       * @returns {Array} - Array of component definitions
       */
      getComponentDefinitions: function() {
        return _componentDefinitions;
      },
      
      /**
       * Load all component scripts
       * @param {string} basePath - Base path for component scripts
       * @returns {Promise} - Resolves when all components are loaded
       */
      loadComponents: function(basePath) {
        return _loadAllComponents(basePath);
      },
      
      /**
       * Execute callback when all components are loaded
       * @param {Function} callback - Callback function
       * @returns {void}
       */
      onComponentsLoaded: function(callback) {
        if (!_loading && _loadedScripts.length === _componentDefinitions.length) {
          // If already loaded, execute callback immediately
          callback();
        } else {
          // Otherwise, queue callback for when loading finishes
          _onComponentsLoadedCallbacks.push(callback);
        }
      },
      
      /**
       * Initialize the registry and load components
       * @param {string} basePath - Base path for component scripts
       * @returns {Promise} - Resolves when initialized
       */
      init: function(basePath) {
        console.log('Component Registry initializing...');
        return this.loadComponents(basePath)
          .then(function() {
            console.log('Component Registry initialized');
            return _groups;
          });
      }
    };
  })();
  
  // Make the registry available globally
  window.ComponentRegistry = ComponentRegistry;