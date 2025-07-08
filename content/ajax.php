<?php
/**
 * reBB - Ajax Backend
 * 
 * This file handles the backend ajax calls.
 */
header('Content-Type: application/json');

// Make sure constants are defined
if (!defined('MAX_REQUESTS_PER_HOUR')) {
    // Default values as fallback
    define('MAX_REQUESTS_PER_HOUR', 60);
    define('COOLDOWN_PERIOD', 5);
    define('IP_BLACKLIST', []);
    define('DEBUG_MODE', false);
}

// Anti-spam configuration - define it globally to be accessible in functions
global $ajax_config;
$ajax_config = [
    'max_requests_per_hour' => MAX_REQUESTS_PER_HOUR,          // Maximum form submissions per hour per IP
    'cooldown_period' => COOLDOWN_PERIOD,                      // Seconds between submissions
    'log_file' => STORAGE_DIR . '/logs/form_submissions.log',     // Path to log file (relative to script)
    'ip_blacklist' => IP_BLACKLIST,
];

// Create logs directory if it doesn't exist
$logsDir = dirname($ajax_config['log_file']);
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Check if user is logged in as admin
function isTrusted() {
    return (auth()->hasRole('editor') || auth()->hasRole('trusted') || auth()->hasRole('admin'));
}

/**
 * Send a Discord webhook notification for public directory submissions
 * 
 * @param array $formData Form data being submitted
 * @param string $formId ID of the form
 * @param string $username Name of the submitter
 * @param bool $isUpdate Whether this is an update to an existing listing
 * @return bool Success status
 */
function sendDiscordWebhookNotification($formData, $formId, $username, $isUpdate = false) {
    // Check if Discord webhook URL is defined in config
    if (!defined('DISCORD_WEBHOOK_URL') || empty(DISCORD_WEBHOOK_URL)) {
        return false;
    }
    
    // Prepare the webhook message
    $formName = isset($formData['formName']) ? $formData['formName'] : 'Unnamed Form';
    $formStyle = isset($formData['formStyle']) ? $formData['formStyle'] : 'default';
    $action = $isUpdate ? 'updated' : 'submitted';
    $formUrl = site_url('form') . '?f=' . $formId;
    $timestamp = date('c'); // ISO 8601 date format
    
    // Format the webhook data
    $webhookData = [
        'embeds' => [
            [
                'title' => 'New Form ' . ($isUpdate ? 'Update' : 'Submission') . ' for Public Directory',
                'description' => "**{$username}** has {$action} a form for the public directory.",
                'color' => 0x3498db, // Blue color
                'fields' => [
                    [
                        'name' => 'Form Name',
                        'value' => $formName,
                        'inline' => true
                    ],
                    [
                        'name' => 'Form ID',
                        'value' => $formId,
                        'inline' => true
                    ],
                    [
                        'name' => 'Style',
                        'value' => ucfirst($formStyle),
                        'inline' => true
                    ],
                    [
                        'name' => 'Submitted By',
                        'value' => $username,
                        'inline' => true
                    ],
                    [
                        'name' => 'Action',
                        'value' => $isUpdate ? 'Form Update' : 'New Form',
                        'inline' => true
                    ],
                    [
                        'name' => 'Review Status',
                        'value' => 'Awaiting Approval',
                        'inline' => true
                    ],
                ],
                'url' => $formUrl,
                'timestamp' => $timestamp,
                'footer' => [
                    'text' => 'reBB Directory Submission System'
                ]
            ]
        ]
    ];
    
    // Add reviewer actions section
    $adminUrl = site_url('admin/lists');
    $webhookData['embeds'][0]['fields'][] = [
        'name' => 'Reviewer Actions',
        'value' => "[View Form]({$formUrl}) | [Manage Listings]({$adminUrl})",
        'inline' => false
    ];
    
    // Send the webhook
    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $success = ($response !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204);
    curl_close($ch);

    if(DEBUG_MODE) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
    
        // Log more detailed information
        if ($response === false || $httpCode !== 204) {
            logAttempt("Discord webhook error: HTTP Code: $httpCode, Error: $error", true);
        }
    }
    
    // Log the webhook attempt
    logAttempt(
        "Discord webhook " . ($success ? "sent" : "failed") . " for form: {$formId} by {$username}",
        !$success
    );
    
    return $success;
}

// Debug IP variables - to help troubleshoot issues
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $raw_remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'Not available';
    $raw_client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? 'Not available';
    $raw_forwarded_for = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not available';
    
    $debug_file = STORAGE_DIR . '/logs/ip_debug.log';
    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] IP Debug Info:\n" . 
        "REMOTE_ADDR: {$raw_remote_addr}\n" .
        "HTTP_CLIENT_IP: {$raw_client_ip}\n" .
        "HTTP_X_FORWARDED_FOR: {$raw_forwarded_for}\n\n", FILE_APPEND);
}

// Check if request method is valid
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAttempt('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only POST allowed.']);
    exit;
}

// Get client IP address - get it for each use rather than storing globally
$ip = getClientIP();

// Check if IP is blacklisted
if (in_array($ip, $ajax_config['ip_blacklist'])) {
    logAttempt('Blacklisted IP attempt');
    echo json_encode(['success' => false, 'error' => 'Request denied.']);
    exit;
}

// Process the JSON data
$jsonData = file_get_contents('php://input');
$requestData = json_decode($jsonData, true);

if ($requestData === null && json_last_error() !== JSON_ERROR_NONE) {
    logAttempt('Invalid JSON data received');
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data received.']);
    exit;
}

// Rate limiting: Check submission cooldown
if (isset($_SESSION['last_submission_time'])) {
    $timeSinceLastSubmission = time() - $_SESSION['last_submission_time'];
    if ($timeSinceLastSubmission < $ajax_config['cooldown_period']) {
        echo json_encode([
            'success' => false, 
            'error' => 'Please wait ' . ($ajax_config['cooldown_period'] - $timeSinceLastSubmission) . ' seconds before submitting again.'
        ]);
        exit;
    }
}

// Rate limiting: Check hourly submission limit
if (!isset($_SESSION['hourly_submissions'])) {
    $_SESSION['hourly_submissions'] = ['count' => 0, 'reset_time' => time() + 3600];
}

// Reset counter if hour has passed
if (time() > $_SESSION['hourly_submissions']['reset_time']) {
    $_SESSION['hourly_submissions'] = ['count' => 0, 'reset_time' => time() + 3600];
}

// Check if hourly limit exceeded
if ($_SESSION['hourly_submissions']['count'] >= $ajax_config['max_requests_per_hour']) {
    $resetTimeFormatted = date('H:i:s', $_SESSION['hourly_submissions']['reset_time']);
    logAttempt('Hourly submission limit exceeded');
    echo json_encode([
        'success' => false, 
        'error' => 'You have reached the maximum submissions per hour. Please try again after ' . $resetTimeFormatted
    ]);
    exit;
}

$requestType = isset($requestData['type']) ? $requestData['type'] : null;
$formsDir = STORAGE_DIR . '/forms';

if (!is_dir($formsDir)) {
    if (!mkdir($formsDir, 0777, true)) { // Create directory with write permissions
        logAttempt('Failed to create forms directory');
        echo json_encode(['success' => false, 'error' => 'Failed to create forms directory.']);
        exit;
    }
}

/**
 * Generate a unique form ID string that doesn't collide with existing files
 * 
 * @param string $formsDir Directory where form schemas are stored
 * @param int $maxAttempts Maximum number of attempts to generate a unique ID
 * @return string|false The unique form ID or false if generation failed
 */
function generateUniqueFormId($formsDir, $maxAttempts = 10) {
    $attempts = 0;
    
    while ($attempts < $maxAttempts) {
        // Generate a random string
        $randomString = bin2hex(random_bytes(16));
        $filename = $formsDir . '/' . $randomString . '_schema.json';
        
        // Check if file already exists
        if (!file_exists($filename)) {
            return $randomString;
        }
        
        // Log this rare collision
        logAttempt("File collision detected: $filename - regenerating ID", true);
        $attempts++;
    }
    
    // If we've reached the maximum attempts, log this and return false
    logAttempt("Failed to generate a unique form ID after $maxAttempts attempts", true);
    return false;
}

// Later in the code where we handle the 'schema' type request
if ($requestType === 'schema') {
    // Basic content validation
    $formSchema = isset($requestData['schema']) ? $requestData['schema'] : null;
    $formTemplate = isset($requestData['template']) ? $requestData['template'] : ''; 
    $formName = isset($requestData['formName']) ? $requestData['formName'] : '';
    $formStyle = isset($requestData['formStyle']) ? $requestData['formStyle'] : 'default'; // Get form style
    $isEditMode = isset($requestData['editMode']) && $requestData['editMode'] === true;
    $editingFormId = isset($requestData['editingForm']) ? $requestData['editingForm'] : '';
    $builderType = isset($requestData['builder']) ? $requestData['builder'] : '';
    $formTypeParam = isset($requestData['form_type']) ? $requestData['form_type'] : ''; // Get the form_type

    $createdBy = null;
    $verified = false;
    if(auth()->isLoggedIn()) {
        $currentUser = auth()->getUser();
        $createdBy = $currentUser['_id'];
        if(isTrusted()) {
            $verified = true;
        }
    }
    
    // Get the template title and link fields with toggle states
    $enableTemplateTitle = isset($requestData['enableTemplateTitle']) ? (bool)$requestData['enableTemplateTitle'] : false;
    $enableTemplateLink = isset($requestData['enableTemplateLink']) ? (bool)$requestData['enableTemplateLink'] : false;
    $enablePublicLink = isset($requestData['enablePublicLink']) ? (bool)$requestData['enablePublicLink'] : false;
    $templateTitle = $enableTemplateTitle && isset($requestData['templateTitle']) ? $requestData['templateTitle'] : '';
    $templateLink = $enableTemplateLink && isset($requestData['templateLink']) ? $requestData['templateLink'] : '';

    if ($formSchema === null) {
        logAttempt('No form schema data received');
        echo json_encode(['success' => false, 'error' => 'No form schema data received.']);
        exit;
    }

    // Check for very large submissions (potential DoS)
    $jsonSize = strlen(json_encode($formSchema));
    $allowedSize = MAX_SCHEMA_SIZE_GUEST;
    if(auth()->isLoggedIn()) {
        $allowedSize = MAX_SCHEMA_SIZE_MEMBER;
    }
    if ($jsonSize > $allowedSize) {
        logAttempt('Form schema too large: ' . $jsonSize . ' bytes');
        echo json_encode(['success' => false, 'error' => 'Form schema exceeds maximum allowed size.']);
        exit;
    }

    // Sanitize template to prevent malicious content
    $formTemplate = htmlspecialchars($formTemplate, ENT_QUOTES, 'UTF-8');
    $templateTitle = htmlspecialchars($templateTitle, ENT_QUOTES, 'UTF-8');
    $templateLink = htmlspecialchars($templateLink, ENT_QUOTES, 'UTF-8');
    
    // Validate form style (only allow valid options)
    $allowedStyles = ['default', 'paperwork', 'vector', 'retro', 'modern'];
    if (!in_array($formStyle, $allowedStyles)) {
        $formStyle = 'default'; // Default fallback
    }
    
    // Determine if we're editing or creating a new form
    if ($isEditMode && !empty($editingFormId)) {
        // Handle form editing - verify ownership
        $existingFilename = $formsDir . '/' . $editingFormId . '_schema.json';
        
        if (!file_exists($existingFilename)) {
            logAttempt('Edit attempt for non-existent form: ' . $editingFormId);
            echo json_encode(['success' => false, 'error' => 'Form not found.']);
            exit;
        }
        
        // Load the existing form data
        $existingFormData = json_decode(file_get_contents($existingFilename), true);
        
        // Check if user has permission to edit this form
        if (!auth()->isLoggedIn()) {
            logAttempt('Unauthenticated edit attempt for form: ' . $editingFormId);
            echo json_encode(['success' => false, 'error' => 'Authentication required to edit forms.']);
            exit;
        }
        
        $currentUser = auth()->getUser();
        $formCreator = isset($existingFormData['createdBy']) ? $existingFormData['createdBy'] : null;
        
        if ($formCreator !== $currentUser['_id'] && $currentUser['role'] !== 'admin') {
            logAttempt('Unauthorized edit attempt for form: ' . $editingFormId . ' by user: ' . $currentUser['username']);
            echo json_encode(['success' => false, 'error' => 'You do not have permission to edit this form.']);
            exit;
        }

        $formWidth = isset($requestData['formWidth']) ? (int)$requestData['formWidth'] : 45;
        $formWidth = max(20, min(100, $formWidth)); // Limit between 20-100%
        
        // User has permission, proceed with update
        // Keep the original creation data but update everything else
        $fileContent = json_encode([
            'success' => true,
            'filename' => $existingFilename,
            'formName' => $formName,
            'schema' => $formSchema,
            'template' => $formTemplate,
            'templateTitle' => $templateTitle,
            'templateLink' => $templateLink,
            'enableTemplateTitle' => $enableTemplateTitle,
            'enableTemplateLink' => $enableTemplateLink,
            'enablePublicLink' => $enablePublicLink,
            'public' => $enablePublicLink, // Add the public flag
            'builder' => $builderType,
            'formWidth' => $formWidth,
            'formStyle' => $formStyle,
            'createdBy' => $formCreator, // Maintain original creator
            'verified' => $verified,
            'created' => $existingFormData['created'] ?? time(), // Maintain original creation time
            'updated' => time() // Add update timestamp
        ], JSON_PRETTY_PRINT);
        
        if (!file_put_contents($existingFilename, $fileContent)) {
            logAttempt('Failed to update form: ' . $editingFormId);
            echo json_encode(['success' => false, 'error' => 'Failed to update form.']);
            exit;
        }
        
        // Update the form data in SleekDB only if authenticated
        if (auth()->isLoggedIn()) {
            $currentUser = auth()->getUser();
            $userId = $currentUser['_id'];

            try {
                // Initialize the SleekDB store for user forms
                $dbPath = ROOT_DIR . '/db';
                $userFormsStore = new \SleekDB\Store('user_forms', $dbPath, [
                    'auto_cache' => false,
                    'timeout' => false
                ]);
                
                // Look up existing record for this form
                $existingRecord = $userFormsStore->findOneBy([['form_id', '=', $editingFormId], 'AND', ['user_id', '=', $userId]]);
                
                if ($existingRecord) {
                    // Update existing record
                    $userFormsStore->updateById($existingRecord['_id'], [
                        'form_name' => $formName,
                        'last_updated' => time()
                    ]);
                } else if ($formCreator === $userId) {
                    // Create new record only if user is the creator
                    $userFormsStore->insert([
                        'user_id' => $userId,
                        'form_id' => $editingFormId,
                        'form_name' => $formName,
                        'created_at' => $existingFormData['created'] ?? time(),
                        'last_updated' => time()
                    ]);
                }
                
                logAttempt("Updated form in database: $editingFormId by user: " . $currentUser['username'], false);
            } catch (\Exception $e) {
                // Log the error but don't stop the process
                logAttempt("Error updating form in database: " . $e->getMessage());
            }
            
            // Handle public listing
            try {
                $publicListingsStore = new \SleekDB\Store('public_listings', $dbPath, [
                    'auto_cache' => false,
                    'timeout' => false
                ]);
                
                // Look up existing public listing
                $existingPublicListing = $publicListingsStore->findOneBy([['form_id', '=', $editingFormId]]);
                
                if ($enablePublicLink) {
                    // Form should be public
                    $publicListingData = [
                        'form_id' => $editingFormId,
                        'form_name' => $formName,
                        'form_style' => $formStyle,
                        'user_id' => $userId,
                        'username' => $currentUser['username'],
                        'last_updated' => time(),
                        'is_guest' => false, // This is definitely a logged-in user at this point
                        'is_approved' => false // Reset approval status on update
                    ];
                    
                    if ($existingPublicListing) {
                        // Update existing public listing
                        $publicListingsStore->updateById($existingPublicListing['_id'], $publicListingData);
                    } else {
                        // Create new public listing
                        $publicListingData['created_at'] = time();
                        $publicListingsStore->insert($publicListingData);
                    }
                    
                    logAttempt("Updated public listing for form: $editingFormId by user: " . $currentUser['username'], false);
                    
                    // Send Discord webhook notification
                    $formData = [
                        'formName' => $formName,
                        'formStyle' => $formStyle
                    ];
                    sendDiscordWebhookNotification($formData, $editingFormId, $currentUser['username'], $existingPublicListing ? true : false);
                } else if ($existingPublicListing) {
                    // Form is no longer public, remove from listings
                    $publicListingsStore->deleteById($existingPublicListing['_id']);
                    logAttempt("Removed public listing for form: $editingFormId by user: " . $currentUser['username'], false);
                }
            } catch (\Exception $e) {
                // Log the error but don't stop the process
                logAttempt("Error updating public listing: " . $e->getMessage());
            }
        }
        
        // Update rate limiting counters
        $_SESSION['last_submission_time'] = time();
        $_SESSION['hourly_submissions']['count']++;
        
        // Log successful update
        logAttempt("Successful form update - Form ID: $editingFormId by user: " . ($currentUser['username'] ?? 'Unknown'), false);
        
        // Organizational logic for edited forms - typically an org form definition wouldn't be edited
        // but if it were, its link to an organization is already established.
        // We might want to update org name if formName changed and it's an org form.
        if ($formTypeParam === 'organization' && auth()->isLoggedIn()) {
            try {
                $orgDbPath = ROOT_DIR . '/db';
                $organizationsStore = new \SleekDB\Store('organizations', $orgDbPath, [
                    'auto_cache' => false, 'timeout' => false
                ]);
                $orgData = $organizationsStore->findOneBy(['form_id', '=', $editingFormId]);
                if ($orgData && $orgData['organization_name'] !== $formName) {
                    $organizationsStore->updateById($orgData['_id'], ['organization_name' => $formName, 'updated_at' => time()]);
                }
            } catch (\Exception $e) {
                logAttempt("Error updating organization name during form edit: " . $e->getMessage());
            }
        }

        // Return success response with original form ID
        echo json_encode([
            'success' => true,
            'filename' => $existingFilename,
            'formId' => $editingFormId,
            'isUpdate' => true
        ]);
        exit;
    } else {
        // This is a new form creation - Generate a unique form ID
        $randomString = generateUniqueFormId($formsDir);
        
        if ($randomString === false) {
            logAttempt('Failed to generate a unique form ID');
            echo json_encode(['success' => false, 'error' => 'Failed to generate a unique form ID. Please try again.']);
            exit;
        }

        $formWidth = isset($requestData['formWidth']) ? (int)$requestData['formWidth'] : 35;
        $formWidth = max(20, min(100, $formWidth)); // Limit between 20-100%
        
        $filename = $formsDir . '/' . $randomString . '_schema.json';
        $fileContent = json_encode([
            'success' => true,
            'filename' => $filename,
            'formName' => $formName,
            'schema' => $formSchema,
            'template' => $formTemplate,
            'templateTitle' => $templateTitle,
            'templateLink' => $templateLink,
            'enableTemplateTitle' => $enableTemplateTitle,
            'enableTemplateLink' => $enableTemplateLink,
            'enablePublicLink' => $enablePublicLink,
            'public' => $enablePublicLink, // Add the public flag
            'builder' => $builderType,
            'formStyle' => $formStyle,
            'createdBy' => $createdBy,
            'formWidth' => $formWidth,
            'verified' => $verified,
            'created' => time()
        ], JSON_PRETTY_PRINT);

        if (!file_put_contents($filename, $fileContent)) {
            logAttempt('Failed to save form schema to file');
            echo json_encode(['success' => false, 'error' => 'Failed to save form schema to file.']);
            exit;
        }
        
        // Initialize database path
        $dbPath = ROOT_DIR . '/db';
        
        // Store the form data in SleekDB if the user is authenticated
        if (auth()->isLoggedIn()) {
            $currentUser = auth()->getUser();
            $userId = $currentUser['_id'];
            $username = $currentUser['username'];
            
            try {
                // Initialize the SleekDB store for user forms
                $userFormsStore = new \SleekDB\Store('user_forms', $dbPath, [
                    'auto_cache' => false,
                    'timeout' => false
                ]);
                
                // Insert the form record
                $userFormsStore->insert([
                    'user_id' => $userId,
                    'form_id' => $randomString,
                    'form_name' => $formName,
                    'created_at' => time(),
                    'last_updated' => time()
                ]);
                
                logAttempt("Added form to database: $randomString by user: " . $currentUser['username'], false);
            } catch (\Exception $e) {
                // Log the error but don't stop the process
                logAttempt("Error adding form to database: " . $e->getMessage());
            }
        } else {
            // Set default values for guest users
            $userId = 'guest';
            $username = 'Anonymous';
        }
        
        // Handle public listing if enabled - regardless of login status
        if ($enablePublicLink) {
            try {
                $publicListingsStore = new \SleekDB\Store('public_listings', $dbPath, [
                    'auto_cache' => false,
                    'timeout' => false
                ]);
                
                // Insert the public listing record
                $publicListingsStore->insert([
                    'form_id' => $randomString,
                    'form_name' => $formName,
                    'form_style' => $formStyle,
                    'user_id' => $userId,
                    'username' => $username,
                    'created_at' => time(),
                    'last_updated' => time(),
                    'is_guest' => !auth()->isLoggedIn(), // Flag to identify guest submissions
                    'is_approved' => false
                ]);
                
                logAttempt("Added form to public listings: $randomString by " . (auth()->isLoggedIn() ? "user: $username" : "guest"), false);
                
                // Send Discord webhook notification for public listing submission
                $formData = [
                    'formName' => $formName,
                    'formStyle' => $formStyle
                ];
                sendDiscordWebhookNotification($formData, $randomString, $username, false);
            } catch (\Exception $e) {
                // Log the error but don't stop the process
                logAttempt("Error adding form to public listings: " . $e->getMessage(), false);
            }
        }

        // Update rate limiting counters
        $_SESSION['last_submission_time'] = time();
        $_SESSION['hourly_submissions']['count']++;
        
        // Log successful submission with form ID
        $userInfo = auth()->isLoggedIn() ? " by user: " . auth()->getUser()['username'] : "";
        logAttempt("Successful form schema submission - Form ID: $randomString$userInfo", false);
        
        $createdOrganizationId = null;
        // === Organization Creation Logic ===
        if ($formTypeParam === 'organization' && auth()->isLoggedIn()) {
            $currentUserId = auth()->getUser()['_id'];
            $orgDbPath = ROOT_DIR . '/db';

            try {
                // Check if user already belongs to an organization
                $userOrgLinkStore = new \SleekDB\Store('user_to_organization_links', $orgDbPath, ['auto_cache' => false, 'timeout' => false]);
                $existingLink = $userOrgLinkStore->findOneBy(['user_id', '=', $currentUserId]);

                if ($existingLink) {
                    // User already in an org. Delete the form we just created to avoid orphans.
                    if (file_exists($filename)) {
                        unlink($filename);
                    }
                    // Also remove from user_forms
                    if(isset($userFormsStore)) { // Check if $userFormsStore was initialized
                        $userFormEntry = $userFormsStore->findOneBy([['form_id', '=', $randomString], 'AND', ['user_id', '=', $currentUserId]]);
                        if($userFormEntry) {
                            $userFormsStore->deleteById($userFormEntry['_id']);
                        }
                    }
                     // Also remove from public_listings if it was added
                    if ($enablePublicLink && isset($publicListingsStore)) {
                        $publicListingEntry = $publicListingsStore->findOneBy(['form_id', '=', $randomString]);
                        if ($publicListingEntry) {
                            $publicListingsStore->deleteById($publicListingEntry['_id']);
                        }
                    }

                    echo json_encode(['success' => false, 'error' => 'You already belong to an organization. You cannot create a new one. The submitted form has been discarded.']);
                    exit;
                }

                // Create organization
                $organizationsStore = new \SleekDB\Store('organizations', $orgDbPath, ['auto_cache' => false, 'timeout' => false]);
                $newOrganization = $organizationsStore->insert([
                    'organization_name' => $formName ?: 'New Organization', // Use form name or a default
                    'form_id' => $randomString,
                    'owner_user_id' => $currentUserId,
                    'created_at' => time(),
                    'updated_at' => time()
                ]);

                if (!$newOrganization || !isset($newOrganization['_id'])) {
                    throw new \Exception("Failed to create organization entry in DB.");
                }
                $createdOrganizationId = $newOrganization['_id'];

                // Link user to organization
                $userOrgLinkStore->insert([
                    'user_id' => $currentUserId,
                    'organization_id' => $createdOrganizationId
                ]);

                // Update the form's schema JSON file with organization_id
                $schemaData = json_decode(file_get_contents($filename), true);
                $schemaData['organization_id'] = $createdOrganizationId;
                file_put_contents($filename, json_encode($schemaData, JSON_PRETTY_PRINT));

                logAttempt("Successfully created organization ID: $createdOrganizationId and linked to user ID: $currentUserId for form ID: $randomString", false);

            } catch (\Exception $e) {
                logAttempt("Error during organization creation: " . $e->getMessage());
                // Rollback: If form file was created, delete it as org creation failed.
                if (file_exists($filename)) {
                    unlink($filename);
                }
                 // Also remove from user_forms
                if(isset($userFormsStore)) {
                    $userFormEntry = $userFormsStore->findOneBy([['form_id', '=', $randomString], 'AND', ['user_id', '=', $currentUserId]]);
                    if($userFormEntry) {
                        $userFormsStore->deleteById($userFormEntry['_id']);
                    }
                }
                // Also remove from public_listings if it was added
                if ($enablePublicLink && isset($publicListingsStore)) {
                    $publicListingEntry = $publicListingsStore->findOneBy(['form_id', '=', $randomString]);
                    if ($publicListingEntry) {
                        $publicListingsStore->deleteById($publicListingEntry['_id']);
                    }
                }
                // If organization was partially created, try to delete it
                if ($createdOrganizationId && isset($organizationsStore)) {
                    $organizationsStore->deleteById($createdOrganizationId);
                }

                echo json_encode(['success' => false, 'error' => 'Failed to create organization: ' . $e->getMessage()]);
                exit;
            }
        }
        // === End Organization Creation Logic ===

        $responseData = json_decode($fileContent, true);
        $responseData['formId'] = $randomString; // Add formId to the response
        if ($createdOrganizationId) {
            $responseData['organization_id'] = $createdOrganizationId;
        }
        echo json_encode($responseData);
        exit;
    }
} elseif ($requestType === 'custom_link') {
    // Require authentication
    if (!auth()->isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Authentication required.']);
        exit;
    }
    
    $currentUser = auth()->getUser();
    $userId = $currentUser['_id'];
    $action = isset($requestData['action']) ? $requestData['action'] : null;
    
    // Initialize custom links store
    $dbPath = ROOT_DIR . '/db';
    $linkStore = new \SleekDB\Store('custom_links', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Initialize users store to check link limits
    $userStore = new \SleekDB\Store('users', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Get user's max links limit
    $userData = $userStore->findById($userId);
    $maxLinks = isset($userData['max_unique_links']) ? $userData['max_unique_links'] : DEFAULT_MAX_UNIQUE_LINKS;
    
    switch ($action) {
        case 'create':
            // Get parameters
            $formId = isset($requestData['form_id']) ? $requestData['form_id'] : '';
            $customLink = isset($requestData['custom_link']) ? $requestData['custom_link'] : '';
            
            // Basic validation
            if (empty($formId)) {
                echo json_encode(['success' => false, 'error' => 'Form ID is required.']);
                exit;
            }
            
            if (empty($customLink)) {
                echo json_encode(['success' => false, 'error' => 'Custom link is required.']);
                exit;
            }
            
            // Validate link format
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $customLink)) {
                echo json_encode(['success' => false, 'error' => 'Custom link can only contain letters, numbers, hyphens and underscores.']);
                exit;
            }
            
            // Validate link length
            $minLength = defined('CUSTOM_LINK_MIN_LENGTH') ? CUSTOM_LINK_MIN_LENGTH : 3;
            $maxLength = defined('CUSTOM_LINK_MAX_LENGTH') ? CUSTOM_LINK_MAX_LENGTH : 30;
            
            if (strlen($customLink) < $minLength || strlen($customLink) > $maxLength) {
                echo json_encode(['success' => false, 'error' => "Custom link must be between {$minLength} and {$maxLength} characters."]);
                exit;
            }
            
            // Check if the form exists
            $formPath = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
            if (!file_exists($formPath)) {
                echo json_encode(['success' => false, 'error' => 'Form not found.']);
                exit;
            }
            
            // Get form data
            $formData = json_decode(file_get_contents($formPath), true);
            $formName = isset($formData['formName']) ? $formData['formName'] : 'Unnamed Form';
            
            // Check if custom link already exists
            $existingLink = $linkStore->findOneBy([['custom_link', '=', $customLink]]);
            if ($existingLink) {
                echo json_encode(['success' => false, 'error' => 'This custom link is already in use. Please choose another.']);
                exit;
            }
            
            // Check if user has reached their link limit
            $userLinks = $linkStore->findBy([['user_id', '=', $userId]]);
            if (count($userLinks) >= $maxLinks) {
                echo json_encode(['success' => false, 'error' => "You have reached your limit of {$maxLinks} custom links. Please delete some before creating new ones."]);
                exit;
            }
            
            // Create the custom link
            try {
                $linkStore->insert([
                    'user_id' => $userId,
                    'form_id' => $formId,
                    'custom_link' => $customLink,
                    'form_name' => $formName,
                    'created_at' => time(),
                    'use_count' => 0
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Custom link created successfully.']);
                logAttempt("Created custom link: {$customLink} for form: {$formId}", false);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to create custom link: ' . $e->getMessage()]);
                logAttempt("Failed to create custom link: {$customLink} - " . $e->getMessage());
            }
            break;
            
        case 'list':
            // Get all custom links for current user
            try {
                $links = $linkStore->findBy([['user_id', '=', $userId]]);
                
                // Add full URL to each link
                foreach ($links as &$link) {
                    $link['full_url'] = site_url('u') . '?f=' . $link['custom_link'];
                }
                
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'links' => $links,
                        'links_used' => count($links),
                        'max_links' => $maxLinks
                    ]
                ]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to fetch custom links: ' . $e->getMessage()]);
                logAttempt("Failed to fetch custom links - " . $e->getMessage());
            }
            break;
            
        case 'delete':
            // Get parameters
            $customLink = isset($requestData['custom_link']) ? $requestData['custom_link'] : '';
            
            if (empty($customLink)) {
                echo json_encode(['success' => false, 'error' => 'Custom link is required.']);
                exit;
            }
            
            // Check if link exists and belongs to user
            $linkData = $linkStore->findOneBy([
                ['custom_link', '=', $customLink],
                'AND',
                ['user_id', '=', $userId]
            ]);
            
            if (!$linkData) {
                // For admins, allow deleting any link
                if ($currentUser['role'] === 'admin') {
                    $linkData = $linkStore->findOneBy([['custom_link', '=', $customLink]]);
                    
                    if (!$linkData) {
                        echo json_encode(['success' => false, 'error' => 'Custom link not found.']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this link.']);
                    exit;
                }
            }
            
            // Delete the link
            try {
                $linkStore->deleteById($linkData['_id']);
                echo json_encode(['success' => true, 'message' => 'Custom link deleted successfully.']);
                logAttempt("Deleted custom link: {$customLink}", false);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to delete custom link: ' . $e->getMessage()]);
                logAttempt("Failed to delete custom link: {$customLink} - " . $e->getMessage());
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid custom link action.']);
            exit;
    }
    exit;
} elseif ($requestType === 'analytics') {
    $analytics = new Analytics();

    if (!$analytics->isEnabled()) {
        echo json_encode(['success' => true, 'analyticsEnabled' => false]);
        exit;
    }
    
    $action = isset($requestData['action']) ? $requestData['action'] : null;
    
    switch ($action) {
        case 'track_pageview':
            $page = isset($requestData['page']) ? $requestData['page'] : '';
            $analytics->trackPageView($page);
            break;
            
        case 'track_component':
            $component = isset($requestData['component']) ? $requestData['component'] : '';
            $analytics->trackComponentUsage($component);
            break;
            
        case 'track_theme':
            $theme = isset($requestData['theme']) ? $requestData['theme'] : '';
            $analytics->trackThemeUsage($theme);
            break;
            
        case 'track_form':
            $formId = isset($requestData['formId']) ? $requestData['formId'] : '';
            $isSubmission = isset($requestData['isSubmission']) ? 
                $requestData['isSubmission'] : false;
            $analytics->trackFormUsage($formId, $isSubmission);
            break;
            
        case 'get_live_visitors':
            $count = $analytics->getLiveVisitors();
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
    }
    
    echo json_encode(['success' => true, 'analyticsEnabled' => true]);
    exit;
} elseif ($requestType === 'public_listings') {
    // Handle public listings API requests
    $action = isset($requestData['action']) ? $requestData['action'] : 'list';
    
    $dbPath = ROOT_DIR . '/db';
    $publicListingsStore = new \SleekDB\Store('public_listings', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    switch ($action) {
        case 'list':
            // Get options for pagination/filtering
            $page = isset($requestData['page']) ? (int)$requestData['page'] : 1;
            $limit = isset($requestData['limit']) ? (int)$requestData['limit'] : 20;
            $sort = isset($requestData['sort']) ? $requestData['sort'] : 'newest';
            
            // Apply pagination and sorting
            $skip = ($page - 1) * $limit;
            
            // Set sorting options
            $sortOptions = [];
            if ($sort === 'newest') {
                $sortOptions['created_at'] = 'desc';
            } elseif ($sort === 'oldest') {
                $sortOptions['created_at'] = 'asc';
            } elseif ($sort === 'name_asc') {
                $sortOptions['form_name'] = 'asc';
            } elseif ($sort === 'name_desc') {
                $sortOptions['form_name'] = 'desc';
            }
            
            // Get public listings with pagination
            $listings = $publicListingsStore->findAll($limit, $skip, $sortOptions);
            $total = $publicListingsStore->count();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'listings' => $listings,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'search':
            $query = isset($requestData['query']) ? $requestData['query'] : '';
            
            if (empty($query)) {
                echo json_encode(['success' => false, 'error' => 'Search query is required.']);
                exit;
            }
            
            // Search by form name or username
            $results = $publicListingsStore->createQueryBuilder()
                ->where([
                    ['form_name', 'LIKE', "%{$query}%"],
                    'OR',
                    ['username', 'LIKE', "%{$query}%"]
                ])
                ->limit(20)
                ->orderBy(['created_at' => 'desc'])
                ->getQuery()
                ->fetch();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'listings' => $results,
                    'total' => count($results)
                ]
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid public listings action.']);
            exit;
    }
    exit;
} else {
    logAttempt('Invalid request type: ' . $requestType);
    echo json_encode(['success' => false, 'error' => 'Invalid request type.']);
    exit;
}

/**
 * Get client's real IP address
 * @return string The IP address
 */
function getClientIP() {
    // Try each common IP source variable
    $ip_sources = [
        'HTTP_CLIENT_IP', 
        'HTTP_X_FORWARDED_FOR', 
        'HTTP_X_FORWARDED', 
        'HTTP_FORWARDED_FOR', 
        'HTTP_FORWARDED', 
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_sources as $source) {
        if (!empty($_SERVER[$source])) {
            // For forwarded IPs that might contain multiple IPs
            if ($source === 'HTTP_X_FORWARDED_FOR') {
                $ips = explode(',', $_SERVER[$source]);
                $ip = trim($ips[0]);
            } else {
                $ip = $_SERVER[$source];
            }
            
            // If it's a valid IP that's not a private range, use it
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    // If we didn't find any valid IP in the preferred sources,
    // just return REMOTE_ADDR as a last resort
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

/**
 * Log submission attempts for security monitoring
 * @param string $message The message to log
 * @param bool $isFailure Whether this is a failed attempt (default: true)
 */
function logAttempt($message, $isFailure = true) {
    global $ajax_config;
    
    // Get IP directly in this function to ensure it's always available
    $ip = getClientIP();
    
    // Safety check - make sure log_file is defined
    if (empty($ajax_config) || empty($ajax_config['log_file'])) {
        // Fallback log file location
        $log_file = STORAGE_DIR . '/logs/form_submissions.log';
    } else {
        $log_file = $ajax_config['log_file'];
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $status = $isFailure ? 'FAILED' : 'SUCCESS';
    $logEntry = "[$timestamp] [$status] [IP: $ip] $message" . PHP_EOL;
    
    // Make sure directory exists
    $logsDir = dirname($log_file);
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    
    // Try to write to log file, silently fail if unable
    @file_put_contents($log_file, $logEntry, FILE_APPEND);
    
    // Additional debug logging if debug mode is enabled
    if (defined('DEBUG_MODE') && DEBUG_MODE && $isFailure) {
        error_log("reBB Ajax Error: $message [IP: $ip]");
    }
}