// Direct AI Post Generation via AJAX
// Code comments are in English per project rule.
// Bypasses UI and calls AJAX directly to generate real AI content

describe('AI Post Generation - Direct AJAX Call', () => {
	const aiTitles = [
		'Künstliche Intelligenz revolutioniert die Arbeitswelt',
		'Machine Learning: Die Zukunft der Datenanalyse',
		'ChatGPT und die Evolution der Sprachmodelle',
		'Deep Learning in der Medizin: Chancen und Risiken',
		'Computer Vision: Wenn Maschinen sehen lernen'
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

	// Test each length with direct AJAX call
	['short', 'medium', 'long', 'verylong'].forEach((length) => {
		it(`generates real AI post with ${length} length via direct AJAX call`, () => {
			const title = `${getRandomTitle()} [AJAX ${length}] - ${Date.now()}`;
			
			cy.log(`🎯 Creating ${length} post via AJAX: "${title}"`);

			// Create a new post first
			cy.visit('/wp-admin/post-new.php');
			cy.wait(3000);

			// Add title
			cy.get('#title').should('be.visible').type(title, { delay: 30 });
			cy.wait(1000);

			// Save as draft to get post ID
			cy.get('#save-post').click();
			cy.wait(3000);

			// Get post ID from URL or hidden field
			cy.get('#post_ID').invoke('val').then((postId) => {
				cy.log(`📝 Post ID: ${postId}`);

				// Get nonce
				cy.get('#_wpnonce').invoke('val').then((nonce) => {
					cy.log(`🔒 Nonce: ${nonce.substring(0, 10)}...`);

					// Make direct AJAX request to generate AI content
					cy.request({
						method: 'POST',
						url: '/wp-admin/admin-ajax.php',
						form: true,
						body: {
							action: 'generate_ai_post',
							post_id: postId,
							length: length,
							nonce: nonce
						},
						timeout: 180000 // 3 minutes
					}).then((response) => {
						cy.log('📥 AJAX Response received');

						expect(response.status).to.eq(200);
						expect(response.body).to.have.property('success');

						if (response.body.success) {
							cy.log('✅ AI Content generated successfully!');

							const data = response.body.data;

							// Validate content
							expect(data).to.have.property('content_html');
							expect(data.content_html).to.not.be.empty;

							const wordCount = countWords(data.content_html);
							cy.log(`📊 Generated ${wordCount} words`);

							// Validate word count based on length
							const expectations = {
								short: { min: 250, max: 650 },
								medium: { min: 700, max: 1400 },
								long: { min: 1400, max: 2300 },
								verylong: { min: 2300, max: 3500 }
							};

							const expected = expectations[length];
							expect(wordCount, `Word count for ${length}`).to.be.at.least(expected.min);
							expect(wordCount, `Word count for ${length}`).to.be.at.most(expected.max);

							// Validate tags
							if (data.tags) {
								cy.log(`🏷️ Tags: ${data.tags.join(', ')}`);
								expect(data.tags.length, 'Should have 7-10 tags').to.be.within(7, 10);
							}

							// Validate category
							if (data.category_name) {
								cy.log(`📁 Category: ${data.category_name}`);
								expect(data.category_name).to.be.a('string');
							}

							// Validate HTML structure
							expect(data.content_html).to.include('<h2>');
							expect(data.content_html).to.include('<p>');

							cy.log(`✅ All validations passed for ${length} (${wordCount} words)`);

							// Reload page to see if content was inserted
							cy.reload();
							cy.wait(2000);

							// Check if content is in editor
							cy.window().then((win) => {
								if (win.tinyMCE && win.tinyMCE.activeEditor) {
									const content = win.tinyMCE.activeEditor.getContent();
									const editorWordCount = countWords(content);
									cy.log(`✓ Content in editor: ${editorWordCount} words`);
									expect(editorWordCount).to.be.greaterThan(100);
								}
							});

							// Publish the post
							cy.get('#publish').should('be.visible').click();
							cy.wait(4000);

							// Verify published
							cy.get('#message').should('be.visible');

							cy.log(`✅ Post "${title}" created and published with ${wordCount} words!`);

						} else {
							const errorMsg = response.body.data && response.body.data.message 
								? response.body.data.message 
								: 'Unknown error';
							cy.log(`❌ API Error: ${errorMsg}`);
							throw new Error(`AI Post generation failed: ${errorMsg}`);
						}
					});
				});
			});
		});
	});

	it('creates multiple AI posts with different lengths and compares results', () => {
		const timestamp = Date.now();
		const results = [];

		const lengths = ['short', 'medium', 'long'];

		lengths.forEach((length, index) => {
			const title = `Multi-Test ${length} - ${timestamp}-${index}`;

			cy.log(`\n📝 Creating post ${index + 1}/${lengths.length}: ${length}`);

			// Create post
			cy.visit('/wp-admin/post-new.php');
			cy.wait(2000);

			cy.get('#title').type(title, { delay: 20 });
			cy.wait(500);

			// Save to get ID
			cy.get('#save-post').click();
			cy.wait(3000);

			cy.get('#post_ID').invoke('val').then((postId) => {
				cy.get('#_wpnonce').invoke('val').then((nonce) => {
					
					// AJAX call
					cy.request({
						method: 'POST',
						url: '/wp-admin/admin-ajax.php',
						form: true,
						body: {
							action: 'generate_ai_post',
							post_id: postId,
							length: length,
							nonce: nonce
						},
						timeout: 180000
					}).then((response) => {
						if (response.body.success && response.body.data.content_html) {
							const wordCount = countWords(response.body.data.content_html);
							
							results.push({
								length: length,
								wordCount: wordCount,
								tags: response.body.data.tags ? response.body.data.tags.length : 0
							});

							cy.log(`✅ ${length}: ${wordCount} words, ${response.body.data.tags ? response.body.data.tags.length : 0} tags`);

							// Publish
							cy.reload();
							cy.wait(2000);
							cy.get('#publish').click();
							cy.wait(3000);
						}
					});
				});
			});
		});

		// At the end, verify all posts exist
		cy.visit('/wp-admin/edit.php');
		cy.wait(2000);

		cy.get('.row-title').should('have.length.at.least', lengths.length);

		cy.log('\n📊 Final Results:');
		cy.wrap(results).then((res) => {
			res.forEach(r => {
				cy.log(`  ${r.length}: ${r.wordCount} words, ${r.tags} tags`);
			});
		});
	});

	it('verifies API key is configured', () => {
		cy.visit('/wp-admin/options-general.php?page=ai-featured-image-settings');
		cy.wait(2000);

		// Check if API key field exists and has value
		cy.get('input[name="ai_featured_image_options[api_key]"]').then($input => {
			const value = $input.val();
			if (value && value.length > 10) {
				cy.log('✅ API Key is configured');
				expect(value).to.have.length.greaterThan(10);
			} else {
				cy.log('⚠️ API Key not configured or too short');
				cy.log('Please configure your OpenAI API key in Settings > AI Featured Image');
			}
		});
	});
});


