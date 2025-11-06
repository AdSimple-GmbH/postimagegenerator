=== AI Featured Image ===
Contributors: andreasostheimer
Tags: ai, openai, featured image, gpt, content generation
Requires at least: 6.3
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate AI-powered featured images and full blog posts with optional length correction. Includes a dashboard, REST API, WP‑CLI and detailed logging.

== Description ==

AI Featured Image lets editors generate multiple image proposals and set them as the featured image. The plugin can also generate full blog posts with target length and automatic correction.

Features:
- Image generation (manual and on first publish)
- AI Post Generator with length validation and correction
- Prompt management via custom post type (with variants and testing)
- REST API and WP‑CLI commands
- Debug logs and post meta summaries

== Installation ==
1. Upload the plugin to `/wp-content/plugins/ai-featured-image`.
2. Activate it in the Plugins screen.
3. Configure your OpenAI API key under Settings → AI Featured Image.

== Frequently Asked Questions ==

= Where are images stored? =
In the WordPress media library, attached to the current post.

= Do I need an OpenAI key? =
Yes. Provide it in the settings or via `OPENAI_API_KEY` in `wp-config.php`.

== Changelog ==

= 1.0.0 =
Initial release.


