# Cypress Test Documentation

## Overview

This directory contains comprehensive end-to-end tests for the AI Featured Image Generator plugin, with special focus on the new Dashboard, REST API, and Prompt Management features.

## Test Files

### 1. `ai-dashboard.cy.js` - Dashboard Tests
Tests the new AI Post Generator Dashboard with all its features:

- ✅ Dashboard loading and UI elements
- ✅ Statistics display
- ✅ Prompt management section
- ✅ Test post creation
- ✅ Content generation for all lengths (short, medium, long, verylong)
- ✅ Auto-correction functionality
- ✅ Debug panel with OpenAI communication details
- ✅ Prompt edit links
- ✅ Model information display

**Key Features Tested:**
- Dashboard loads correctly with all sections
- Statistics are displayed and can be refreshed
- Can create test posts via dashboard button
- Generates content via REST API for all length options
- Auto-correction works and respects max_corrections setting
- Debug panel displays request/response details
- Prompt links are clickable and point to correct pages
- Word counts are validated against target ranges

**Run Time:** ~15-30 minutes (includes 4 full content generation tests)

### 2. `ai-rest-api.cy.js` - REST API Tests
Tests the new REST API endpoint `/ai-featured-image/v1/generate-post`:

- ✅ Endpoint accessibility
- ✅ Response structure validation
- ✅ Debug information completeness
- ✅ All length options (short, medium, long, verylong)
- ✅ Auto-correction enabled/disabled
- ✅ Max corrections limit enforcement
- ✅ Error handling (invalid post ID, invalid parameters)
- ✅ Model information (GPT-5 family usage)
- ✅ Temperature handling (default for GPT-5)
- ✅ Token usage reporting
- ✅ Category and tag generation
- ✅ Correction debug information

**Key Features Tested:**
- REST API returns proper structure with success/data
- Debug info includes model, prompts, tokens, and edit links
- Corrections are tracked with history and debug details
- GPT-5 models correctly use default temperature (1)
- Prompt IDs and edit links are provided
- Word counts are validated
- Error responses for invalid inputs

**Run Time:** ~20-40 minutes (includes multiple API calls)

### 3. `ai-prompt-management.cy.js` - Prompt Management Tests
Tests the Custom Post Type (CPT) based prompt management:

- ✅ AI Prompts menu visibility
- ✅ Default prompts exist
- ✅ Custom columns (Slug, Typ, Modell, Status)
- ✅ Creating new prompts
- ✅ GPT model options including GPT-5 variants
- ✅ Prompt type taxonomy
- ✅ Default GPT parameters
- ✅ Parameter descriptions and recommendations
- ✅ Editing existing prompts
- ✅ Test function in editor
- ✅ Field validation
- ✅ Active/inactive status toggle
- ✅ JSON variants support
- ✅ Model pricing information
- ✅ Meta-to-taxonomy synchronization

**Key Features Tested:**
- CPT menu and list page work correctly
- All required default prompts are created on activation
- Can create and edit prompts with all fields
- GPT-5 model variants are available
- Default values are sensible
- Validation prevents invalid data
- Taxonomy syncs with meta field
- Variants support multiple prompt lengths

**Run Time:** ~5-10 minutes

### 4. `ai-post-direct-api-call.cy.js` - AJAX API Tests (Existing)
Tests the legacy AJAX endpoint for backward compatibility.

### 5. `ai-featured-image-plugin.cy.js` - Plugin Tests (Existing)
Tests basic plugin functionality and image generation.

## Running the Tests

### Prerequisites

1. **Docker Environment Running:**
   ```bash
   docker-compose up -d
   ```

2. **WordPress Configured:**
   - Admin user: `admin`
   - Admin password: Set in `cypress.env.json`
   - OpenAI API Key: Configured in plugin settings

3. **Cypress Installed:**
   ```bash
   npm install cypress --save-dev
   ```

### Run All Tests

```bash
npx cypress run
```

### Run Specific Test File

```bash
# Dashboard tests
npx cypress run --spec "cypress/e2e/ai-dashboard.cy.js"

# REST API tests
npx cypress run --spec "cypress/e2e/ai-rest-api.cy.js"

# Prompt management tests
npx cypress run --spec "cypress/e2e/ai-prompt-management.cy.js"
```

### Run Tests in Interactive Mode

```bash
npx cypress open
```

This opens the Cypress Test Runner where you can:
- Select individual tests to run
- Watch tests execute in real browser
- Debug failures with DevTools
- View screenshots and videos

### Run Only New Feature Tests

```bash
npx cypress run --spec "cypress/e2e/ai-dashboard.cy.js,cypress/e2e/ai-rest-api.cy.js,cypress/e2e/ai-prompt-management.cy.js"
```

## Test Configuration

### Timeouts

Due to OpenAI API calls, tests have extended timeouts:

- **Default Command Timeout:** 8 seconds
- **Request Timeout:** 180 seconds (3 minutes)
- **Response Timeout:** 180 seconds (3 minutes)
- **Individual Test Timeout:** Up to 300 seconds (5 minutes) for generation tests

### Retries

- **Run Mode:** 1 retry on failure
- **Open Mode:** 0 retries (for debugging)

### Environment Variables

Set in `cypress.env.json`:

```json
{
  "wpUsername": "admin",
  "wpPassword": "your_password_here"
}
```

## Expected Test Results

### Success Criteria

All tests should pass with:

- ✅ Dashboard loads and all UI elements present
- ✅ Content generation works for all lengths
- ✅ Auto-correction improves word count accuracy
- ✅ Debug panel shows complete OpenAI communication
- ✅ REST API returns valid structured responses
- ✅ Prompt management allows CRUD operations
- ✅ GPT-5 models use correct temperature settings
- ✅ All required prompts exist and are active

### Common Failures and Solutions

**1. API Key Not Configured**
```
Error: OpenAI API key is not set
```
**Solution:** Configure API key in WordPress Settings → AI Featured Image

**2. Timeout on Content Generation**
```
Error: Timeout of 180000ms exceeded
```
**Solution:** 
- Check OpenAI API status
- Increase timeout in test
- Verify network connectivity

**3. Word Count Out of Range**
```
Error: expected 280 to be at least 300
```
**Solution:** This is expected occasionally due to GPT variance. Test allows 20% tolerance and uses auto-correction.

**4. Missing Prompts**
```
Error: Prompt "system-post-generation" nicht gefunden
```
**Solution:** Deactivate and reactivate plugin to trigger `setup_default_prompts()`

**5. Temperature Error (GPT-5)**
```
Error: temperature does not support 0.2
```
**Solution:** Already fixed - GPT-5 models now skip temperature parameter

## Test Coverage

### Features Tested

| Feature | Dashboard | REST API | Prompt Mgmt | Coverage |
|---------|-----------|----------|-------------|----------|
| Content Generation | ✅ | ✅ | - | 100% |
| Length Options | ✅ | ✅ | - | 100% |
| Auto-Correction | ✅ | ✅ | - | 100% |
| Debug Information | ✅ | ✅ | - | 100% |
| Prompt Management | ✅ | - | ✅ | 100% |
| GPT-5 Support | ✅ | ✅ | ✅ | 100% |
| Error Handling | ✅ | ✅ | ✅ | 100% |
| Temperature Handling | ✅ | ✅ | - | 100% |
| Token Usage | ✅ | ✅ | - | 100% |
| Categories/Tags | ✅ | ✅ | - | 100% |

### API Endpoints Tested

- ✅ `POST /wp-json/ai-featured-image/v1/generate-post` (REST API)
- ✅ `POST /wp-admin/admin-ajax.php?action=generate_ai_post` (AJAX)
- ✅ `POST /wp-admin/admin-ajax.php?action=ai_dashboard_test_generation` (Dashboard)
- ✅ `POST /wp-admin/admin-ajax.php?action=ai_dashboard_create_test_post` (Dashboard)
- ✅ `POST /wp-admin/admin-ajax.php?action=ai_dashboard_get_stats` (Dashboard)

### UI Components Tested

- ✅ AI Dashboard page
- ✅ AI Prompts list page
- ✅ AI Prompt editor (new/edit)
- ✅ Dashboard test configuration form
- ✅ Dashboard results display
- ✅ Dashboard statistics
- ✅ Dashboard prompt management table
- ✅ Debug panel (collapsible)
- ✅ Prompt meta boxes

## Debugging Failed Tests

### View Screenshots

Failed tests automatically capture screenshots:
```
cypress/screenshots/[test-file]/[test-name].png
```

### Enable Video Recording

In `cypress.config.js`:
```javascript
video: true,
videosFolder: 'cypress/videos',
```

### View Detailed Logs

Run with debug output:
```bash
DEBUG=cypress:* npx cypress run
```

### Check WordPress Logs

```bash
docker exec -it postimagegenerator-wordpress-1 cat /var/www/html/wp-content/uploads/ai-featured-image.log
```

### Inspect Network Requests

In Cypress Test Runner (interactive mode):
1. Open Chrome DevTools
2. Network tab shows all requests
3. Console shows `cy.log()` messages

## Continuous Integration

### GitHub Actions Example

```yaml
name: Cypress Tests

on: [push, pull_request]

jobs:
  cypress:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Start Docker Compose
        run: docker-compose up -d
        
      - name: Wait for WordPress
        run: sleep 30
        
      - name: Run Cypress Tests
        uses: cypress-io/github-action@v5
        with:
          wait-on: 'http://localhost:8080'
          wait-on-timeout: 120
          
      - name: Upload Screenshots
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: cypress-screenshots
          path: cypress/screenshots
```

## Test Maintenance

### Updating Tests After Code Changes

1. **New GPT Model Added:**
   - Update `ai-prompt-management.cy.js` to check for new model option
   - Update model assertions in `ai-rest-api.cy.js`

2. **New Prompt Type Added:**
   - Update default prompts check in `ai-prompt-management.cy.js`
   - Update required prompts array in `ai-dashboard.cy.js`

3. **Word Count Ranges Changed:**
   - Update `expectations` object in both dashboard and REST API tests
   - Adjust tolerance if needed

4. **New Dashboard Feature:**
   - Add test case to `ai-dashboard.cy.js`
   - Update UI element assertions

### Best Practices

- ✅ Use `cy.log()` to document test flow
- ✅ Use descriptive test names
- ✅ Keep tests independent (no shared state)
- ✅ Use `beforeEach()` for common setup
- ✅ Clean up test data when possible
- ✅ Handle async operations with proper waits
- ✅ Assert on both success and error cases
- ✅ Test edge cases (empty, max, invalid values)

## Troubleshooting

### Tests Pass Locally but Fail in CI

- Check Docker container startup timing
- Verify API key is set in CI environment
- Increase wait times for slower CI runners
- Check network connectivity to OpenAI API

### Flaky Tests

- Increase timeouts for API-dependent tests
- Add explicit waits for UI elements
- Use `cy.wait()` after state-changing actions
- Retry failed tests (configured in `cypress.config.js`)

### Performance Issues

- Run tests in headless mode
- Disable video recording
- Run specific test files instead of all
- Use faster test data (short length instead of verylong)

## Contributing

When adding new tests:

1. Follow existing naming conventions
2. Add comments explaining test purpose
3. Update this documentation
4. Ensure tests pass locally
5. Add appropriate timeouts for API calls
6. Handle both success and failure cases

## Support

For test-related issues:
- Check Cypress documentation: https://docs.cypress.io
- Review test output and screenshots
- Enable debug logging
- Check WordPress error logs

---

**Last Updated:** October 2025
**Cypress Version:** 13.x
**Plugin Version:** 1.0.0



