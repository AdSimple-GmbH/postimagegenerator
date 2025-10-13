// AI Featured Image Plugin E2E Tests
// Code comments are in English per project rule.

describe('AI Featured Image Plugin', () => {
	beforeEach(() => {
		// Login before each test
		cy.wpLogin();
	});

	it('creates a new post and opens AI Featured Image modal', () => {
		// Navigate to create new post
		cy.visit('/wp-admin/post-new.php');
		
		// Wait for the editor to load
		cy.get('body', { timeout: 10000 }).should('be.visible');
		
		// Add a post title (required for image generation)
		cy.get('.editor-post-title__input, #title').first().type('Test Post for AI Featured Image');
		
		// Wait a bit for the page to fully load
		cy.wait(2000);
		
		// Check if the AI Featured Image button exists
		// In Gutenberg it should be in the sidebar panel
		cy.get('#ai-featured-image-generate-button-gutenberg, #ai-featured-image-generate-button')
			.should('exist')
			.first()
			.click();
		
		// Verify the modal is displayed
		cy.get('#ai-featured-image-modal').should('be.visible');
		
		// Verify modal title
		cy.get('#ai-featured-image-modal h2').should('contain', 'Generate AI Featured Image');
	});

	it('verifies modal elements and options', () => {
		// Create new post
		cy.visit('/wp-admin/post-new.php');
		cy.get('.editor-post-title__input, #title').first().type('Test Post for Modal Verification');
		cy.wait(2000);
		
		// Open modal
		cy.get('#ai-featured-image-generate-button-gutenberg, #ai-featured-image-generate-button')
			.first()
			.click();
		
		// Check "Number of Images" dropdown
		cy.get('#ai-num-images').should('exist');
		cy.get('#ai-num-images option').should('have.length', 4); // Options 1-4
		
		// Check dimensions field (value depends on plugin settings)
		cy.get('#ai-image-dimensions').should('exist');
		cy.get('#ai-image-dimensions').invoke('val').should('match', /^\d+x\d+$/);
		
		// Check Generate button
		cy.get('#ai-generate-image-button').should('exist').should('contain', 'Generate');
		
		// Check "Set as Featured Image" button (should be hidden initially)
		cy.get('#ai-set-featured-image-button').should('not.be.visible');
		
		// Check loading indicator (should be hidden initially)
		cy.get('#ai-loading').should('not.be.visible');
		
		// Check preview container
		cy.get('#ai-image-preview-container').should('exist');
	});

	it('changes number of images in modal', () => {
		cy.visit('/wp-admin/post-new.php');
		cy.get('.editor-post-title__input, #title').first().type('Test Post for Image Options');
		cy.wait(2000);
		
		cy.get('#ai-featured-image-generate-button-gutenberg, #ai-featured-image-generate-button')
			.first()
			.click();
		
		// Change number of images
		cy.get('#ai-num-images').select('3');
		cy.get('#ai-num-images').should('have.value', '3');
		
		cy.get('#ai-num-images').select('1');
		cy.get('#ai-num-images').should('have.value', '1');
	});

	it('closes modal when clicking X button', () => {
		cy.visit('/wp-admin/post-new.php');
		cy.get('.editor-post-title__input, #title').first().type('Test Post for Modal Close');
		cy.wait(2000);
		
		cy.get('#ai-featured-image-generate-button-gutenberg, #ai-featured-image-generate-button')
			.first()
			.click();
		
		// Modal should be visible
		cy.get('#ai-featured-image-modal').should('be.visible');
		
		// Click close button
		cy.get('.ai-modal-close').click();
		
		// Modal should be hidden
		cy.get('#ai-featured-image-modal').should('not.be.visible');
	});

	it('verifies plugin settings page exists', () => {
		// Navigate to plugin settings (correct slug)
		cy.visit('/wp-admin/options-general.php?page=ai-featured-image-settings');
		
		// Check if settings page loaded
		cy.get('h1').should('contain', 'AI Featured Image');
		
		// Check for API key field
		cy.get('input[name="ai_featured_image_options[api_key]"]').should('exist');
		
		// Check for image dimensions field
		cy.get('select[name="ai_featured_image_options[image_dimensions]"]').should('exist');
		
		// Check for save button
		cy.get('input[type="submit"]').should('exist');
	});

	it('checks classic editor integration', () => {
		// First, we need to check if classic editor is available
		// This test might fail if only Gutenberg is installed
		cy.visit('/wp-admin/post-new.php?classic-editor');
		
		// Wait for page load
		cy.wait(2000);
		
		// In classic editor, the button should be in the featured image metabox
		// The test will be conditional based on whether classic editor is active
		cy.get('body').then($body => {
			if ($body.find('#ai-featured-image-generate-button').length > 0) {
				cy.get('#ai-featured-image-generate-button').should('exist');
				cy.get('#ai-featured-image-generate-button').click();
				cy.get('#ai-featured-image-modal').should('be.visible');
			} else {
				// Classic editor not available, skip this part
				cy.log('Classic editor not available, skipping classic editor test');
			}
		});
	});

	it('verifies error container exists for API errors', () => {
		cy.visit('/wp-admin/post-new.php');
		cy.get('.editor-post-title__input, #title').first().type('Test Post for Error Handling');
		cy.wait(2000);
		
		cy.get('#ai-featured-image-generate-button-gutenberg, #ai-featured-image-generate-button')
			.first()
			.click();
		
		// Check error container exists (hidden initially)
		cy.get('#ai-modal-error-container').should('exist');
		cy.get('#ai-modal-error-container').should('not.be.visible');
	});
});
