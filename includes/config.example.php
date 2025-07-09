<?php
/**
 * reBB - Configuration File
 * 
 * This file contains all the core configuration settings for the reBB application.
 * It defines constants that are used throughout the application to maintain
 * consistency and allow for easy updates.
 * 
 * @package reBB
 * @author booskit-codes
 */

// ╔════════════════════════════════════════╗
// ║            SITE CONFIGURATION          ║
// ╚════════════════════════════════════════╝

// Site identity
define('SITE_NAME',        'reBB');
define('SITE_DESCRIPTION', 'BBCode done differently');

// URLs and paths
define('SITE_URL',         'https://rebb.booskit.dev');
define('DOCS_URL',         'https://rebb.booskit.dev/docs');
define('JSON_URL',         'https://sys.booskit.dev/cdn/serve.php?file=');
define('ASSETS_DIR',       SITE_URL . '/assets');

// External links
define('FOOTER_GITHUB',    'https://github.com/booskit-codes/reBB');

// ╔════════════════════════════════════════╗
// ║       APPLICATION CONFIGURATION        ║
// ╚════════════════════════════════════════╝

// Environment and debugging
define('ENVIRONMENT',      'production');
define('DEBUG_MODE',       ENVIRONMENT === 'development');

// Session settings
define('SESSION_LIFETIME', 86400);    // 24 hours in seconds

// Security and rate limiting
define('MAX_REQUESTS_PER_HOUR', 60);  // Maximum form submissions per hour per IP
define('COOLDOWN_PERIOD',        5);  // Seconds between submissions
define('IP_BLACKLIST',           ['192.0.2.1']);  // Example blacklisted IPs

// Form size limits
define('MAX_SCHEMA_SIZE_GUEST',  1000000);   // 1MB for guests
define('MAX_SCHEMA_SIZE_MEMBER', 10000000);  // 10MB for registered members

// Custom link settings
define('DEFAULT_MAX_UNIQUE_LINKS', 5);       // Maximum number of custom links per user
define('CUSTOM_LINK_MIN_LENGTH',   3);       // Minimum length for custom links
define('CUSTOM_LINK_MAX_LENGTH',   30);      // Maximum length for custom links

// Donations and other site settings
define('ENABLE_AUTH',       true);                             // Enables the authentication system (login page)
define('ENABLE_DONATIONS',  false);                             // Enables the "donations" button and links.
define('ENABLE_JSON_VIEW',  true);                              // Enables the ability to view a form's json contents / copy someone's form.
define('ENABLE_API_SYSTEM',  false);                              // Enables public api calls to the website and people to create their own APIs.
define('ENABLE_ORGANIZATIONS',  false);                              // Enables the ability to create organizations.

// Footer settings
define('FOOTER_TEXT',       'Made with ❤️ by <a href="https://booskit.dev/" target="_blank">booskit</a>');

// Backward compatibility
define('LEGACY_URLS_ENABLED', true);         // Support for legacy URL patterns (form.php, etc.)

// ╔════════════════════════════════════════╗
// ║        NOTIFICATION SETTINGS           ║
// ╚════════════════════════════════════════╝

// Discord webhook for form submission notifications
define('DISCORD_WEBHOOK_URL', '');  // Add your Discord webhook URL here

// ╔════════════════════════════════════════╗
// ║         ANALYTICS CONFIGURATION        ║
// ╚════════════════════════════════════════╝

// Master switch for analytics
define('ENABLE_ANALYTICS', true);

// Analytics feature toggles
define('TRACK_VISITORS',   true);     // Track page views and visitor counts
define('TRACK_COMPONENTS', true);     // Track component usage statistics
define('TRACK_THEMES',     true);     // Track theme selection statistics
define('TRACK_FORM_USAGE', true);     // Track form views and submissions

/**
 * End of configuration
 */