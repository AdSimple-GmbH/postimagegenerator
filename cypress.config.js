// Cypress configuration for local WordPress running in Docker
// Code comments are in English per project rule.

const { defineConfig } = require('cypress');

module.exports = defineConfig({
	e2e: {
		baseUrl: 'http://localhost:8080',
		supportFile: 'cypress/support/e2e.js',
		specPattern: 'cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
		video: false,
		screenshotOnRunFailure: true,
		screenshotsFolder: 'cypress/screenshots',
		defaultCommandTimeout: 8000,
		requestTimeout: 180000, // 3 minutes for API calls
		responseTimeout: 180000,
		env: {
			wpAdminPath: '/wp-admin/',
			wpLoginPath: '/wp-login.php'
		},
        // Retry failed tests (useful for flaky external API calls)
        retries: {
            runMode: 2,
            openMode: 0
        }
	}
});



