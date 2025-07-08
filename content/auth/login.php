<?php
/**
 * reBB - Login Page
 * 
 * This file handles user login - no authentication required to access.
 */

// Define the page content to be yielded in the master layout
ob_start();

if(auth()->isLoggedIn()) return redirect('/profile');

// Process login if form submitted
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!auth()->verifyCsrfToken($csrfToken)) {
        $error = "Invalid form submission. Please try again.";
    } else {
        // Attempt login
        if (auth()->login($username, $password)) {
            $user = auth()->getUser();
            $defaultRedirect = site_url('profile'); // Default for regular users

            if ($user && $user['role'] === Auth::ROLE_ORGANIZATION_USER) {
                // For organization_user, try to redirect to their organization page.
                // Need to check if they are linked to an organization.
                $dbPath = ROOT_DIR . '/db';
                try {
                    $userOrgStore = new \SleekDB\Store('user_to_organization_links', $dbPath, ['auto_cache' => false, 'timeout' => false]);
                    $orgLink = $userOrgStore->findOneBy(['user_id', '=', $user['_id']]);
                    if ($orgLink && isset($orgLink['organization_id'])) {
                        $defaultRedirect = site_url('organization/management');
                    } else {
                        // Edge Case: Org user not linked to any org. Log them out with an error.
                        auth()->logout(); // Clean up session
                        $_SESSION['login_error'] = 'Your organization account is not properly configured. Please contact support.';
                        header("Location: " . site_url('login')); // Redirect back to login with error
                        exit;
                    }
                } catch (\Exception $e) {
                    // Database error, log out with a generic error
                    auth()->logout();
                    $_SESSION['login_error'] = 'A database error occurred during login. Please try again later.';
                    error_log("Error fetching org link for org user: " . $e->getMessage());
                    header("Location: " . site_url('login'));
                    exit;
                }
            }
            
            // Check for a previously intended redirect URL, otherwise use the role-based default
            $redirect = isset($_SESSION['auth_redirect']) ? $_SESSION['auth_redirect'] : $defaultRedirect;
            unset($_SESSION['auth_redirect']); // Clear the stored redirect
            
            header("Location: " . $redirect);
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
}

// Generate CSRF token for form
$csrfToken = auth()->generateCsrfToken();
?>

<div class="container login-page">
    <div class="login-container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Login</h3>
            </div>
            <div class="card-body">
                <?php 
                if (isset($_SESSION['login_error'])) {
                    $error = $_SESSION['login_error'];
                    unset($_SESSION['login_error']);
                }
                if ($error): 
                ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="post" action="login">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-group mb-3">
                        <label for="username">Username:</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="password">Password:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary btn-block">Log In</button>
                </form>
                
                <div class="mt-3 text-center">
                    <a href="<?php echo site_url(); ?>">Back to Homepage</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Login';

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';