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
define('FOOTER_GITHUB',    'https://github.com/booskit-codes/reBB');

// Directory structure
define('ASSETS_DIR',       SITE_URL . '/assets');

// ╔════════════════════════════════════════╗
// ║       APPLICATION CONFIGURATION        ║
// ╚════════════════════════════════════════╝

// Environment: 'development' or 'production'
define('ENVIRONMENT',      'production');

// Session Lifetime
define('SESSION_LIFETIME', 86400);

// Form Builder Submission settings
define('MAX_REQUESTS_PER_HOUR', 60);    // Maximum form submissions per hour per IP
define('COOLDOWN_PERIOD', 5);           // Seconds between submissions
define('MAX_SCHEMA_SIZE_GUEST', 1000000);       // 1MB by default
define('MAX_SCHEMA_SIZE_MEMBER', 10000000);     // 10MB by default
define('IP_BLACKLIST', ['192.0.2.1']);  // Example blacklisted IPs (replace with actual ones)

// Custom Shareable Links settings
define('DEFAULT_MAX_UNIQUE_LINKS', 5);  // Default maximum number of custom links per user
define('CUSTOM_LINK_MIN_LENGTH', 3);    // Minimum length for custom links
define('CUSTOM_LINK_MAX_LENGTH', 30);   // Maximum length for custom links

// Routing configuration
define('LEGACY_URLS_ENABLED', true);  // Set to false to disable legacy URL support (form.php, etc.)

// Feature flags
define('DEBUG_MODE',         ENVIRONMENT === 'development');

// ╔════════════════════════════════════════╗
// ║         ANALYTICS CONFIGURATION        ║
// ╚════════════════════════════════════════╝

// Enable or disable the analytics system globally
define('ENABLE_ANALYTICS',    true);

// Configure what to track
define('TRACK_VISITORS',      true);    // Track page views and visitor counts
define('TRACK_COMPONENTS',    true);    // Track component usage statistics
define('TRACK_THEMES',        true);    // Track theme selection statistics
define('TRACK_FORM_USAGE',    true);    // Track form views and submissions

/**
 * End of configuration
 */