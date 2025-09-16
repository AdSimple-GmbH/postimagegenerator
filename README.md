# AI Featured Image - WordPress Plugin

AI Featured Image adds AI powered featured images to WordPress posts. Editors can generate multiple proposals right from the post screen and store the selected image in the media library. Optional automation generates an image on first publish so every post ships with a visual instantly.

## Features
- Manual image generation inside both the block editor and the classic editor
- Configurable defaults for image size, output format, model style and the number of proposals
- Optional automation that runs on first publish and only if a featured image is missing
- Images are uploaded to the WordPress media library and attached to the current post
- Uses OpenAI `gpt-image-1` with prompts built from the post title and summary

## Requirements
- WordPress with the ability to install custom plugins
- PHP 7.4 or higher (matching current WordPress requirements)
- An active OpenAI API key with access to the Images API

## Installation
1. Copy the plugin directory into `wp-content/plugins/ai-featured-image` of your WordPress site.
2. Log in to the WordPress dashboard and go to `Plugins > Installed Plugins`.
3. Activate **AI Featured Image**.

## Configuration
1. Navigate to `Settings > AI Featured Image`.
2. Enter your OpenAI API key. Without it no image generation can run.
3. Adjust the default options as needed:
   - **Image Dimensions**: choose from 1024x1024, 1024x1536 or 1536x1024.
   - **File Format**: output JPEG or PNG files.
   - **Quality Presets**: define which quality levels are offered to editors.
   - **Available Styles/Moods**: provide a comma separated list that is shown as guidance in the editor UI.
   - **Render Style (gpt-image-1)**: select vivid or natural for the OpenAI style parameter.
   - **Number of Images**: default number of proposals when opening the modal (1-4).
   - **Automation**: enable automatic generation on publish and decide whether it should only run when no featured image is present.
4. Click **Save Settings**.

## Using the plugin
### Manual generation
- Open a post in the editor and locate the featured image panel.
- Click **AI Beitragsbild festlegen** (classic editor) or the AI button in the block editor panel.
- A modal opens where you can adjust the number of images to request and start generation.
- Once the previews load, click one to select it and choose **Set as Featured Image**.
- The selected image is uploaded to the media library, assigned to the post and the modal closes.

### Automatic generation on publish
- When **Auto-generate on publish** is enabled the plugin calls OpenAI as soon as a post is published for the first time.
- The generated image is stored in the media library and set as featured image automatically.
- If **Only if no featured image is set** is active, automation respects any manually selected thumbnails.
- Failures during the publish hook are silent; check the post afterwards and trigger manual generation if needed.

## Development environment (Docker)
A Docker Compose setup is included for local development and testing on Windows.

1. Copy `.env.example` to `.env` and adjust the values if required.
2. Start the stack:
   ```powershell
   docker compose up -d
   ```
   - WordPress: http://localhost:8080
   - phpMyAdmin: http://localhost:8081 (Host: db, User: wordpress, Pass: wordpress)
3. Install WordPress via WP-CLI on the first run:
   ```powershell
   docker compose run --rm wpcli wp core install `
     --url=http://localhost:8080 `
     --title="Local WP" `
     --admin_user=admin `
     --admin_password=admin `
     --admin_email=admin@example.com `
     --skip-email
   ```
4. Activate the plugin inside the container:
   ```powershell
   docker compose run --rm wpcli wp plugin activate ai-featured-image
   ```
5. Helpful WP-CLI commands:
   ```powershell
   docker compose run --rm wpcli wp plugin list
   docker compose run --rm wpcli wp rewrite flush --hard
   ```
6. Shut down the stack when finished:
   ```powershell
   docker compose down
   ```

## Troubleshooting
- Ensure the OpenAI API key has quota for the Images API; otherwise requests return an error.
- If previews remain empty, check the browser console for blocked responses or mixed-content issues.
- On publish, long running requests may time out on cheap hosting. Use manual generation in these cases.

