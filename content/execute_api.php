<?php
/**
 * reBB - Public API Execution Endpoint
 */

header('Content-Type: text/plain');
require_once ROOT_DIR . '/core/bbcode_engine.php'; // Ensure BbcodeEngine class is loaded

$apiIdentifier = null;
if (isset($_GET['id'])) { // 'id' is set by the router from ':api_identifier'
    $apiIdentifier = trim($_GET['id']);
}

if (empty($apiIdentifier)) {
    http_response_code(400);
    echo "Error: API identifier is missing.";
    exit;
}

// Validate the identifier format (api_ followed by 16 hex characters)
if (!preg_match('/^api_[a-f0-9]{16}$/', $apiIdentifier)) {
    http_response_code(400);
    echo "Error: Invalid API identifier format.";
    exit;
}

$apisDir = STORAGE_DIR . '/apis';
$apiFilename = $apisDir . '/' . $apiIdentifier . '.json';

if (!file_exists($apiFilename)) {
    http_response_code(404);
    echo "Error: API schema not found.";
    exit;
}

$schemaContent = file_get_contents($apiFilename);
$apiSchema = json_decode($schemaContent, true);

if ($apiSchema === null || !isset($apiSchema['fields']) || !is_array($apiSchema['fields']) || !isset($apiSchema['main_bbcode_template'])) {
    http_response_code(500);
    error_log("Corrupt or invalid API schema for identifier: " . $apiIdentifier);
    echo "Error: Could not process API schema. It might be corrupt or invalid.";
    exit;
}

// Field values can be passed via GET or POST, $_REQUEST handles both.
// Note: For file uploads or very large data, POST is preferred.
// For simplicity and easier URL construction for docs, GET is common.
$fieldValues = $_REQUEST;

// Remove 'id' from fieldValues if it exists (as it's our route param, not an API field)
unset($fieldValues['id']);
// Remove any other potential routing parameters if your router adds them to $_GET/$_REQUEST
// For example, if your router adds a 'route' parameter:
// unset($fieldValues['route']);


try {
    $generatedBbcode = BbcodeEngine::generateBbcodeForApi($apiSchema, $fieldValues);
    echo $generatedBbcode;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error during BBCode generation for API " . $apiIdentifier . ": " . $e->getMessage());
    echo "Error: Could not generate BBCode due to a server error.";
}

exit;
?>
