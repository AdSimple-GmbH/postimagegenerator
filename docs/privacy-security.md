# Security & Privacy (English)

This plugin calls the OpenAI API to generate images and text. Review the following guidelines before using it in production.

## Data sent to OpenAI
- Image prompts include post title and a short excerpt (no media files are uploaded by default).
- Post generation prompts include title, excerpt and strict length/structure instructions.
- Do not include secrets or personal data in titles/excerpts.

## API Key Handling
- API key is stored in WordPress options or can be provided via `OPENAI_API_KEY` constant in `wp-config.php`.
- Keys are never written to logs and are only sent as HTTP `Authorization: Bearer` header to `api.openai.com`.

## Logs
- Structured logs are stored at `wp-content/uploads/ai-featured-image.log`.
- Logs contain timestamps, post IDs, length parameters, validation/corrections, and debug excerpts.
- Rotate or disable logs per your compliance needs.

## Permissions & Nonces
- All admin-ajax endpoints use nonces and capability checks.
- REST endpoint requires authenticated users with `edit_posts` capability.

## Recommendations
- Limit user roles that can trigger generation.
- Configure editorial line, author style and target audience to prevent unsafe output.
- Review generated content before publishing.

