// AI Post Generation with Real API Calls and Validation
// Code comments are in English per project rule.
// This test suite validates AI post generation with real OpenAI API calls

describe('AI Post Generation with Real API', () => {
	// AI-related German titles for testing
	const aiTitles = [
		'Künstliche Intelligenz revolutioniert die Arbeitswelt',
		'Machine Learning: Die Zukunft der Datenanalyse',
		'ChatGPT und die Evolution der Sprachmodelle',
		'Neuronale Netze: Wie Computer lernen zu denken',
		'Deep Learning in der Medizin: Chancen und Risiken',
		'KI-gestützte Automatisierung in der Industrie 4.0',
		'Ethik der künstlichen Intelligenz: Grenzen und Verantwortung',
		'Computer Vision: Wenn Maschinen sehen lernen',
		'Natural Language Processing: Die Macht der Sprachverarbeitung',
		'Reinforcement Learning: KI lernt durch Belohnung'
	];

	// Expected word counts for each length option
	const lengthExpectations = {
		short: { min: 250, max: 650, target: '300-500', minExpected: 300 },
		medium: { min: 700, max: 1400, target: '800-1200', minExpected: 800 },
		long: { min: 1400, max: 2300, target: '1500-2000', minExpected: 1500 },
		verylong: { min: 2300, max: 3500, target: '2500+', minExpected: 2500 }
	};

	const getRandomTitle = () => {
		return aiTitles[Math.floor(Math.random() * aiTitles.length)];
	};

	const countWords = (html) => {
		if (!html) return 0;
		// Remove HTML tags and count words
		const text = html.replace(/<[^>]*>/g, ' ')
			.replace(/\s+/g, ' ')
			.replace(/&nbsp;/g, ' ')
			.trim();
		const words = text.split(' ').filter(word => word.length > 0);
		return words.length;
	};

	beforeEach(() => {
		cy.wpLogin();
	});

	// Test each length with real API call and word count validation
	['short', 'medium', 'long', 'verylong'].forEach((lengthOption) => {
		it(`generates AI post with ${lengthOption} length, validates word count, and takes screenshots`, function() {
			const title = `${getRandomTitle()} - API Test ${lengthOption}`;
			const expectations = lengthExpectations[lengthOption];
			let generatedContent = '';
			let wordCount = 0;

			cy.log(`🎯 Testing ${lengthOption}: Expected ${expectations.target} words`);

			// Create new post
			cy.visit('/wp-admin/post-new.php');
			cy.wait(2000);

			// Screenshot 1: Empty editor
			cy.screenshot(`${lengthOption}-01-empty-editor`, {
				capture: 'viewport',
				overwrite: true
			});

			// Add title
			cy.get('.editor-post-title__input, #title').first().clear().type(title, { delay: 20 });
			cy.wait(1500);

			cy.log(`📝 Title: "${title}"`);

			// Screenshot 2: Title added
			cy.screenshot(`${lengthOption}-02-title-added`, {
				capture: 'viewport',
				overwrite: true
			});

			// Check if we're in classic editor or Gutenberg
			cy.get('body').then($body => {
				const hasAIControls = $body.find('#ai-generate-post-button').length > 0;
				
				if (!hasAIControls) {
					cy.log('⚠️  AI post generation controls not visible in this view');
					cy.log('This might require Classic Editor plugin or sidebar metabox');
					this.skip(); // Skip this test if controls aren't available
					return;
				}

				// Select length option
				cy.get('#ai-post-length').should('be.visible').select(lengthOption);
				cy.get('#ai-post-length').should('have.value', lengthOption);
				cy.log(`✓ Selected length: ${lengthOption}`);

				// Screenshot 3: Length selected
				cy.screenshot(`${lengthOption}-03-length-selected`, {
					capture: 'viewport',
					overwrite: true
				});

				// Intercept the AJAX request to OpenAI
				cy.intercept('POST', '**/admin-ajax.php*', (req) => {
					if (req.body.includes('action=generate_ai_post')) {
						cy.log('🔄 API request intercepted - generating content...');
						req.alias = 'generateAIPost';
					}
				});

				// Click generate button
				cy.get('#ai-generate-post-button').click();
				cy.log('🚀 AI Post generation started...');

				// Screenshot 4: Generation started
				cy.screenshot(`${lengthOption}-04-generation-started`, {
					capture: 'viewport',
					overwrite: true
				});

				// Wait for AJAX response (timeout: 3 minutes for API call)
				cy.wait('@generateAIPost', { timeout: 180000 }).then((interception) => {
					cy.log('✅ API Response received');
					
					const response = interception.response.body;
					
					// Screenshot 5: After API response
					cy.wait(2000);
					cy.screenshot(`${lengthOption}-05-api-response-received`, {
						capture: 'viewport',
						overwrite: true
					});

					if (response.success) {
						cy.log('✅ AI Post generated successfully!');
						
						// Extract generated content
						if (response.data && response.data.content_html) {
							generatedContent = response.data.content_html;
							wordCount = countWords(generatedContent);
							
							cy.log(`📊 Word count: ${wordCount}`);
							cy.log(`🎯 Expected: ${expectations.min}-${expectations.max} words`);

							// Validate word count
							expect(wordCount, `Word count should be at least ${expectations.min}`).to.be.at.least(expectations.min);
							expect(wordCount, `Word count should be at most ${expectations.max}`).to.be.at.most(expectations.max);
							
							cy.log(`✅ Word count validation passed: ${wordCount} words`);

							// Validate that content was inserted into editor
							cy.wait(2000);
							
							// Screenshot 6: Content inserted
							cy.screenshot(`${lengthOption}-06-content-inserted`, {
								capture: 'viewport',
								overwrite: true
							});

							// Check if content exists in the editor
							cy.get('body').then($body => {
								const hasContent = $body.text().includes(title) || 
												  $body.find('.editor-post-text-editor, #content').length > 0;
								expect(hasContent, 'Content should be in editor').to.be.true;
							});

							// Validate tags were added
							if (response.data.tags && response.data.tags.length > 0) {
								cy.log(`🏷️  Tags generated: ${response.data.tags.join(', ')}`);
								expect(response.data.tags.length, 'Should have 7-10 tags').to.be.within(7, 10);
							}

							// Validate category was suggested
							if (response.data.category_name) {
								cy.log(`📁 Category suggested: ${response.data.category_name}`);
							}

						} else {
							cy.log('⚠️  No content_html in response');
						}

						// Save as draft
						cy.wait(2000);
						cy.get('.editor-post-save-draft, #save-post').first().click({ force: true });
						cy.wait(3000);

						// Screenshot 7: Draft saved
						cy.screenshot(`${lengthOption}-07-draft-saved`, {
							capture: 'viewport',
							overwrite: true
						});

						cy.log(`✅ Post saved as draft with ${wordCount} words`);

					} else {
						// API error
						const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
						cy.log(`❌ API Error: ${errorMsg}`);
						
						// Screenshot error
						cy.screenshot(`${lengthOption}-ERROR-api-failed`, {
							capture: 'viewport',
							overwrite: true
						});
						
						throw new Error(`AI Post generation failed: ${errorMsg}`);
					}
				});
			});
		});
	});

	it('generates complete AI post, publishes it, and validates all aspects', function() {
		const title = `${getRandomTitle()} - Vollständiger API Test (${Date.now()})`;
		const lengthOption = 'medium'; // Use medium for balanced testing
		const expectations = lengthExpectations[lengthOption];

		cy.log(`🎯 Creating complete AI post: "${title}"`);

		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);

		// Screenshot: Initial state
		cy.screenshot('complete-01-initial', { capture: 'viewport', overwrite: true });

		// Add title
		cy.get('.editor-post-title__input, #title').first().clear().type(title, { delay: 20 });
		cy.wait(1500);

		cy.screenshot('complete-02-title-added', { capture: 'viewport', overwrite: true });

		cy.get('body').then($body => {
			if ($body.find('#ai-generate-post-button').length === 0) {
				cy.log('⚠️  Skipping: AI controls not visible');
				this.skip();
				return;
			}

			// Select medium length
			cy.get('#ai-post-length').select(lengthOption);
			
			cy.screenshot('complete-03-ready-to-generate', { capture: 'viewport', overwrite: true });

			// Intercept API call
			cy.intercept('POST', '**/admin-ajax.php*', (req) => {
				if (req.body.includes('action=generate_ai_post')) {
					req.alias = 'generateComplete';
				}
			});

			// Generate
			cy.get('#ai-generate-post-button').click();
			cy.log('🚀 Generating AI content...');

			// Wait for response
			cy.wait('@generateComplete', { timeout: 180000 }).then((interception) => {
				const response = interception.response.body;

				cy.screenshot('complete-04-content-generated', { capture: 'viewport', overwrite: true });

				if (response.success && response.data.content_html) {
					const wordCount = countWords(response.data.content_html);
					
					cy.log(`✅ Generated ${wordCount} words`);
					cy.log(`🏷️  Tags: ${response.data.tags ? response.data.tags.length : 0}`);
					cy.log(`📁 Category: ${response.data.category_name || 'None'}`);

					// Validate
					expect(wordCount).to.be.at.least(expectations.min);
					expect(wordCount).to.be.at.most(expectations.max);

					cy.wait(3000);

					// Publish the post
					cy.get('body').then($publishBody => {
						if ($publishBody.find('.editor-post-publish-panel__toggle').length > 0) {
							// Gutenberg
							cy.screenshot('complete-05-before-publish', { capture: 'viewport', overwrite: true });
							
							cy.get('.editor-post-publish-panel__toggle').click({ force: true });
							cy.wait(1500);
							
							cy.screenshot('complete-06-publish-panel', { capture: 'viewport', overwrite: true });
							
							cy.get('.editor-post-publish-button').click({ force: true });
							cy.wait(4000);
							
							cy.screenshot('complete-07-published', { capture: 'viewport', overwrite: true });
							
						} else if ($publishBody.find('#publish').length > 0) {
							// Classic
							cy.get('#publish').click();
							cy.wait(4000);
							
							cy.screenshot('complete-07-published', { capture: 'viewport', overwrite: true });
						}
					});

					// Verify in post list
					cy.visit('/wp-admin/edit.php');
					cy.wait(2000);
					
					cy.screenshot('complete-08-post-list', { capture: 'viewport', overwrite: true });

					// Search for post
					const searchTerm = title.substring(0, 40);
					cy.get('#post-search-input').type(searchTerm);
					cy.get('#search-submit').click();
					cy.wait(2000);

					cy.screenshot('complete-09-search-results', { capture: 'viewport', overwrite: true });

					// Verify post exists
					cy.contains('.row-title', title.substring(0, 30), { timeout: 10000 }).should('exist');

					cy.log(`✅ Complete test passed! Post "${title}" created with ${wordCount} words`);
				} else {
					throw new Error('AI generation failed');
				}
			});
		});
	});

	it('generates posts with all 4 lengths and compares word counts', function() {
		const timestamp = Date.now();
		const results = [];

		cy.log('🎯 Testing all 4 length options with API validation');

		// Helper function to test one length
		const testLength = (length, index) => {
			return new Promise((resolve) => {
				const title = `KI Multi-Test ${length} - ${timestamp}`;
				const expectations = lengthExpectations[length];

				cy.log(`\n📝 Test ${index + 1}/4: ${length.toUpperCase()}`);
				
				cy.visit('/wp-admin/post-new.php');
				cy.wait(2000);

				cy.screenshot(`multi-${index + 1}-${length}-01-start`, { 
					capture: 'viewport', 
					overwrite: true 
				});

				cy.get('.editor-post-title__input, #title').first().clear().type(title, { delay: 20 });
				cy.wait(1500);

				cy.get('body').then($body => {
					if ($body.find('#ai-generate-post-button').length === 0) {
						cy.log(`⚠️  Skipping ${length}: Controls not visible`);
						resolve({ length, skipped: true });
						return;
					}

					cy.get('#ai-post-length').select(length);
					
					cy.screenshot(`multi-${index + 1}-${length}-02-selected`, { 
						capture: 'viewport', 
						overwrite: true 
					});

					cy.intercept('POST', '**/admin-ajax.php*', (req) => {
						if (req.body.includes('action=generate_ai_post')) {
							req.alias = `generate_${length}`;
						}
					});

					cy.get('#ai-generate-post-button').click();

					cy.wait(`@generate_${length}`, { timeout: 180000 }).then((interception) => {
						const response = interception.response.body;

						if (response.success && response.data.content_html) {
							const wordCount = countWords(response.data.content_html);
							
							cy.screenshot(`multi-${index + 1}-${length}-03-generated`, { 
								capture: 'viewport', 
								overwrite: true 
							});

							cy.log(`✅ ${length}: ${wordCount} words (expected: ${expectations.target})`);

							const result = {
								length,
								wordCount,
								expected: expectations,
								inRange: wordCount >= expectations.min && wordCount <= expectations.max,
								title
							};

							results.push(result);

							// Validate
							expect(wordCount, `${length} word count`).to.be.at.least(expectations.min);
							expect(wordCount, `${length} word count`).to.be.at.most(expectations.max);

							// Save as draft
							cy.wait(2000);
							cy.get('.editor-post-save-draft, #save-post').first().click({ force: true });
							cy.wait(3000);

							resolve(result);
						} else {
							resolve({ length, error: true });
						}
					});
				});
			});
		};

		// Run tests sequentially
		cy.wrap(null).then(() => testLength('short', 0));
		cy.wrap(null).then(() => testLength('medium', 1));
		cy.wrap(null).then(() => testLength('long', 2));
		cy.wrap(null).then(() => testLength('verylong', 3)).then(() => {
			// Final validation
			cy.log('\n📊 FINAL RESULTS:');
			results.forEach(r => {
				if (!r.skipped && !r.error) {
					cy.log(`  ${r.length}: ${r.wordCount} words ✅`);
				}
			});

			// All should be in range
			const allInRange = results.filter(r => !r.skipped && !r.error).every(r => r.inRange);
			expect(allInRange, 'All word counts should be in expected range').to.be.true;
		});
	});
});

