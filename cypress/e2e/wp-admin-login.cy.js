// WordPress admin login e2e

describe('WordPress Admin Login', () => {
	it('logs in and lands on Dashboard', () => {
		cy.wpLogin();
		cy.location('pathname').should('match', /\/wp-admin(\/|\/index\.php)?$/);
		cy.get('#wpadminbar').should('be.visible');
	});
});


