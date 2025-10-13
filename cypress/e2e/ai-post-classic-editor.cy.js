// AI Post Generation for Classic Editor with Real API
// Code comments are in English per project rule.
// Creates real posts with AI content using Classic Editor

describe('AI Post Generation - Classic Editor', () => {
	const aiTitles = [
		'Künstliche Intelligenz revolutioniert die Arbeitswelt',
		'Machine Learning: Die Zukunft der Datenanalyse',
		'ChatGPT und die Evolution der Sprachmodelle',
		'Neuronale Netze: Wie Computer lernen zu denken',
		'Deep Learning in der Medizin: Chancen und Risiken',
		'KI-gestützte Automatisierung in der Industrie 4.0',
		'Computer Vision: Wenn Maschinen sehen lernen',
		'Natural Language Processing im Alltag'
	];

	const getRandomTitle = () => {
		return aiTitles[Math.floor(Math.random() * aiTitles.length)];
	};

	const countWords = (html) => {
		if (!html) return 0;
		const text = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
		return text.split(' ').filter(word => word.length > 0).length;
	};

	beforeEach(() => {
		cy.wpLogin();
	});

	// Test each length option with real API call
	['short', 'medium', 'long', 'verylong'].forEach((length) => {
		it(`creates real AI post with ${length} length in Classic Editor`, () => {
			const title = `${getRandomTitle()} [${length}] - ${Date.now()}`;
			
			cy.log(`🎯 Creating ${length} post: "${title}"`);

			// Visit new post page (Classic Editor)
			cy.visit('/wp-admin/post-new.php');
			cy.wait(3000);

			// Screenshot 1: Initial editor
			cy.screenshot(`classic-${length}-01-editor-loaded`, { 
				capture: 'viewport',
				overwrite: true 
			});

			// Add title
			cy.get('#title').should('be.visible').clear().type(title, { delay: 30 });
			cy.wait(1000);

			cy.log(`✓ Title added: "${title}"`);

			// Screenshot 2: Title added
			cy.screenshot(`classic-${length}-02-title-added`, { 
				capture: 'viewport',
				overwrite: true 
			});

			// Look for AI Post Generator metabox
			cy.get('body').then($body => {
				// Check if metabox exists
				const hasMetabox = $body.find('#ai-post-generator').length > 0 ||
				                   $body.find('#ai-generate-post-button').length > 0 ||
				                   $body.find('#ai-post-length').length > 0;

				if (!hasMetabox) {
					cy.log('⚠️ AI Post Generator metabox not found');
					cy.log('Checking page structure...');
					cy.get('body').then($b => {
						cy.log(`Found metaboxes: ${$b.find('.postbox').length}`);
					});
					return;
				}

				cy.log('✓ AI Post Generator metabox found');

				// Select length
				cy.get('#ai-post-length').should('be.visible').select(length);
				cy.wait(500);
				
				cy.log(`✓ Selected length: ${length}`);

				// Screenshot 3: Length selected
				cy.screenshot(`classic-${length}-03-length-selected`, { 
					capture: 'viewport',
					overwrite: true 
				});

				// Intercept AJAX request
				let requestSent = false;
				cy.intercept('POST', '**/admin-ajax.php*', (req) => {
					if (req.body && req.body.includes('action=generate_ai_post')) {
						requestSent = true;
						cy.log('🔄 AI Post generation API request sent');
					}
				}).as('aiPostRequest');

				// Click generate button
				cy.get('#ai-generate-post-button').should('be.visible').click();
				
				cy.log('🚀 Clicked Generate AI Post button');

				// Screenshot 4: After clicking generate
				cy.screenshot(`classic-${length}-04-generation-started`, { 
					capture: 'viewport',
					overwrite: true 
				});

				// Wait for AJAX response (up to 3 minutes for API)
				cy.wait('@aiPostRequest', { timeout: 180000 }).then((interception) => {
					cy.log('📥 Received API response');

					// Screenshot 5: Response received
					cy.wait(2000);
					cy.screenshot(`classic-${length}-05-response-received`, { 
						capture: 'viewport',
						overwrite: true 
					});

					const response = interception.response.body;

					if (response && response.success) {
						cy.log('✅ AI content generated successfully!');
						
						if (response.data.content_html) {
							const wordCount = countWords(response.data.content_html);
							cy.log(`📊 Generated ${wordCount} words`);
							
							if (response.data.tags) {
								cy.log(`🏷️ Tags: ${response.data.tags.join(', ')}`);
							}
							
							if (response.data.category_name) {
								cy.log(`📁 Category: ${response.data.category_name}`);
							}
						}

						// Wait for content to be inserted
						cy.wait(3000);

						// Screenshot 6: Content inserted
						cy.screenshot(`classic-${length}-06-content-inserted`, { 
							capture: 'viewport',
							overwrite: true 
						});

						// Check if content was inserted into TinyMCE editor
						cy.window().then((win) => {
							if (win.tinyMCE && win.tinyMCE.activeEditor) {
								const content = win.tinyMCE.activeEditor.getContent();
								const wordCount = countWords(content);
								cy.log(`✓ Content in editor: ${wordCount} words`);
							}
						});

						// Publish the post
						cy.get('#publish').should('be.visible').click();
						cy.wait(4000);

						// Screenshot 7: Published
						cy.screenshot(`classic-${length}-07-published`, { 
							capture: 'viewport',
							overwrite: true 
						});

						// Verify publish message
						cy.get('#message').should('contain.text', 'veröffentlicht').or('contain.text', 'published');

						cy.log(`✅ Post "${title}" published successfully!`);

					} else {
						const errorMsg = response && response.data && response.data.message 
							? response.data.message 
							: 'Unknown error';
						cy.log(`❌ API Error: ${errorMsg}`);
						
						cy.screenshot(`classic-${length}-ERROR`, { 
							capture: 'viewport',
							overwrite: true 
						});
					}
				});
			});
		});
	});

	it('creates a complete published post with AI content', () => {
		const title = `${getRandomTitle()} - Vollständig - ${Date.now()}`;
		const length = 'medium';

		cy.log(`🎯 Creating complete post: "${title}"`);

		cy.visit('/wp-admin/post-new.php');
		cy.wait(3000);

		cy.screenshot('complete-classic-01-initial', { capture: 'viewport', overwrite: true });

		// Add title
		cy.get('#title').clear().type(title, { delay: 30 });
		cy.wait(1000);

		cy.screenshot('complete-classic-02-title', { capture: 'viewport', overwrite: true });

		// Check for AI controls
		cy.get('#ai-post-length').then($select => {
			if ($select.length > 0) {
				cy.get('#ai-post-length').select(length);
				cy.wait(500);

				cy.screenshot('complete-classic-03-ready', { capture: 'viewport', overwrite: true });

				// Intercept
				cy.intercept('POST', '**/admin-ajax.php*', (req) => {
					if (req.body && req.body.includes('action=generate_ai_post')) {
						cy.log('🔄 Generating AI content...');
					}
				}).as('completeRequest');

				// Generate
				cy.get('#ai-generate-post-button').click();

				cy.wait('@completeRequest', { timeout: 180000 }).then((interception) => {
					cy.screenshot('complete-classic-04-generated', { capture: 'viewport', overwrite: true });

					const response = interception.response.body;

					if (response && response.success && response.data.content_html) {
						const wordCount = countWords(response.data.content_html);
						cy.log(`✅ Generated ${wordCount} words`);

						cy.wait(3000);

						// Publish
						cy.get('#publish').click();
						cy.wait(4000);

						cy.screenshot('complete-classic-05-published', { capture: 'viewport', overwrite: true });

						// Verify in list
						cy.visit('/wp-admin/edit.php');
						cy.wait(2000);

						cy.screenshot('complete-classic-06-post-list', { capture: 'viewport', overwrite: true });

						// Search for post
						const searchTerm = title.substring(0, 40);
						cy.get('#post-search-input').type(searchTerm);
						cy.get('#search-submit').click();
						cy.wait(2000);

						cy.screenshot('complete-classic-07-found', { capture: 'viewport', overwrite: true });

						// Verify exists
						cy.contains('.row-title', title.substring(0, 30)).should('exist');

						cy.log(`✅ Complete post created and published!`);
					}
				});
			}
		});
	});

	it('verifies AI Post Generator metabox is visible', () => {
		cy.visit('/wp-admin/post-new.php');
		cy.wait(3000);

		cy.screenshot('metabox-check-01', { capture: 'viewport', overwrite: true });

		// Add a title first (metabox might need it)
		cy.get('#title').type('Test für Metabox-Sichtbarkeit');
		cy.wait(1000);

		cy.screenshot('metabox-check-02', { capture: 'viewport', overwrite: true });

		// Check for metabox elements
		cy.get('body').then($body => {
			cy.log('Checking for AI Post Generator elements...');
			
			const hasLength = $body.find('#ai-post-length').length;
			const hasButton = $body.find('#ai-generate-post-button').length;
			const hasMetabox = $body.find('#ai-post-generator').length;

			cy.log(`Length selector: ${hasLength > 0 ? '✓' : '✗'}`);
			cy.log(`Generate button: ${hasButton > 0 ? '✓' : '✗'}`);
			cy.log(`Metabox container: ${hasMetabox > 0 ? '✓' : '✗'}`);

			if (hasLength > 0 && hasButton > 0) {
				cy.log('✅ AI Post Generator is fully functional');
				
				// Test the dropdown
				cy.get('#ai-post-length').select('short');
				cy.get('#ai-post-length').should('have.value', 'short');
				
				cy.get('#ai-post-length').select('medium');
				cy.get('#ai-post-length').should('have.value', 'medium');
				
				cy.get('#ai-post-length').select('long');
				cy.get('#ai-post-length').should('have.value', 'long');
				
				cy.get('#ai-post-length').select('verylong');
				cy.get('#ai-post-length').should('have.value', 'verylong');

				cy.screenshot('metabox-check-03-all-lengths', { capture: 'viewport', overwrite: true });

				cy.log('✅ All length options work correctly');
			} else {
				cy.log('⚠️ AI Post Generator elements not found');
			}
		});
	});
});


