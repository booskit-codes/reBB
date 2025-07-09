<?php
/**
 * Master layout template
 * 
 * This file serves as the main layout template for all pages.
 * It includes the common header and footer elements.
 */

 // Function to get settings with fallback to default values
function page_setting($key, $default = null) {
    if (isset($GLOBALS['page_settings'][$key])) {
        return $GLOBALS['page_settings'][$key];
    }
    return null;
}

// Function to include the content from the calling page
function yield_content() {
    if (isset($GLOBALS['page_content'])) {
        echo $GLOBALS['page_content'];
    }
}

// Function to include custom CSS from the calling page
function yield_css() {
    if (isset($GLOBALS['page_css'])) {
        echo $GLOBALS['page_css'];
    }
}

// Function to include custom JavaScript from the calling page
function yield_javascript() {
    if (isset($GLOBALS['page_javascript'])) {
        echo $GLOBALS['page_javascript'];
    }
}

function yield_js_vars() {
    if (isset($GLOBALS['page_js_vars'])) {
        echo $GLOBALS['page_js_vars'];
    }
}

// Function to set page title (with default fallback)
function get_page_title() {
    if (isset($GLOBALS['page_title'])) {
        return $GLOBALS['page_title'] . " - " . SITE_NAME;
    }
    return SITE_NAME . " - " . SITE_DESCRIPTION;
}

// Function to detect if current page is homepage
function is_homepage() {
    // Check if the current page is the home/front page
    if (isset($GLOBALS['current_page']) && $GLOBALS['current_page'] === 'front-page') {
        return true;
    }
    
    // Check if we're at the root URL
    $request_uri = $_SERVER['REQUEST_URI'];
    $uri_parts = parse_url($request_uri);
    $path = $uri_parts['path'] ?? '/';
    
    // Get the base path
    $script_name = $_SERVER['SCRIPT_NAME'];
    $dir_path = rtrim(dirname($script_name), '/\\');
    
    // Adjust path for subdirectory installations
    if ($dir_path !== '' && $dir_path !== '/' && strpos($path, $dir_path) === 0) {
        $path = substr($path, strlen($dir_path));
    }
    
    // Clean path
    $path = '/' . ltrim($path, '/');
    
    // Check if we're at the root
    return ($path === '/' || $path === '/index.php');
}

/**
 * Add this function to includes/master.php, just before the closing </head> tag
 */
function generate_meta_tags() {
    // Default values
    $title = get_page_title();
    $description = SITE_DESCRIPTION ?? 'BBCode done differently';
    $image = site_url('resources/android-chrome-192x192.png');
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $type = 'website';
    
    // Get specific page information
    if (isset($_GET['f']) && !empty($_GET['f'])) {
        // We're on a form page - get the form name if available
        $formId = $_GET['f'];
        $filename = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
        
        if (file_exists($filename)) {
            $formData = json_decode(file_get_contents($filename), true);
            if ($formData && isset($formData['formName']) && !empty($formData['formName'])) {
                $title = htmlspecialchars($formData['formName']) . " - " . SITE_NAME;
                $description = "Fill out this form to generate formatted BBCode content.";
            }
        }
    } elseif (isset($GLOBALS['page_title']) && strpos($GLOBALS['page_title'], 'Documentation') !== false) {
        // Documentation page
        $description = "reBB Documentation - Learn how to create and use forms for formatted BBCode content.";
    } elseif (is_homepage()) {
        // Homepage
        $title = SITE_NAME . ' - ' . SITE_DESCRIPTION;
        $description = "Create custom forms without any coding knowledge. Generate structured BBCode content with a simple drag-and-drop interface.";
    } elseif (isset($GLOBALS['page_title']) && strpos($GLOBALS['page_title'], 'Builder') !== false) {
        // Form Builder page
        $description = "Create your own custom form with our easy-to-use form builder. Design forms to generate BBCode templates.";
    } elseif (isset($GLOBALS['page_title']) && strpos($GLOBALS['page_title'], 'Admin') !== false) {
        // Admin page - might want to have no meta for this page
        return "";
    }

    // Generate the meta tags
    $meta = "\n    <!-- Open Graph / Social Media Meta Tags -->\n";
    $meta .= '    <meta property="og:title" content="' . htmlspecialchars($title) . '" />' . "\n";
    $meta .= '    <meta property="og:description" content="' . htmlspecialchars($description) . '" />' . "\n";
    $meta .= '    <meta property="og:image" content="' . htmlspecialchars($image) . '" />' . "\n";
    $meta .= '    <meta property="og:url" content="' . htmlspecialchars($url) . '" />' . "\n";
    $meta .= '    <meta property="og:type" content="' . $type . '" />' . "\n";
    $meta .= '    <meta property="og:site_name" content="' . htmlspecialchars(SITE_NAME) . '" />' . "\n";
    
    // Twitter Card meta tags
    $meta .= '    <meta name="twitter:card" content="summary_large_image" />' . "\n";
    $meta .= '    <meta name="twitter:title" content="' . htmlspecialchars($title) . '" />' . "\n";
    $meta .= '    <meta name="twitter:description" content="' . htmlspecialchars($description) . '" />' . "\n";
    $meta .= '    <meta name="twitter:image" content="' . htmlspecialchars($image) . '" />' . "\n";
    
    return $meta;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_page_title(); ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo site_url(); ?>resources/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo site_url(); ?>resources/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo site_url(); ?>resources/favicon-16x16.png">
    <link rel="manifest" href="<?php echo site_url(); ?>resources/site.webmanifest">
    <?php echo generate_meta_tags(); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo asset_path('css/app.css'); ?>?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="<?php echo asset_path('css/dark-mode.css'); ?>?v=<?php echo APP_VERSION; ?>">
    <?php if (page_setting('formio_assets', true)): ?>
        <link rel="stylesheet" href="https://cdn.form.io/js/formio.full.min.css">
        <script src='https://cdn.form.io/js/formio.full.min.js'></script>
    <?php endif; ?>
    <?php yield_css(); ?>
</head>
<body>
    <?php yield_content(); ?>

    <?php 
    // Only show footer if not on homepage
    if (!is_homepage()): 
    ?>
        <?php if (page_setting('footer', 'form')): ?>
            <footer class="footer">
                <p>Made using <a href="<?php echo site_url(); ?>" target="_blank"><?php echo SITE_NAME; ?></a></br> <!-- Version moved down -->
                <?php if (isset($_GET['f']) && (auth()->hasRole('admin') || ENABLE_JSON_VIEW) && !empty($_GET['f'])): ?>
                    <a href="?f=<?php echo htmlspecialchars($_GET['f']) ?>/json">View form in json</a> • 
                    <a href="<?php echo site_url('builder'); ?>?f=<?php echo htmlspecialchars($_GET['f']) ?>">Use this form as a template</a> • 
                <?php endif; ?>
                <?php if (ENABLE_DONATIONS): ?>
                    <a href="<?php echo site_url('donate'); ?>" class="donate-footer-link"><i class="bi bi-heart-fill"></i> Donate</a> • 
                <?php endif; ?>
                <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a><br/>
                <a href="<?php echo FOOTER_GITHUB; ?>">Github</a><br/>
                <?php
                // Display "Managed by" if applicable
                if (isset($_GET['f']) && !empty($_GET['f'])) {
                    $formIdForFooter = $_GET['f'];
                    $formSchemaPathForFooter = STORAGE_DIR . '/forms/' . $formIdForFooter . '_schema.json';
                    if (file_exists($formSchemaPathForFooter)) {
                        $formSchemaContentFooter = file_get_contents($formSchemaPathForFooter);
                        $formDataFooter = json_decode($formSchemaContentFooter, true);
                        if ($formDataFooter && isset($formDataFooter['organization_id']) && !empty($formDataFooter['organization_id'])) {
                            $organizationIdFooter = $formDataFooter['organization_id'];
                            try {
                                $dbPathFooter = ROOT_DIR . '/db';
                                $organizationsStoreFooter = new \SleekDB\Store('organizations', $dbPathFooter, ['auto_cache' => false, 'timeout' => false]);
                                $organizationDataFooter = $organizationsStoreFooter->findById($organizationIdFooter);
                                if ($organizationDataFooter && isset($organizationDataFooter['organization_name'])) {
                                    echo '<small class="d-block text-muted mt-1">Managed by: ' . htmlspecialchars($organizationDataFooter['organization_name']) . '</small>';
                                }
                            } catch (\Exception $e) {
                                // Silently fail or log if store is not found or other DB error
                                error_log("Error fetching organization for footer: " . $e->getMessage());
                            }
                        }
                    }
                }
                ?>
                <small class="d-block text-muted mt-1">Version: <?php echo APP_VERSION; ?></small>
                </p>
            </footer>
        <?php else: ?>
            <footer class="footer">
                <p>Made with ❤️ by <a href="https://booskit.dev/" target="_blank">booskit</a></br>
                <?php if (auth()->isLoggedIn()): ?>
                    <a href="<?php echo site_url('profile'); ?>">My Forms</a> • 
                <?php endif; ?>
                <a href="<?php echo FOOTER_GITHUB; ?>" target="_blank">Github</a> • 
                <a href="<?php echo site_url('API_DOCUMENTATION.md'); ?>" target="_blank">API</a> •
                <a href="<?php echo DOCS_URL; ?>" target="_blank">Docs</a> •
                <?php if (ENABLE_DONATIONS): ?>
                    <a href="<?php echo site_url('donate'); ?>" class="donate-footer-link"><i class="bi bi-heart-fill"></i> Donate</a> • 
                <?php endif; ?>
                <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a><br/>
                <span style="font-size: 12px;"><?php echo APP_VERSION; ?></span></p> <!-- Kept original version display here -->
            </footer>
        <?php endif; ?>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script><?php yield_js_vars(); ?></script>
    <script src="<?php echo asset_path('js/common.js'); ?>?v=<?php echo APP_VERSION; ?>"></script>
    <script src="<?php echo asset_path('js/analytics.js'); ?>?v=<?php echo APP_VERSION; ?>"></script>
    <?php yield_javascript(); ?>
</body>
</html>