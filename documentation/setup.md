# Plugin Configuration Reference

Use this document as a quick reference when configuring AI Featured Image in WordPress.

## Settings screen
All settings live under `Settings > AI Featured Image`.

- **OpenAI API Key**: required for any communication with the OpenAI Images API. Keys are stored in the WordPress options table; rotate them periodically.
- **Image Dimensions**: default size sent to `gpt-image-1`. The plugin allows 1024x1024, 1024x1536 (portrait) and 1536x1024 (landscape).
- **File Format**: determines the format of the uploaded file when base64 data is returned. When OpenAI responds with a remote URL the original format is preserved.
- **Quality Presets**: list of checkboxes offered to editors. The selection is purely informational today but can be extended server side if needed.
- **Available Styles/Moods**: comma separated helper text rendered in the editor modal so authors know which styles are approved for the site.
- **Render Style (gpt-image-1)**: selects the OpenAI rendering style (`vivid` or `natural`).
- **Number of Images**: initial value shown in the modal. Editors can still change it before each generation.
- **Auto-generate on publish**: if enabled the plugin requests one image on the first publish of a post.
- **Only if no featured image is set**: prevents automation from overwriting a manually selected thumbnail.

## Editor workflow
1. Editors open the post and trigger the **AI Beitragsbild festlegen** button in the featured image panel.
2. The modal uses AJAX calls to request up to four proposals using the configured defaults.
3. Thumbnails are displayed immediately, including base64 data when returned by the API so no additional fetch is required.
4. Selecting a thumbnail enables **Set as Featured Image**; uploading compresses data URLs to JPEG when possible before storing.
5. After upload the media item is attached to the post and the editor refreshes to show the new thumbnail.

## Automation flow
- Automation hooks into `transition_post_status` and only fires when moving from a non published status to `publish`.
- The routine respects the `Only if no featured image is set` toggle.
- Errors from the OpenAI request abort silently to avoid blocking publishing; review server logs when debugging.
- Images are stored in the media library via `media_handle_sideload`/`media_sideload_image`, mirroring manual uploads.

## Troubleshooting checklist
- Confirm the AJAX nonce is valid when customising the modal or moving the button.
- Increase PHP execution time if long running uploads fail on slower hosting.
- When running locally ensure outbound HTTPS requests to `api.openai.com` are allowed.
- If automation never fires, verify the post type supports thumbnails and the status transition matches the expected workflow.

# End-to-end Tests (Cypress)

The project includes a minimal Cypress setup to verify WordPress admin login locally.

## Prerequisites
- Docker-based WordPress is running and accessible at `http://localhost:8080`.
- Default credentials are available (see below) or set your own via environment variables.

## Configuration
- `cypress.config.js` sets `baseUrl` to `http://localhost:8080` and defines default paths for `wp-admin` and `wp-login.php`.
- `cypress.env.json` holds local defaults:

  ```json
  {
    "wpUsername": "admin",
    "wpPassword": "admin",
    "wpAdminPath": "/wp-admin/",
    "wpLoginPath": "/wp-login.php"
  }
  ```

  You can override these with OS env vars, for example:

  ```bash
  set CYPRESS_wpUsername=myuser
  set CYPRESS_wpPassword=mypass
  ```

## Commands and Specs
- Reusable login command defined in `cypress/support/e2e.js` as `cy.wpLogin()`.
- Login spec located at `cypress/e2e/wp-admin-login.cy.js`.

## Run tests
- Interactive: `npm run cypress:open`
- Headless: `npm run test:e2e`

The login test visits `/wp-admin/`, authenticates with the provided credentials if required, and asserts that the WordPress admin bar is visible.

# REST API - AI Post Generation with Length Correction

The plugin provides a REST API endpoint to generate AI-powered blog posts with automatic length correction.

## Endpoint

**POST** `/wp-json/ai-featured-image/v1/generate-post`

### Authentication

Requires WordPress authentication with `edit_posts` capability. Use one of:
- Cookie-based authentication (logged-in user)
- Application passwords
- JWT tokens (with appropriate plugin)

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `post_id` | integer | Yes | - | WordPress post ID to generate content for |
| `length` | string | No | `short` | Content length: `short`, `medium`, `long`, `verylong` |
| `auto_correct` | boolean | No | `true` | Enable automatic length correction |
| `max_corrections` | integer | No | `2` | Maximum correction attempts (0-3) |

### Length Specifications

| Length | Target Words | Tolerance |
|--------|-------------|-----------|
| `short` | 300-500 | ±10% |
| `medium` | 800-1200 | ±10% |
| `long` | 1500-2000 | ±10% |
| `verylong` | 2500-3000 | ±10% |

### Response

```json
{
  "success": true,
  "data": {
    "content_html": "<h2>Einleitung</h2><p>...</p>",
    "category_id": 5,
    "category_name": "Technologie",
    "tags": ["AI", "Innovation", "Zukunft", "..."],
    "word_count": {
      "initial": 280,
      "final": 420,
      "target_min": 300,
      "target_max": 500,
      "valid": true,
      "message": "Word count valid: 420 words (target: 300-500)"
    },
    "corrections": {
      "enabled": true,
      "made": 1,
      "max_allowed": 2,
      "history": [
        {
          "attempt": 1,
          "before_words": 280,
          "after_words": 420,
          "direction": "expand"
        }
      ]
    }
  }
}
```

### Error Response

```json
{
  "code": "api_key_missing",
  "message": "OpenAI API key is not set.",
  "data": {
    "status": 400
  }
}
```

## How Length Correction Works

1. **Initial Generation**: GPT-4o generates content with specific word count instructions
2. **Validation**: Word count is checked against target range with 10% tolerance
3. **Automatic Correction** (if enabled and needed):
   - **Too short**: GPT expands existing sections with more details and examples
   - **Too long**: GPT shortens content while preserving key information
4. **Iterative Process**: Up to `max_corrections` attempts until valid or limit reached
5. **Result**: Returns final content with detailed word count statistics and correction history

## Usage Examples

### Example 1: Basic Request (with auto-correction)

```bash
curl -X POST https://localhost:8080/wp-json/ai-featured-image/v1/generate-post \
  -H "Content-Type: application/json" \
  -u admin:admin \
  -d '{
    "post_id": 123,
    "length": "medium"
  }'
```

### Example 2: Disable Auto-Correction

```bash
curl -X POST https://localhost:8080/wp-json/ai-featured-image/v1/generate-post \
  -H "Content-Type: application/json" \
  -u admin:admin \
  -d '{
    "post_id": 123,
    "length": "long",
    "auto_correct": false
  }'
```

### Example 3: Maximum Corrections

```bash
curl -X POST https://localhost:8080/wp-json/ai-featured-image/v1/generate-post \
  -H "Content-Type: application/json" \
  -u admin:admin \
  -d '{
    "post_id": 123,
    "length": "verylong",
    "auto_correct": true,
    "max_corrections": 3
  }'
```

### Example 4: Using JavaScript/Fetch

```javascript
// Get WordPress nonce and user session first (when logged in)
fetch('/wp-json/ai-featured-image/v1/generate-post', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce // WordPress nonce from wp_localize_script
  },
  body: JSON.stringify({
    post_id: 123,
    length: 'medium',
    auto_correct: true,
    max_corrections: 2
  })
})
.then(response => response.json())
.then(data => {
  console.log('Word count:', data.data.word_count);
  console.log('Corrections made:', data.data.corrections.made);
  console.log('Content:', data.data.content_html);
});
```

### Example 5: Test with WP-CLI inside Docker

```bash
# Access WordPress container
docker exec -it postimagegenerator-wordpress-1 bash

# Create test post
wp post create \
  --post_title="KI und maschinelles Lernen" \
  --post_status=draft \
  --user=admin \
  --porcelain

# Note the post ID (e.g., 456) and use it in curl command above
```

## Testing the Feature

### Step 1: Create a Draft Post

1. Log into WordPress: http://localhost:8080/wp-admin
2. Create a new post with a title (e.g., "Künstliche Intelligenz")
3. Save as draft and note the post ID from the URL

### Step 2: Call the API

Use curl, Postman, or browser console to make a POST request:

```bash
curl -X POST http://localhost:8080/wp-json/ai-featured-image/v1/generate-post \
  -H "Content-Type: application/json" \
  -u admin:YOUR_PASSWORD \
  -d '{
    "post_id": YOUR_POST_ID,
    "length": "medium",
    "auto_correct": true,
    "max_corrections": 2
  }'
```

### Step 3: Review Results

The response includes:
- Generated HTML content
- Initial vs. final word counts
- Whether corrections were needed
- Correction history (if any)
- Category and tags

### Step 4: Update Post (Optional)

Use the `content_html` from the response to update your post via WordPress admin or another API call.

## Benefits of Length Correction

✅ **Accuracy**: Ensures generated content matches specified length requirements  
✅ **Consistency**: Reliable word counts across different content types  
✅ **Quality**: Maintains content quality during expansion/shortening  
✅ **Transparency**: Detailed logging of corrections and word count changes  
✅ **Flexibility**: Configurable tolerance and maximum correction attempts  
✅ **Efficiency**: Automatic process reduces manual editing

## Troubleshooting

- **401 Unauthorized**: Check authentication credentials
- **403 Forbidden**: User lacks `edit_posts` capability
- **404 Post Not Found**: Verify post ID exists
- **400 API Key Missing**: Configure OpenAI API key in plugin settings
- **500 API Error**: Check OpenAI API status and error message in logs

## Logging

All API requests and corrections are logged via `log_line()`:
- `rest_api_post_request`: Initial request parameters
- `rest_api_correction_attempt`: Each correction attempt with word counts
- `rest_api_correction_failed`: Failed correction details
- `rest_api_post_complete`: Final results and statistics

Check WordPress uploads directory for `ai-featured-image.log`.

