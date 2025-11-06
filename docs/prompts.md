# Prompt Management (English)

The plugin manages prompts via a custom post type `AI Prompts`:

- Prompt types: `system_generation`, `generation` (with variants), `correction_expand`, `correction_shorten`, `system_correction`, `image`
- Per-prompt parameters: model, temperature, max tokens, response format (`text` or `json_object`)
- Variant JSON for different lengths (short/medium/long/verylong)
- Built-in validation and a one-click Test action

Variables available for replacement:
- `{post_title}`, `{post_excerpt}`, `{post_content}`
- `{min_words}`, `{max_words}`, `{current_words}`, `{length}`
- Editorial variables from settings: `{editorial_line}`, `{author_style}`, `{target_audience}`

Tips
- Keep prompts explicit about length and structure
- Use `json_object` for post generation (expected fields: `content_html`, `category_name`, `tags`)
- Store length-specific content in `variants` JSON for the `post-generation` prompt

See also
- German reference: `../PROMPT_MANAGEMENT.md`
- Dashboard guide: `./dashboard.md`

