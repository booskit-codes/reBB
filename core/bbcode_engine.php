<?php
/**
 * reBB - BBCode Generation Engine
 */

class BbcodeEngine {

    /**
     * Generates BBCode based on an API schema and provided field values.
     *
     * @param array $apiSchema The decoded JSON schema for the API.
     * @param array $fieldValues An associative array of field_name => value(s) from user input.
     * @return string The generated BBCode.
     */
    public static function generateBbcodeForApi(array $apiSchema, array $fieldValues): string {
        if (!isset($apiSchema['main_bbcode_template']) || !isset($apiSchema['fields']) || !is_array($apiSchema['fields'])) {
            // This case should ideally be caught before calling, but good to have a fallback.
            error_log("BbcodeEngine: Invalid API schema structure provided.");
            return "Error: Invalid API schema structure.";
        }

        $mainTemplate = $apiSchema['main_bbcode_template'];

        foreach ($apiSchema['fields'] as $fieldSchema) {
            $fieldNameSlug = $fieldSchema['name']; // This is the sanitized name
            $placeholder = '{' . $fieldNameSlug . '}';
            $fieldReplacement = "";

            $individualWrapper = $fieldSchema['individual_wrapper'] ?? '{field_value}';
            $isMultiEntry = $fieldSchema['is_multi_entry'] ?? false;

            $submittedValue = $fieldValues[$fieldNameSlug] ?? null;

            if ($isMultiEntry) {
                $multiStartWrapper = $fieldSchema['multi_start_wrapper'] ?? '';
                $multiEndWrapper = $fieldSchema['multi_end_wrapper'] ?? '';
                $itemsContent = "";

                if (is_array($submittedValue)) {
                    foreach ($submittedValue as $item) {
                        $itemValue = htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
                        $itemsContent .= str_replace('{field_value}', $itemValue, $individualWrapper);
                    }
                } elseif ($submittedValue !== null && !empty(trim($submittedValue))) {
                    // Handle if a single non-empty value is passed for a multi-entry field
                    $itemValue = htmlspecialchars(trim($submittedValue), ENT_QUOTES, 'UTF-8');
                    $itemsContent .= str_replace('{field_value}', $itemValue, $individualWrapper);
                }

                // Only add wrappers if there's content or if the wrappers themselves are not empty
                // This prevents empty [list][/list] if itemsContent is empty and wrappers are present.
                // However, if wrappers are present, they should likely always appear.
                // Let's refine: wrappers appear if they exist, content is inserted between them.
                if (!empty($itemsContent) || !empty($multiStartWrapper) || !empty($multiEndWrapper)) {
                     $fieldReplacement = $multiStartWrapper . $itemsContent . $multiEndWrapper;
                }

            } else {
                // Handle single entry field
                $singleValue = '';
                if (is_array($submittedValue) && isset($submittedValue[0])) { // If somehow an array is passed, take the first element
                    $singleValue = htmlspecialchars(trim($submittedValue[0]), ENT_QUOTES, 'UTF-8');
                } elseif ($submittedValue !== null) {
                    $singleValue = htmlspecialchars(trim($submittedValue), ENT_QUOTES, 'UTF-8');
                }

                // Process wrapper even if value is empty, in case wrapper has default content
                // or is a self-closing type tag.
                if ($singleValue !== '' || strpos($individualWrapper, '{field_value}') === false) {
                    $fieldReplacement = str_replace('{field_value}', $singleValue, $individualWrapper);
                } else if (!empty($individualWrapper) && $singleValue === '') {
                    // If value is empty but wrapper exists, and wrapper expects a value, result is wrapper with empty value.
                    $fieldReplacement = str_replace('{field_value}', '', $individualWrapper);
                }

            }
            $mainTemplate = str_replace($placeholder, $fieldReplacement, $mainTemplate);
        }

        // Replace {api_name} with the API's unique identifier (or display name based on schema design)
        $apiNameForPlaceholder = $apiSchema['api_name_placeholder'] ?? ($apiSchema['api_identifier'] ?? '');
        $finalBbcode = str_replace('{api_name}', htmlspecialchars($apiNameForPlaceholder, ENT_QUOTES, 'UTF-8'), $mainTemplate);

        // Clean up any remaining unmatched field placeholders from the main template
        $finalBbcode = preg_replace('/{[a-zA-Z0-9_]+}/', '', $finalBbcode);

        return $finalBbcode;
    }
}
?>
