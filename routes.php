<?php
/**
 * reBB - Route Definitions
 *
 * This file defines all routes for the application in a logical, grouped manner.
 */

// ===================================
// Legacy URL Support
// ===================================
// Map old file-based URLs to the new route structure
legacy('index.php', '');
legacy('form.php', 'form');
legacy('builder.php', 'builder');
legacy('documentation.php', 'docs');
legacy('edit.php', 'edit');
legacy('list.php', 'list');
legacy('directory.php', 'directory');


// ===================================
// Authentication Routes
// ===================================
// Login page
if(ENABLE_AUTH) {
    any('/login', function() {
        view('auth/login');
    });
}

// Logout action
any('/logout', function() {
    auth()->logout();
    redirect('');
});

// ===================================
// Public-facing Routes
// ===================================
// Home page
get('/', function() {
    view('front-page');
});

// ===================================
// Form Routes - For viewing and using forms
// ===================================
// Form listing page
get('/form', function() {
    view('form');
});

// View specific form by ID
get('/form/:id', function($params) {
    $_GET['f'] = $params['id']; // Store ID in GET param for backward compatibility
    view('form');
});

// ===================================
// Public Directory - For viewing and using lists
// ===================================
// List
get('/directory', function() {
    view('public_directory');
});

// ===================================
// List Routes - For viewing and using lists
// ===================================
// List
get('/list', function() {
    view('list');
});

// ===================================
// Custom Shareable Links Route
// ===================================
// Redirect custom links to the actual form
get('/u', function() {
    // Get the custom link
    $customLink = isset($_GET['f']) ? $_GET['f'] : '';
    
    if (empty($customLink)) {
        // No custom link provided, redirect to homepage
        redirect('');
        return;
    }
    
    // Look up the custom link in the database
    $dbPath = ROOT_DIR . '/db';
    $linkStore = new \SleekDB\Store('custom_links', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Find the custom link
    $linkData = $linkStore->findOneBy([['custom_link', '=', $customLink]]);
    
    if ($linkData) {
        // Link found, redirect to the actual form
        redirect('form?f=' . $linkData['form_id']);
    } else {
        // Link not found, show 404 error
        http_response_code(404);
        view('errors/404');
    }
});

// ===================================
// Builder Routes - For creating and editing forms
// ===================================
// Form builder - create new form
get('/builder', function() {
    view('builder');
});

// Form builder - edit existing form
get('/builder/:id', function($params) {
    $_GET['f'] = $params['id']; // Store ID in GET param for backward compatibility
    view('builder');
});

// ===================================
// Donation Route
// ===================================
if(ENABLE_DONATIONS) {
    any('/donate', function() {
        view('donate');
    });
}

// ===================================
// Edit Form Routes - For authenticated editing of forms
// ===================================
// Edit form - authenticated edit of existing form with ownership check
// Change this route from a path parameter to a query parameter
if(ENABLE_AUTH) {
    get('/edit', function() {
        // This now handles /edit?f=formId format
        if (!isset($_GET['f']) || empty($_GET['f'])) {
            // No form ID provided
            http_response_code(400);
            echo '<div class="alert alert-danger">No form ID provided. Please specify a form to edit.</div>';
            return;
        }
        
        // Require authentication
        auth()->requireAuth('login');
        
        // Check if the form exists and the user has permission to edit it
        $formId = $_GET['f'];
        $filename = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
        
        if (file_exists($filename)) {
            $formData = json_decode(file_get_contents($filename), true);
            $currentUser = auth()->getUser();
            
            // Check if form has a createdBy field and if it matches the current user
            // Admin users can edit any form
            if (isset($formData['createdBy']) && $formData['createdBy'] === $currentUser['_id'] || 
                $currentUser['role'] === 'admin') {
                // User has permission to edit, redirect to builder with edit mode
                $_GET['edit_mode'] = 'true';
                view('builder');
            } else {
                // User doesn't have permission to edit this form
                http_response_code(403);
                echo '<div class="alert alert-danger">You do not have permission to edit this form.</div>';
            }
        } else {
            // Form not found
            http_response_code(404);
            view('errors/404');
        }
    });
}

// ===================================
// API Routes
// ===================================
// AJAX endpoint for form operations
any('/ajax', function() {
    view('ajax');
});

// ===================================
// User Routes
// ===================================
if(ENABLE_AUTH) {
    // Profile
    any('/profile', function() {
        view('user/profile');
    });

    // Account management
    any('/account', function() {
        view('user/account');
    });

    // List management
    any('/lists', function() {
        view('user/lists');
    });

    // Organization Management page
    any('/organization/management', function() {
        auth()->requireAuth('login'); // Ensure user is logged in
        view('organization/management');
    });
}

// ===================================
// Admin & Management Routes
// ===================================
if(ENABLE_AUTH) {
    // Admin panel
    any('/admin', function() {
        view('admin/admin');
    });

    // User management
    any('/admin/users', function() {
        view('admin/users');
    });

    // Account sharing
    any('/admin/share', function() {
        view('admin/share');
    });

    // List Management Admin Page
    any('/admin/lists', function() {
        view('admin/lists');
    });

    // Admin Public Directory
    any('/admin/directory', function() {
        view('admin/public_directory');
    });

    // Analytics dashboard
    any('/analytics', function() {
        view('admin/analytics');
    });
}

// ===================================
// Documentation Routes
// ===================================
// Documentation home
any('/docs', function() {
    view('documentation');
});

// Specific documentation page
get('/docs/:id', function($params) {
    $_GET['doc'] = $params['id']; // Store ID in GET param for backward compatibility
    view('documentation');
});

// ===================================
// Development Routes
// ===================================
// Debug page - only available in development mode
if(DEBUG_MODE === true) {
    get('/debug', function() {
        view('debug');
    });

    get('/test', function() {
        echo 'Hello World!';
    });
}

// Setup page - only accessible if no users exist
any('/setup', function() {
    // Check if users exist without requiring Auth class
    function checkUsersExist() {
        $dbPath = ROOT_DIR . '/db/users';
        if (!is_dir($dbPath)) {
            return false;
        } else return true;
    }
    
    // Redirect if users already exist
    if (checkUsersExist()) {
        header('Location: ' . site_url());
        exit;
    }
    
    view('setup');
});