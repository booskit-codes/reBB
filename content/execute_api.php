<?php
/**
 * reBB - Public API Execution Endpoint
 */

header('Content-Type: text/plain');
require_once ROOT_DIR . '/core/bbcode_engine.php'; // Ensure BbcodeEngine class is loaded

$rawApiIdFromUrl = null;
if (isset($_GET['id'])) { // 'id' is set by the router from ':api_identifier'
    $rawApiIdFromUrl = trim($_GET['id']);
}

if (empty($rawApiIdFromUrl)) {
    http_response_code(400);
    echo "Error: API identifier (hex string) is missing from the URL.";
    exit;
}

// Validate the raw identifier format (expecting just 16 hex characters)
if (!preg_match('/^[a-f0-9]{16}$/', $rawApiIdFromUrl)) {
    http_response_code(400);
    echo "Error: Invalid API identifier format. Expected a 16-character hexadecimal string.";
    exit;
}

$fullApiIdentifier = 'api_' . $rawApiIdFromUrl; // Prepend 'api_' for internal use

$apisDir = STORAGE_DIR . '/apis';
$apiFilename = $apisDir . '/' . $fullApiIdentifier . '.json';

if (!file_exists($apiFilename)) {
    http_response_code(404);
    echo "Error: API schema not found for identifier " . htmlspecialchars($rawApiIdFromUrl) . ".";
    exit;
}

$schemaContent = file_get_contents($apiFilename);
$apiSchema = json_decode($schemaContent, true);

if ($apiSchema === null || !isset($apiSchema['fields']) || !is_array($apiSchema['fields']) || !isset($apiSchema['main_bbcode_template'])) {
    http_response_code(500);
    error_log("Corrupt or invalid API schema for identifier: " . $fullApiIdentifier);
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
    error_log("Error during BBCode generation for API " . $fullApiIdentifier . ": " . $e->getMessage()); // Use full identifier in log
    echo "Error: Could not generate BBCode due to a server error.";
}

exit;
?>
