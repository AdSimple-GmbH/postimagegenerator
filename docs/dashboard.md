# AI Post Generator Dashboard (English)

This dashboard provides a visual way to generate AI-powered posts with automatic length correction.

- Location: WordPress Admin → AI Dashboard
- Create a test post in one click
- Configure length: short, medium, long, verylong
- Enable/disable auto-correction and set max correction attempts (0–3)
- Review results: word counts, validity, correction history, category, tags, and content preview

Notes
- Content is not automatically saved from the dashboard (copy/paste or use WP‑CLI `--save`).
- Statistics are built from `wp-content/uploads/ai-featured-image.log`.
- The Debug panel shows prompts, IDs, edit links, token usage, and excerpts.

See also
- German guide: `../DASHBOARD_ANLEITUNG.md`
- REST API details: `../documentation/setup.md`

