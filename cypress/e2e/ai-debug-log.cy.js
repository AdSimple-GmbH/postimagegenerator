// AI Debug Log Tests
// Tests for debug logging and full prompt storage in custom fields
// Code comments are in English per project rule.

describe('AI Debug Log Feature', () => {
	let testPostId;

	beforeEach(() => {
		cy.wpLogin();
	});

	it('should save debug log as custom field when generating via REST API', function() {
		this.timeout(300000); // 5 minutes

		// Create a test post
		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		const title = `Debug Test - ${Date.now()}`;
		cy.get('#title').type(title);
		cy.get('#save-post').click();
		cy.wait(3000);
		
		cy.get('#post_ID').invoke('val').then((postId) => {
			testPostId = postId;
			cy.log(`Created test post ID: ${testPostId}`);

			// Generate content via REST API
			cy.request({
				method: 'POST',
				url: '/wp-json/ai-featured-image/v1/generate-post',
				body: {
					post_id: testPostId,
					length: 'short',
					auto_correct: true,
					max_corrections: 2
				},
				timeout: 180000
			}).then((response) => {
				expect(response.status).to.eq(200);
				expect(response.body.success).to.be.true;

				cy.log('✅ Content generated successfully');

				// Verify debug info is in response
				expect(response.body.data.debug).to.exist;
				expect(response.body.data.debug.initial_generation).to.exist;

				// Check for full prompts in debug
				expect(response.body.data.debug.initial_generation.request).to.have.property('system_prompt_full');
				expect(response.body.data.debug.initial_generation.request).to.have.property('user_prompt_full');

				// Verify full prompts are not empty
				expect(response.body.data.debug.initial_generation.request.system_prompt_full).to.not.be.empty;
				expect(response.body.data.debug.initial_generation.request.user_prompt_full).to.not.be.empty;

				// Verify full prompts are longer than preview
				expect(response.body.data.debug.initial_generation.request.system_prompt_full.length)
					.to.be.greaterThan(response.body.data.debug.initial_generation.request.system_prompt.length);

				cy.log('✅ Debug info contains full prompts');
			});
		});
	});

	it('should display debug meta box in post editor', function() {
		this.timeout(300000);

		// Generate a post first
		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		const title = `Meta Box Test - ${Date.now()}`;
		cy.get('#title').type(title);
		cy.get('#save-post').click();
		cy.wait(3000);
		
		cy.get('#post_ID').invoke('val').then((postId) => {
			// Generate via REST API
			cy.request({
				method: 'POST',
				url: '/wp-json/ai-featured-image/v1/generate-post',
				body: {
					post_id: postId,
					length: 'short',
					auto_correct: false,
					max_corrections: 0
				},
				timeout: 180000
			}).then(() => {
				// Reload the editor
				cy.visit(`/wp-admin/post.php?post=${postId}&action=edit`);
				cy.wait(3000);

				// Check for debug meta box
				cy.get('#ai_debug_info').should('exist');
				cy.get('#ai_debug_info').should('contain', 'AI Debug-Informationen');

				// Check for summary section
				cy.get('.ai-debug-summary').should('exist');
				cy.get('.ai-debug-summary').should('contain', 'Generierungs-Zusammenfassung');

				// Verify summary contains key information
				cy.get('.ai-debug-summary').within(() => {
					cy.contains('Modell').should('exist');
					cy.contains('Länge').should('exist');
					cy.contains('Finale Wortanzahl').should('exist');
					cy.contains('Status').should('exist');
				});

				// Check for expandable sections
				cy.get('details').should('have.length.greaterThan', 0);

				cy.log('✅ Debug meta box displayed correctly');
			});
		});
	});

	it('should allow exporting debug log as JSON from meta box', function() {
		this.timeout(300000);

		// Generate a post
		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		cy.get('#title').type(`JSON Export Test - ${Date.now()}`);
		cy.get('#save-post').click();
		cy.wait(3000);
		
		cy.get('#post_ID').invoke('val').then((postId) => {
			cy.request({
				method: 'POST',
				url: '/wp-json/ai-featured-image/v1/generate-post',
				body: {
					post_id: postId,
					length: 'short',
					auto_correct: false,
					max_corrections: 0
				},
				timeout: 180000
			}).then(() => {
				cy.visit(`/wp-admin/post.php?post=${postId}&action=edit`);
				cy.wait(3000);

				// Find and expand raw JSON section
				cy.contains('summary', 'Raw JSON Export').click();
				cy.wait(500);

				// Verify JSON textarea exists and has content
				cy.get('textarea[readonly]').should('exist');
				cy.get('textarea[readonly]').invoke('val').then((json) => {
					expect(json).to.not.be.empty;
					
					// Verify it's valid JSON
					const parsed = JSON.parse(json);
					expect(parsed).to.have.property('initial_generation');
					expect(parsed.initial_generation).to.have.property('request');
					expect(parsed.initial_generation.request).to.have.property('system_prompt_full');
					expect(parsed.initial_generation.request).to.have.property('user_prompt_full');

					cy.log('✅ JSON export contains full debug data');
				});
			});
		});
	});

	it('should show variant information in debug log', function() {
		this.timeout(300000);

		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		cy.get('#title').type(`Variant Test - ${Date.now()}`);
		cy.get('#save-post').click();
		cy.wait(3000);
		
		cy.get('#post_ID').invoke('val').then((postId) => {
			// Test with different length variants
			const length = 'medium';

			cy.request({
				method: 'POST',
				url: '/wp-json/ai-featured-image/v1/generate-post',
				body: {
					post_id: postId,
					length: length,
					auto_correct: false,
					max_corrections: 0
				},
				timeout: 180000
			}).then((response) => {
				expect(response.body.data.debug.initial_generation.request.user_prompt_variant).to.eq(length);

				cy.visit(`/wp-admin/post.php?post=${postId}&action=edit`);
				cy.wait(3000);

				// Check meta box shows variant
				cy.get('#ai_debug_info').within(() => {
					cy.contains('Länge').parent().should('contain', length);
				});

				cy.log('✅ Variant information displayed correctly');
			});
		});
	});

	it('should track corrections in debug log', function() {
		this.timeout(300000);

		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		cy.get('#title').type(`Corrections Debug Test - ${Date.now()}`);
		cy.get('#save-post').click();
		cy.wait(3000);
		
		cy.get('#post_ID').invoke('val').then((postId) => {
			cy.request({
				method: 'POST',
				url: '/wp-json/ai-featured-image/v1/generate-post',
				body: {
					post_id: postId,
					length: 'medium',
					auto_correct: true,
					max_corrections: 2
				},
				timeout: 180000
			}).then((response) => {
				const corrections = response.body.data.corrections.made;
				
				if (corrections > 0) {
					cy.log(`🔄 ${corrections} corrections were made`);

					// Verify debug has correction entries
					expect(response.body.data.debug.corrections).to.have.length(corrections);

					// Visit editor
					cy.visit(`/wp-admin/post.php?post=${postId}&action=edit`);
					cy.wait(3000);

					// Check meta box shows corrections
					cy.get('#ai_debug_info').within(() => {
						cy.contains('Korrekturen').parent().should('contain', corrections.toString());
					});

					// Expand corrections section if exists
					cy.contains('summary', 'Korrekturen').then($summary => {
						if ($summary.length > 0) {
							cy.wrap($summary).click();
							cy.wait(500);
							
							// Should show correction details
							cy.contains('Modell').should('exist');
							cy.contains('Aktuelle Wörter').should('exist');
							cy.contains('Neue Wörter').should('exist');
						}
					});

					cy.log('✅ Corrections tracked in debug log');
				} else {
					cy.log('ℹ️ No corrections were needed for this post');
				}
			});
		});
	});

	it('should show token usage in debug log', function() {
		this.timeout(300000);

		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		cy.get('#title').type(`Token Usage Test - ${Date.now()}`);
		cy.get('#save-post').click();
		cy.wait(3000);
		
		cy.get('#post_ID').invoke('val').then((postId) => {
			cy.request({
				method: 'POST',
				url: '/wp-json/ai-featured-image/v1/generate-post',
				body: {
					post_id: postId,
					length: 'short',
					auto_correct: false,
					max_corrections: 0
				},
				timeout: 180000
			}).then((response) => {
				// Check token usage in response
				const usage = response.body.data.debug.initial_generation.response.usage;
				
				if (usage) {
					expect(usage).to.have.property('prompt_tokens');
					expect(usage).to.have.property('completion_tokens');
					expect(usage).to.have.property('total_tokens');
					expect(usage.total_tokens).to.be.greaterThan(0);

					cy.log(`📊 Total tokens used: ${usage.total_tokens}`);

					// Check in meta box
					cy.visit(`/wp-admin/post.php?post=${postId}&action=edit`);
					cy.wait(3000);

					cy.contains('summary', 'Initiale Generierung').click();
					cy.wait(500);

					cy.contains('Token Usage').should('exist');
					cy.contains('Total').should('exist');

					cy.log('✅ Token usage displayed correctly');
				}
			});
		});
	});

	it('should show prompt edit links in meta box', function() {
		this.timeout(300000);

		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		cy.get('#title').type(`Prompt Links Test - ${Date.now()}`);
		cy.get('#save-post').click();
		cy.wait(3000);
		
		cy.get('#post_ID').invoke('val').then((postId) => {
			cy.request({
				method: 'POST',
				url: '/wp-json/ai-featured-image/v1/generate-post',
				body: {
					post_id: postId,
					length: 'short',
					auto_correct: false,
					max_corrections: 0
				},
				timeout: 180000
			}).then(() => {
				cy.visit(`/wp-admin/post.php?post=${postId}&action=edit`);
				cy.wait(3000);

				// Expand initial generation section
				cy.contains('summary', 'Initiale Generierung').click();
				cy.wait(500);

				// Check for prompt edit links
				cy.get('a').contains('Prompt bearbeiten').should('have.length.greaterThan', 0);
				
				// Verify links point to correct pages
				cy.get('a').contains('Prompt bearbeiten').first()
					.should('have.attr', 'href')
					.and('include', 'post.php?post=')
					.and('include', 'action=edit');

				cy.log('✅ Prompt edit links are present and valid');
			});
		});
	});

	it('should show message for posts without debug data', () => {
		// Create a regular post without AI generation
		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		cy.get('#title').type(`No Debug Data Test - ${Date.now()}`);
		cy.get('#content').type('This is a regular post without AI generation');
		cy.get('#publish').click();
		cy.wait(4000);

		// Check meta box shows appropriate message
		cy.get('#ai_debug_info').should('exist');
		cy.get('#ai_debug_info').should('contain', 'Keine Debug-Informationen verfügbar');
		cy.get('#ai_debug_info').should('contain', 'nicht über AI generiert');

		cy.log('✅ Appropriate message shown for non-AI posts');
	});
});



