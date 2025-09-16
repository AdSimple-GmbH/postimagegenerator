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

