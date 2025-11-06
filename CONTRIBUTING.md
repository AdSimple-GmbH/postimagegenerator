# Contributing

Thanks for contributing! Please follow these guidelines:

- Use English for documentation and code comments
- Follow WordPress coding standards (PHPCS)
- Security first: escape output, sanitize input, verify nonces, check capabilities
- Write/update documentation for new features (README and docs/)
- Add tests where possible (PHPUnit or Cypress)
- For issues/PRs, describe the problem, steps to reproduce, and proposed solution

## Development

- Docker: see README (Development environment)
- E2E: `npm run test:e2e`
- PHPUnit: see `phpunit.xml.dist` and `tests/bootstrap.php`

## Mocking OpenAI

Set in `wp-config.php` for CI:

```php
define( 'AI_FEATURED_IMAGE_MOCK_OPENAI', true );
```

This intercepts requests to `api.openai.com` and returns deterministic responses.

