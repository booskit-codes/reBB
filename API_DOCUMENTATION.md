# API System Documentation

This document provides instructions on how to set up and use the API system.

## 1. Setting up an API on This Site

APIs are defined using the **API Builder** tool on this website.

**Steps:**

1.  **Navigate to the API Builder:**
    *   You can usually find a link like "Create API System" or similar on the site's main page or dashboard, which will take you to `api_builder.php`.

2.  **Define API Configuration:**
    *   **API Name (Display Name):** Enter a user-friendly name for your API (e.g., "Character Sheet", "Event Announcement"). This name will be used for display purposes and for the `{api_name}` wildcard in your main template.
    *   **Main BBCode Template:** This is the primary structure of your API's output. You will use wildcards here that correspond to the "Field Names" you define below.
        *   Example:
            ```bbcode
            [article id="{api_name}"]
            Display Title: {display_title}
            Author: {author_name}
            Date: {publish_date}

            {content_body}

            [tags]{tags}[/tags]
            [/article]
            ```
        *   The `{api_name}` wildcard will be replaced by the unique **API Identifier** (e.g., `api_a1b2c3d4`) that is generated when you save the API. This is useful for creating unique IDs or references in your BBCode output.
        *   If you want to use the human-readable "API Name (Display Name)" in your template, you should create a separate field for it (e.g., a field named `display_title`) and pass its value when calling the API.
        *   Other wildcards like `{author_name}`, `{publish_date}`, `{content_body}`, `{tags}` must match the "Field Names" you define.

3.  **Define API Fields:**
    *   Click "Add Field" to add each data point your API will handle.
    *   **Field Name:**
        *   A short, descriptive name for the field (e.g., `author_name`, `content_body`, `tags`).
        *   This name, when sanitized (lowercase, spaces to underscores), becomes the wildcard you use in the "Main BBCode Template" (e.g., `author_name` becomes `{author_name}`).
        *   The system will show you available wildcards as you define fields.
    *   **Individual Item BBCode Wrapper:**
        *   The BBCode that will wrap the value provided for this specific field. Use `{field_value}` as a placeholder for the actual data.
        *   Example for `author_name`: `[b]{field_value}[/b]`
        *   Example for `tags` (if it were single entry): `[tag]{field_value}[/tag]`
    *   **Multi-entry Field (Checkbox):**
        *   Check this if the field can accept multiple values (e.g., a list of tags, multiple character abilities).
        *   If checked, two more wrapper fields appear:
            *   **Multi-entry Start Wrapper:** BBCode placed *before* all items of this multi-entry field (e.g., `[list]`).
            *   **Multi-entry End Wrapper:** BBCode placed *after* all items of this multi-entry field (e.g., `[/list]`).
        *   The "Individual Item BBCode Wrapper" will then apply to *each item* within the multi-entry list.
            *   Example for a multi-entry `tags` field:
                *   Individual Item Wrapper: `[*] {field_value}`
                *   Multi-entry Start Wrapper: `[list]`
                *   Multi-entry End Wrapper: `[/list]`

4.  **Live Preview:**
    *   As you define your API, the "Live Preview" section will update.
    *   You can enter sample values in the "Sample Inputs" area to see how the "Preview Output" BBCode will look.

5.  **Save API Schema:**
    *   Once you are satisfied, click "Save API Schema".
    *   Upon successful save, the system will provide you with:
        *   An **API Identifier** (e.g., `api_a1b2c3d4`). This consists of the prefix `api_` followed by a 16-character hexadecimal string (e.g., `a1b2c3d4e5f6a7b8`).
        *   A direct link to the **API Caller page** for this specific API. The URL will look like `[YourDomain]/api_caller?api=[hex_string]`, where `[hex_string]` is *only the 16-character hexadecimal part* of the identifier.

## 2. Using the API from an External Site/Application

Once an API is set up, it can be called via a public URL endpoint.

**Endpoint Structure:**

`[YourDomain]/api/call/[hex_string]`

Where:
*   `[YourDomain]` is the base URL of this website.
*   `[hex_string]` is the 16-character hexadecimal part of the API Identifier (e.g., `a1b2c3d4e5f6a7b8`). The `api_` prefix should *not* be included in this part of the URL. The backend will handle it.

**Passing Parameters:**

Field values are passed as URL query parameters (GET request) or as form data (POST request). The endpoint accepts both.

*   **Field Names:** Use the sanitized field names (lowercase, underscores for spaces) that you defined in the API Builder.
*   **Single-entry fields:** Pass the value directly, e.g., `author_name=John Doe`.
*   **Multi-entry fields:** Pass each item as an array parameter, e.g., `tags[]=general&tags[]=update&tags[]=news`.

**Example Call (GET request):**

Assuming:
*   Your domain is `example.com`
*   The 16-character hex part of your API Identifier is `charstats1234abcde` (so full identifier is `api_charstats1234abcde`)
*   API Fields defined:
    *   `character_name` (single)
    *   `hit_points` (single)
    *   `abilities` (multi-entry)

The call might look like:
`https://example.com/api/call/charstats1234abcde?character_name=Sir%20Reginald&hit_points=150&abilities[]=Strength&abilities[]=Shield%20Bash`

**Expected Response:**

The endpoint will return the generated BBCode as plain text (`text/plain`).

**Example (Conceptual):**

1.  **API Setup (in API Builder):**
    *   **API Name (Display Name):** `Simple Profile`
    *   **Main BBCode Template:**
        ```bbcode
        [profile user="{username}"]
        Status: {status_message}
        Interests:
        {user_interests}
        [/profile]
        ```
    *   **Fields:**
        *   **Field 1:**
            *   Name: `username`
            *   Individual Wrapper: `{field_value}` (no extra wrapper)
            *   Multi-entry: No
        *   **Field 2:**
            *   Name: `status_message`
            *   Individual Wrapper: `[i]{field_value}[/i]`
            *   Multi-entry: No
        *   **Field 3:**
            *   Name: `user_interests`
            *   Individual Wrapper: `[*] {field_value}\n`
            *   Multi-entry: Yes
            *   Multi-entry Start Wrapper: `[list]\n`
            *   Multi-entry End Wrapper: `[/list]`

2.  **Saving provides:**
    *   API Identifier: `api_sample123hexpart` (full identifier would be `api_api_sample123hexpart` - this example is a bit meta, assume `sample123hexpart` is the 16-char hex)

3.  **External Call (GET):**
    `https://[YourDomain]/api/call/sample123hexpart?username=JaneDev&status_message=Coding%20away!&user_interests[]=PHP&user_interests[]=JavaScript&user_interests[]=Hiking`

4.  **Expected BBCode Output (`text/plain`):**
    ```bbcode
    [profile user="JaneDev"]
    Status: [i]Coding away![/i]
    Interests:
    [list]
    [*] PHP
    [*] JavaScript
    [*] Hiking
    [/list]
    [/profile]
    ```

---

Remember to replace `[YourDomain]` and `[hex_string]` (or `[api_identifier]` where appropriate for the full ID) with your actual site URL and the specific API identifier components.
When using the API Caller page on this site, the URL will be `[YourDomain]/api_caller?api=[hex_string]` (using only the 16-character hex part).
The public endpoint for external calls is `[YourDomain]/api/call/[hex_string]` (also using only the 16-character hex part).
The system automatically prepends the `api_` prefix internally for file lookups.
The `{api_name}` wildcard in your Main BBCode Template will be replaced by the full API Identifier (e.g., `api_a1b2c3d4e5f6a7b8`).
You receive the full API Identifier (e.g. `api_a1b2c3d4e5f6a7b8`) and the API Caller URL (with just the hex part) when you save an API in the builder.
