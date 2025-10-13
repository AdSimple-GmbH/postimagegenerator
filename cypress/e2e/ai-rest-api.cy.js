// AI REST API Tests
// Tests for the new REST API endpoint /ai-featured-image/v1/generate-post
// Code comments are in English per project rule.

describe('AI REST API - Post Generation', () => {
	const countWords = (html) => {
		if (!html) return 0;
		const text = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
		return text.split(' ').filter(word => word.length > 0).length;
	};

	let testPostId;

	beforeEach(() => {
		cy.wpLogin();
		
		// Create a test post for API testing
		cy.visit('/wp-admin/post-new.php');
		cy.wait(2000);
		
		const title = `REST API Test - ${Date.now()}`;
		cy.get('#title').type(title);
		cy.get('#save-post').click();
		cy.wait(3000);
		
		cy.get('#post_ID').invoke('val').then((postId) => {
			testPostId = postId;
			cy.log(`📝 Created test post ID: ${testPostId}`);
		});
	});

	it('should successfully call REST API endpoint with valid parameters', function() {
		this.timeout(300000);

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
			// Verify response structure
			expect(response.status).to.eq(200);
			expect(response.body).to.have.property('success', true);
			expect(response.body).to.have.property('data');

			const data = response.body.data;

			// Verify data structure
			expect(data).to.have.property('content_html');
			expect(data).to.have.property('word_count');
			expect(data).to.have.property('corrections');
			expect(data).to.have.property('debug');

			// Verify content
			expect(data.content_html).to.be.a('string').and.not.be.empty;
			const wordCount = countWords(data.content_html);
			cy.log(`📊 Generated ${wordCount} words`);

			// Verify word count object
			expect(data.word_count).to.have.property('initial');
			expect(data.word_count).to.have.property('final');
			expect(data.word_count).to.have.property('target_min');
			expect(data.word_count).to.have.property('target_max');
			expect(data.word_count).to.have.property('valid');
			expect(data.word_count).to.have.property('message');

			// Verify corrections object
			expect(data.corrections).to.have.property('enabled');
			expect(data.corrections).to.have.property('made');
			expect(data.corrections).to.have.property('max_allowed');
			expect(data.corrections).to.have.property('history');

			// Verify debug info
			expect(data.debug).to.have.property('initial_generation');
			expect(data.debug.initial_generation).to.have.property('request');
			expect(data.debug.initial_generation).to.have.property('response');

			cy.log('✅ REST API response structure is valid');
		});
	});

	it('should return debug information with model and prompts', function() {
		this.timeout(300000);

		cy.request({
			method: 'POST',
			url: '/wp-json/ai-featured-image/v1/generate-post',
			body: {
				post_id: testPostId,
				length: 'short',
				auto_correct: false,
				max_corrections: 0
			},
			timeout: 180000
		}).then((response) => {
			expect(response.status).to.eq(200);
			
			const debug = response.body.data.debug;
			const request = debug.initial_generation.request;

			// Verify request debug info
			expect(request).to.have.property('model');
			expect(request).to.have.property('temperature');
			expect(request).to.have.property('max_tokens');
			expect(request).to.have.property('response_format');
			expect(request).to.have.property('system_prompt');
			expect(request).to.have.property('user_prompt');
			expect(request).to.have.property('system_prompt_id');
			expect(request).to.have.property('user_prompt_id');
			expect(request).to.have.property('system_prompt_edit_link');
			expect(request).to.have.property('user_prompt_edit_link');
			expect(request).to.have.property('user_prompt_variant');

			// Verify model is GPT-5 family (as configured in prompts)
			expect(request.model).to.match(/gpt-5|gpt-4/i);

			// Verify temperature handling (GPT-5 should show default)
			if (request.model.includes('gpt-5')) {
				expect(request.temperature).to.satisfy((temp) => {
					return temp === '1 (default)' || temp === 1 || temp === '1';
				});
			}

			// Verify prompt IDs exist
			expect(request.system_prompt_id).to.be.a('number').and.to.be.greaterThan(0);
			expect(request.user_prompt_id).to.be.a('number').and.to.be.greaterThan(0);

			// Verify edit links are valid URLs
			expect(request.system_prompt_edit_link).to.include('post.php?post=');
			expect(request.user_prompt_edit_link).to.include('post.php?post=');

			// Verify variant matches requested length
			expect(request.user_prompt_variant).to.eq('short');

			// Verify response debug info
			const responseDebug = debug.initial_generation.response;
			expect(responseDebug).to.have.property('model');
			expect(responseDebug).to.have.property('usage');
			expect(responseDebug).to.have.property('raw_content');

			// Verify token usage
			if (responseDebug.usage) {
				expect(responseDebug.usage).to.have.property('prompt_tokens');
				expect(responseDebug.usage).to.have.property('completion_tokens');
				expect(responseDebug.usage).to.have.property('total_tokens');
				
				cy.log(`📊 Token usage: ${responseDebug.usage.total_tokens} total`);
			}

			cy.log('✅ Debug information is complete and valid');
		});
	});

	// Test all length options via REST API
	['short', 'medium', 'long', 'verylong'].forEach((length) => {
		it(`should generate ${length} post via REST API`, function() {
			this.timeout(300000);

			// Create fresh post for this test
			cy.visit('/wp-admin/post-new.php');
			cy.wait(2000);
			
			cy.get('#title').type(`REST ${length} - ${Date.now()}`);
			cy.get('#save-post').click();
			cy.wait(3000);
			
			cy.get('#post_ID').invoke('val').then((postId) => {
				cy.request({
					method: 'POST',
					url: '/wp-json/ai-featured-image/v1/generate-post',
					body: {
						post_id: postId,
						length: length,
						auto_correct: true,
						max_corrections: 2
					},
					timeout: 180000
				}).then((response) => {
					expect(response.status).to.eq(200);
					expect(response.body.success).to.be.true;

					const wordCount = response.body.data.word_count;
					cy.log(`📊 ${length}: ${wordCount.final} words (target: ${wordCount.target_min}-${wordCount.target_max})`);

					// Verify word count is reasonable
					expect(wordCount.final).to.be.greaterThan(100);

					// Verify corrections info
					const corrections = response.body.data.corrections;
					cy.log(`🔄 Corrections made: ${corrections.made}/${corrections.max_allowed}`);

					if (corrections.made > 0) {
						expect(corrections.history).to.have.length(corrections.made);
						
						corrections.history.forEach((corr, idx) => {
							cy.log(`  Correction ${idx + 1}: ${corr.direction} (${corr.before_words} → ${corr.after_words} words)`);
						});
					}

					cy.log(`✅ ${length} post generated successfully`);
				});
			});
		});
	});

	it('should handle auto-correction disabled correctly', function() {
		this.timeout(300000);

		cy.request({
			method: 'POST',
			url: '/wp-json/ai-featured-image/v1/generate-post',
			body: {
				post_id: testPostId,
				length: 'short',
				auto_correct: false,
				max_corrections: 0
			},
			timeout: 180000
		}).then((response) => {
			expect(response.status).to.eq(200);

			const corrections = response.body.data.corrections;
			
			expect(corrections.enabled).to.be.false;
			expect(corrections.made).to.eq(0);
			expect(corrections.history).to.be.an('array').and.have.length(0);

			cy.log('✅ Auto-correction disabled correctly');
		});
	});

	it('should include correction debug info when corrections are made', function() {
		this.timeout(300000);

		cy.request({
			method: 'POST',
			url: '/wp-json/ai-featured-image/v1/generate-post',
			body: {
				post_id: testPostId,
				length: 'medium',
				auto_correct: true,
				max_corrections: 2
			},
			timeout: 180000
		}).then((response) => {
			expect(response.status).to.eq(200);

			const corrections = response.body.data.corrections;
			const debug = response.body.data.debug;

			if (corrections.made > 0) {
				// Verify debug has correction entries
				expect(debug).to.have.property('corrections');
				expect(debug.corrections).to.be.an('array').and.have.length(corrections.made);

				// Verify each correction debug entry
				debug.corrections.forEach((corr, idx) => {
					expect(corr).to.have.property('direction');
					expect(corr.direction).to.be.oneOf(['expand', 'shorten']);
					
					expect(corr).to.have.property('request');
					expect(corr.request).to.have.property('model');
					expect(corr.request).to.have.property('temperature');
					expect(corr.request).to.have.property('max_tokens');
					expect(corr.request).to.have.property('system_prompt_id');
					expect(corr.request).to.have.property('user_prompt_id');
					expect(corr.request).to.have.property('system_prompt_edit_link');
					expect(corr.request).to.have.property('user_prompt_edit_link');
					expect(corr.request).to.have.property('user_prompt_slug');
					expect(corr.request).to.have.property('current_words');

					expect(corr).to.have.property('response');
					expect(corr.response).to.have.property('new_word_count');
					expect(corr.response).to.have.property('model');

					cy.log(`✅ Correction ${idx + 1} debug info is complete`);
				});
			} else {
				cy.log('ℹ️ No corrections were needed for this post');
			}
		});
	});

	it('should return error for invalid post ID', () => {
		cy.request({
			method: 'POST',
			url: '/wp-json/ai-featured-image/v1/generate-post',
			body: {
				post_id: 999999,
				length: 'short',
				auto_correct: false,
				max_corrections: 0
			},
			failOnStatusCode: false
		}).then((response) => {
			expect(response.status).to.eq(404);
			expect(response.body).to.have.property('code');
			expect(response.body).to.have.property('message');
			
			cy.log('✅ Correctly returns 404 for invalid post ID');
		});
	});

	it('should validate length parameter', () => {
		cy.request({
			method: 'POST',
			url: '/wp-json/ai-featured-image/v1/generate-post',
			body: {
				post_id: testPostId,
				length: 'invalid_length',
				auto_correct: false,
				max_corrections: 0
			},
			failOnStatusCode: false
		}).then((response) => {
			// Should either reject invalid length or default to a valid one
			// Based on the schema, it should use default 'medium'
			if (response.status === 200) {
				// If it accepts invalid and defaults, that's also acceptable behavior
				cy.log('ℹ️ API defaults to valid length for invalid input');
			} else {
				expect(response.status).to.be.oneOf([400, 422]);
				cy.log('✅ API rejects invalid length parameter');
			}
		});
	});

	it('should respect max_corrections limit', function() {
		this.timeout(300000);

		cy.request({
			method: 'POST',
			url: '/wp-json/ai-featured-image/v1/generate-post',
			body: {
				post_id: testPostId,
				length: 'short',
				auto_correct: true,
				max_corrections: 1
			},
			timeout: 180000
		}).then((response) => {
			expect(response.status).to.eq(200);

			const corrections = response.body.data.corrections;
			
			expect(corrections.max_allowed).to.eq(1);
			expect(corrections.made).to.be.at.most(1);
			
			if (corrections.made === 1) {
				expect(corrections.history).to.have.length(1);
			}

			cy.log(`✅ Corrections limited to max: ${corrections.made}/${corrections.max_allowed}`);
		});
	});

	it('should generate category and tags', function() {
		this.timeout(300000);

		cy.request({
			method: 'POST',
			url: '/wp-json/ai-featured-image/v1/generate-post',
			body: {
				post_id: testPostId,
				length: 'short',
				auto_correct: false,
				max_corrections: 0
			},
			timeout: 180000
		}).then((response) => {
			expect(response.status).to.eq(200);

			const data = response.body.data;

			// Verify category
			if (data.category_name) {
				expect(data.category_name).to.be.a('string').and.not.be.empty;
				expect(data.category_id).to.be.a('number');
				cy.log(`📁 Category: ${data.category_name} (ID: ${data.category_id})`);
			}

			// Verify tags
			if (data.tags) {
				expect(data.tags).to.be.an('array');
				expect(data.tags.length).to.be.greaterThan(0);
				cy.log(`🏷️ Tags (${data.tags.length}): ${data.tags.join(', ')}`);
			}

			cy.log('✅ Category and tags generated');
		});
	});

	it('should show correct model usage in debug (GPT-5 family)', function() {
		this.timeout(300000);

		cy.request({
			method: 'POST',
			url: '/wp-json/ai-featured-image/v1/generate-post',
			body: {
				post_id: testPostId,
				length: 'short',
				auto_correct: false,
				max_corrections: 0
			},
			timeout: 180000
		}).then((response) => {
			expect(response.status).to.eq(200);

			const requestModel = response.body.data.debug.initial_generation.request.model;
			const responseModel = response.body.data.debug.initial_generation.response.model;

			cy.log(`📊 Request model: ${requestModel}`);
			cy.log(`📊 Response model: ${responseModel}`);

			// Verify model is from GPT family
			expect(requestModel).to.match(/gpt-[45]/i);

			// If GPT-5 is used, temperature should be default
			if (requestModel.includes('gpt-5')) {
				const temp = response.body.data.debug.initial_generation.request.temperature;
				expect(String(temp)).to.match(/1|default/i);
				cy.log('✅ GPT-5 model correctly uses default temperature');
			}

			cy.log('✅ Model information is correct');
		});
	});
});



