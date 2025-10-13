// Common Cypress support for E2E
// Code comments are in English per project rule.

/**
 * Logs into WordPress using UI with session caching.
 * Requires env variables: wpUsername, wpPassword. Falls back to cypress.env.json if present.
 * Navigates to /wp-admin/ and ensures dashboard is visible.
 */
Cypress.Commands.add('wpLogin', () => {
	const username = Cypress.env('wpUsername');
	const password = Cypress.env('wpPassword');
	const adminPath = Cypress.env('wpAdminPath') || '/wp-admin/';
	const loginPath = Cypress.env('wpLoginPath') || '/wp-login.php';

	if (!username || !password) {
		throw new Error('Missing Cypress env vars: wpUsername/wpPassword');
	}

	cy.session([username, password], () => {
		cy.visit(loginPath);
		
		// Wait for login form to be visible
		cy.get('input#user_login', { timeout: 10000 }).should('be.visible');
		
		// Perform login
		cy.get('input#user_login').clear().type(username, { log: false });
		cy.get('input#user_pass').clear().type(password, { log: false });
		cy.get('input#wp-submit').click();
		
		// Wait for redirect to admin
		cy.url().should('include', '/wp-admin');
		cy.get('#wpadminbar', { timeout: 10000 }).should('be.visible');
	}, {
		validate() {
			// Check if still logged in
			cy.request(adminPath).its('status').should('eq', 200);
		}
	});

	// After session is restored, visit admin
	cy.visit(adminPath);
	cy.get('#wpadminbar', { timeout: 10000 }).should('be.visible');
});

