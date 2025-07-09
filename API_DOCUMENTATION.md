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
        *   An **API Identifier** (e.g., `api_a1b2c3d4`). This is crucial for calling the API.
        *   A direct link to the **API Caller page** for this specific API, which will look like `[YourDomain]/api_caller?api=[api_identifier]`.

## 2. Using the API from an External Site/Application

Once an API is set up, it can be called via a public URL endpoint.

**Endpoint Structure:**

`[YourDomain]/api/call/[api_identifier]`

Where:
*   `[YourDomain]` is the base URL of this website.
*   `[api_identifier]` is the unique identifier provided when you saved the API (e.g., `api_a1b2c3d4`).

**Passing Parameters:**

Field values are passed as URL query parameters (GET request) or as form data (POST request). The endpoint accepts both.

*   **Field Names:** Use the sanitized field names (lowercase, underscores for spaces) that you defined in the API Builder.
*   **Single-entry fields:** Pass the value directly, e.g., `author_name=John Doe`.
*   **Multi-entry fields:** Pass each item as an array parameter, e.g., `tags[]=general&tags[]=update&tags[]=news`.

**Example Call (GET request):**

Assuming:
*   Your domain is `example.com`
*   API Identifier is `api_charstats`
*   API Fields defined:
    *   `character_name` (single)
    *   `hit_points` (single)
    *   `abilities` (multi-entry)

The call might look like:
`https://example.com/api/call/api_charstats?character_name=Sir%20Reginald&hit_points=150&abilities[]=Strength&abilities[]=Shield%20Bash`

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
    *   API Identifier: `api_sample123`

3.  **External Call (GET):**
    `https://[YourDomain]/api/call/api_sample123?username=JaneDev&status_message=Coding%20away!&user_interests[]=PHP&user_interests[]=JavaScript&user_interests[]=Hiking`

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

Remember to replace `[YourDomain]` and `[api_identifier]` with your actual site URL and the specific API identifier you are using.
You can find a list of available APIs and their details (including identifiers and fields) by navigating to the "API" link in the site footer, which leads to this documentation or a dynamic API listing page.
(Self-correction: The last sentence will be true once the footer link points to this MD file, or if a dynamic page is reinstated later. For now, the user gets the identifier upon creation).
The API Caller page on this site (`[YourDomain]/api_caller?api=[api_identifier]`) can also be used to test your API structure and BBCode output.
