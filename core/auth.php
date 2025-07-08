<?php
/**
 * reBB - Authentication System
 *
 * This file provides all authentication functionality for the application using SleekDB.
 */

// Make sure SleekDB is available
if (!class_exists('SleekDB\Store')) {
    // Try to autoload SleekDB
    $sleekdbPath = ROOT_DIR . '/lib/SleekDB/SleekDB.php';
    if (file_exists($sleekdbPath)) {
        require_once $sleekdbPath;
    } else {
        // SleekDB not found, display an error
        kernel_panic('SleekDB required for session runtime. The application setup procedure has not yet been run.');
    }
}

class Auth {
    private static $instance = null;
    private $userStore;
    private $sessionStore;
    private $dbPath;
    private $isInitialized = false;
    private $currentUser = null;
    private $sessionLifetime = 86400; // Default 24 hours

    // Define roles and their hierarchy (lowest to highest)
    const ROLE_GUEST = 'guest'; // Theoretical, not stored for logged-out users
    const ROLE_ORGANIZATION_USER = 'organization_user';
    const ROLE_USER = 'user';
    const ROLE_EDITOR = 'editor'; // Assuming 'editor' exists or might exist
    const ROLE_ADMIN = 'admin';

    private $roleHierarchy = [
        self::ROLE_GUEST,
        self::ROLE_ORGANIZATION_USER,
        self::ROLE_USER,
        self::ROLE_EDITOR,
        self::ROLE_ADMIN,
    ];
    
    /**
     * Get singleton instance
     *
     * @return Auth
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if any users exist in the database without fully initializing the auth system
     *
     * @return bool True if users exist, false otherwise
     */
    public static function usersExist() {
        $dbPath = ROOT_DIR . '/db';
        
        // Check if the users store exists
        if (!is_dir($dbPath . '/users')) {
            return false;
        }
        
        try {
            $store = new \SleekDB\Store('users', $dbPath, [
                'auto_cache' => false,
                'timeout' => false
            ]);
            
            return $store->count() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct() {
        // Set database path
        $this->dbPath = ROOT_DIR . '/db';
        
        // Initialize database and check session
        $this->initialize();
    }
    
    /**
     * Initialize the authentication system
     *
     * @return bool
     */
    private function initialize() {
        if ($this->isInitialized) {
            return true;
        }
        
        // Create DB directory if it doesn't exist
        if (!is_dir($this->dbPath)) {
            if (!mkdir($this->dbPath, 0755, true)) {
                trigger_error('Failed to create database directory', E_USER_WARNING);
                return false;
            }
        }
        
        try {
            // Initialize SleekDB stores
            $this->userStore = new \SleekDB\Store('users', $this->dbPath, [
                'auto_cache' => true,
                'timeout' => false
            ]);
            
            $this->sessionStore = new \SleekDB\Store('sessions', $this->dbPath, [
                'auto_cache' => false,
                'timeout' => false
            ]);
            
            // Check for existing session
            $this->checkSession();
            
            $this->isInitialized = true;
            return true;
        } catch (\Exception $e) {
            trigger_error('Failed to initialize authentication: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }
    
    /**
     * Check for an existing session and validate it
     *
     * @return bool
     */
    private function checkSession() {
        // Check if we have a session token
        if (!isset($_SESSION['auth_token'])) {
            return false;
        }
        
        $token = $_SESSION['auth_token'];
        
        try {
            // Find the session in the database
            $session = $this->sessionStore->findOneBy(['token', '=', $token]);
            
            if (!$session) {
                // Session not found in database - don't call logout() to preserve CSRF token
                // Just unset the auth token and clear user data
                unset($_SESSION['auth_token']);
                $this->currentUser = null;
                return false;
            }
            
            // Check if session has expired
            if (time() > $session['expires_at']) {
                // Session expired, remove it
                $this->sessionStore->deleteById($session['_id']);
                
                // Don't call logout() to preserve CSRF token
                unset($_SESSION['auth_token']);
                $this->currentUser = null;
                return false;
            }
            
            // Session is valid, load user
            $user = $this->userStore->findById($session['user_id']);
            if (!$user) {
                // User not found, invalidate session
                $this->sessionStore->deleteById($session['_id']);
                
                // Don't call logout() to preserve CSRF token
                unset($_SESSION['auth_token']);
                $this->currentUser = null;
                return false;
            }
            
            // Update session expiration
            $this->sessionStore->updateById($session['_id'], [
                'expires_at' => time() + $this->sessionLifetime
            ]);
            
            // Store current user
            $this->currentUser = $user;
            return true;
        } catch (\Exception $e) {
            dd($e);
            // Error occurred - don't call logout() to preserve CSRF token
            unset($_SESSION['auth_token']);
            $this->currentUser = null;
            return false;
        }
    }
        
    /**
     * Register a new user
     *
     * @param string $username
     * @param string $password
     * @param array $additionalData
     * @return array|bool User data or false on failure
     */
    public function register($username, $password, $additionalData = []) {
        if (!$this->isInitialized) {
            return false;
        }
        
        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return false;
        }
        
        // Check if username already exists
        $existingUser = $this->userStore->findOneBy(['username', '=', $username]);
        if ($existingUser) {
            return false;
        }
        
        // Validate password length
        if (strlen($password) < 8) {
            return false;
        }
        
        // Create user data
        $userData = array_merge([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'created_at' => time(),
            'updated_at' => time(),
            'role' => 'user'
        ], $additionalData);
        
        try {
            // Insert user into database
            $user = $this->userStore->insert($userData);
            
            // Remove password hash from returned data
            unset($user['password_hash']);
            
            return $user;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Attempt to log in a user
     *
     * @param string $username
     * @param string $password
     * @return bool Success
     */
    public function login($username, $password) {
        if (!$this->isInitialized) {
            return false;
        }
        
        try {
            // Find user by username
            $user = $this->userStore->findOneBy(['username', '=', $username]);
            
            if (!$user) {
                return false;
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return false;
            }
            
            // Generate a new session token
            $token = bin2hex(random_bytes(32));
            
            // Store session token in database
            $session = $this->sessionStore->insert([
                'user_id' => $user['_id'],
                'token' => $token,
                'created_at' => time(),
                'expires_at' => time() + $this->sessionLifetime,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            // Store token in session
            $_SESSION['auth_token'] = $token;
            
            // Store user data
            $this->currentUser = $user;
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Log out the current user
     *
     * @return bool Success
     */
    public function logout() {
        // Preserve CSRF token if it exists
        $csrfToken = $_SESSION['csrf_token'] ?? null;
        
        // Clear session data
        unset($_SESSION['auth_token']);
        
        // Reset current user
        $this->currentUser = null;
        
        // Force new session ID
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            
            // Restore CSRF token if it existed
            if ($csrfToken !== null) {
                $_SESSION['csrf_token'] = $csrfToken;
            }
        }
        
        return true;
    }

    /**
     * Get the numerical level of a role.
     * Higher number means higher privilege.
     *
     * @param string $roleName
     * @return int|false Role level or false if role not found
     */
    private function getRoleLevel($roleName) {
        $level = array_search($roleName, $this->roleHierarchy);
        return $level === false ? false : $level;
    }

    /**
     * Check if the current user's role meets or exceeds a minimum required role.
     *
     * @param string $minRoleName The minimum role required (e.g., Auth::ROLE_USER)
     * @return bool True if user meets the requirement, false otherwise.
     */
    public function hasMinRole($minRoleName) {
        if (!$this->isLoggedIn()) {
            // If guest is the minimum requirement, and user is not logged in, they are effectively a guest.
            // However, for roles higher than guest, a non-logged-in user doesn't meet criteria.
            return $this->getRoleLevel($minRoleName) === $this->getRoleLevel(self::ROLE_GUEST);
        }

        $currentUserLevel = $this->getRoleLevel($this->currentUser['role']);
        $requiredLevel = $this->getRoleLevel($minRoleName);

        if ($currentUserLevel === false || $requiredLevel === false) {
            // Invalid role name provided or user has an unrecognized role
            return false;
        }

        return $currentUserLevel >= $requiredLevel;
    }

    /**
     * Middleware to require a minimum role level.
     * Redirects if the user does not meet the role requirement.
     *
     * @param string $minRoleName The minimum role required (e.g., Auth::ROLE_USER)
     * @param string $redirectUrl URL to redirect to if not authorized
     * @return bool True if authorized, otherwise script exits.
     */
    public function requireMinRole($minRoleName, $redirectUrl = 'login') {
        if (!$this->isLoggedIn()) {
            $_SESSION['auth_redirect'] = $_SERVER['REQUEST_URI'];
            header("Location: " . site_url($redirectUrl));
            exit;
        }

        if (!$this->hasMinRole($minRoleName)) {
            // User is logged in but does not have sufficient privileges.
            // Redirect to home or a 'permission denied' page might be better than login.
            // For now, using home.
            // You might want to create a specific 'access-denied.php' view.
            http_response_code(403); // Forbidden
            // For simplicity, redirecting to home. A dedicated error page is better.
            $message = "You do not have sufficient permissions to access this page. Required role: " . $minRoleName . ", Your role: " . $this->currentUser['role'];
            // It's better to have a proper error page rendering function.
            // For now, a simple echo and exit.
            // view('errors/403', ['message' => $message]); // Assuming you might have such a view
            echo "<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>Access Denied</h1><p>" . htmlspecialchars($message) . "</p><p><a href='" . site_url('') . "'>Go to Homepage</a></p></body></html>";
            exit;
        }

        return true;
    }
    
    /**
     * Get the current logged in user
     *
     * @return array|null User data or null if not logged in
     */
    public function getUser() {
        if (!$this->isInitialized || !$this->currentUser) {
            return null;
        }
        
        // Return user without password hash
        $user = $this->currentUser;
        unset($user['password_hash']);
        
        return $user;
    }
    
    /**
     * Check if any user is logged in
     *
     * @return bool
     */
    public function isLoggedIn() {
        return $this->currentUser !== null;
    }
    
    /**
     * Check if the current user has a specific role
     *
     * @param string|array $roles Role or roles to check
     * @return bool
     */
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Convert to array if single role
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return in_array($this->currentUser['role'], $roles);
    }
    
    /**
     * Generate a CSRF token
     *
     * @return string Token
     */
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify a CSRF token
     *
     * @param string $token Token to verify
     * @return bool Valid token
     */
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Middleware to require authentication for a page
     *
     * @param string $redirectUrl URL to redirect to if not authenticated
     * @return bool True if authenticated, false if redirected
     */
    public function requireAuth($redirectUrl = 'login') {
        if (!$this->isLoggedIn()) {
            // Store the requested URL for post-login redirect
            $_SESSION['auth_redirect'] = $_SERVER['REQUEST_URI'];
            
            // Redirect to login page
            header("Location: " . site_url($redirectUrl));
            exit;
        }
        
        return true;
    }
    
    /**
     * Middleware to require a specific role
     *
     * @param string|array $roles Role or roles required
     * @param string $redirectUrl URL to redirect to if not authorized
     * @return bool True if authorized, false if redirected
     */
    public function requireRole($roles, $redirectUrl = 'login') {
        // First check if logged in
        if (!$this->isLoggedIn()) {
            $_SESSION['auth_redirect'] = $_SERVER['REQUEST_URI'];
            header("Location: " . site_url($redirectUrl));
            exit;
        }
        
        // Then check if has required role
        if (!$this->hasRole($roles)) {
            header("Location: " . site_url($redirectUrl));
            exit;
        }
        
        return true;
    }
    
    /**
     * Create admin user if no users exist
     *
     * @param string $username Admin username
     * @param string $password Admin password
     * @return array|bool User data or false on failure
     */
    public function createAdminIfEmpty($username, $password) {
        if (!$this->isInitialized) {
            return false;
        }
        
        try {
            // Check if any users exist
            $userCount = $this->userStore->count();
            
            if ($userCount === 0) {
                // No users exist, create admin
                return $this->register($username, $password, [
                    'role' => 'admin'
                ]);
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Clean up expired sessions (for maintenance)
     *
     * @return int Number of sessions cleaned
     */
    public function cleanExpiredSessions() {
        if (!$this->isInitialized) {
            return 0;
        }
        
        try {
            // Find expired sessions
            $expiredSessions = $this->sessionStore->findBy([
                ['expires_at', '<', time()]
            ]);
            
            // Delete each expired session
            $count = 0;
            foreach ($expiredSessions as $session) {
                $this->sessionStore->deleteById($session['_id']);
                $count++;
            }
            
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }
}

// Create global function for easy access to Auth
function auth() {
    return Auth::getInstance();
}