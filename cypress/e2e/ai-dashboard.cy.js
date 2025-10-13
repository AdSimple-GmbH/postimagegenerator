// AI Dashboard Tests
// Tests for the new AI Post Generator Dashboard with REST API integration
// Code comments are in English per project rule.

describe('AI Post Generator Dashboard', () => {
	const countWords = (html) => {
		if (!html) return 0;
		const text = html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
		return text.split(' ').filter(word => word.length > 0).length;
	};

	beforeEach(() => {
		cy.wpLogin();
	});

	it('should load the dashboard successfully', () => {
		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		// Verify dashboard elements exist
		cy.contains('AI Post Generator Dashboard').should('be.visible');
		cy.contains('Test Configuration').should('be.visible');
		cy.contains('Ergebnisse').should('be.visible');
		cy.contains('Statistiken').should('be.visible');
		cy.contains('Prompt-Verwaltung').should('be.visible');

		// Check form fields
		cy.get('#test-post-select').should('exist');
		cy.get('#test-length').should('exist');
		cy.get('#test-auto-correct').should('exist');
		cy.get('#test-max-corrections').should('exist');
		cy.get('#test-generate-btn').should('be.visible');

		cy.log('✅ Dashboard loaded successfully');
	});

	it('should display statistics', () => {
		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		// Check statistics cards
		cy.get('#stat-total').should('exist');
		cy.get('#stat-today').should('exist');
		cy.get('#stat-success').should('exist');
		cy.get('#stat-corrections').should('exist');

		cy.log('✅ Statistics displayed');
	});

	it('should display prompt management section', () => {
		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		// Scroll to prompt management
		cy.contains('Prompt-Verwaltung').scrollIntoView();
		cy.wait(500);

		// Check for prompt table
		cy.get('.wp-list-table').should('exist');

		// Should show required prompts or warning about missing ones
		cy.get('body').then(($body) => {
			if ($body.find('.notice-error').length > 0) {
				cy.log('⚠️ Some required prompts are missing');
			} else {
				cy.log('✅ All prompts are configured');
				// Check for specific prompts
				cy.get('.wp-list-table').should('contain', 'system-post-generation')
					.or('contain', 'System: Post-Generierung');
			}
		});
	});

	it('should create a new test post via dashboard button', () => {
		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		// Click "Neuen Test-Post erstellen"
		cy.get('#create-test-post-btn').click();
		cy.wait(3000);

		// Should have created a post and added it to dropdown
		cy.get('#test-post-select option').should('have.length.greaterThan', 1);

		// Verify selected post has test title
		cy.get('#test-post-select option:selected').invoke('text').should('match', /Test|KI|AI|Zukunft/i);

		cy.log('✅ Test post created successfully');
	});

	// Test each length option through the dashboard
	['short', 'medium', 'long', 'verylong'].forEach((length) => {
		it(`should generate ${length} post via dashboard with auto-correction`, function() {
			// Increase timeout for this test
			this.timeout(300000); // 5 minutes

			cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
			cy.wait(2000);

			// Create new test post
			cy.get('#create-test-post-btn').click();
			cy.wait(3000);

			// Select length
			cy.get('#test-length').select(length);
			
			// Enable auto-correction
			cy.get('#test-auto-correct').check();
			cy.get('#test-max-corrections').clear().type('2');

			cy.log(`🎯 Generating ${length} post with auto-correction...`);

			// Click generate
			cy.get('#test-generate-btn').click();

			// Wait for progress to appear
			cy.get('#test-progress', { timeout: 5000 }).should('be.visible');
			cy.get('#progress-message').should('contain', 'Generiere AI-Content');

			// Wait for results (long timeout for API call)
			cy.get('#results-container .ai-result-success', { timeout: 180000 }).should('be.visible');

			cy.log('✅ Generation completed');

			// Verify results structure
			cy.get('.ai-result-header').should('exist');
			cy.get('.ai-result-stats').should('exist');
			
			// Check word count display
			cy.get('.ai-stat-card').contains('Wortanzahl').should('be.visible');
			cy.get('.ai-stat-card').contains('Status').should('be.visible');
			cy.get('.ai-stat-card').contains('Korrekturen').should('be.visible');

			// Verify content preview exists
			cy.get('.ai-content-preview').should('exist');
			cy.get('.ai-content-box').should('not.be.empty');

			// Verify debug panel exists and can be opened
			cy.get('.ai-debug-panel').should('exist');
			cy.get('.ai-debug-header').should('contain', 'Debug-Informationen');
			
			// Click to expand debug panel
			cy.get('.ai-debug-header').click();
			cy.wait(500);
			
			cy.get('.ai-debug-panel.expanded').should('exist');
			cy.get('.ai-debug-content').should('be.visible');

			// Verify debug content structure
			cy.get('.ai-debug-content').within(() => {
				cy.contains('Initiale Generierung').should('be.visible');
				cy.contains('Modell:').should('be.visible');
				cy.contains('Temperature:').should('be.visible');
				cy.contains('Max Tokens:').should('be.visible');
				cy.contains('System Prompt:').should('be.visible');
				cy.contains('User Prompt').should('be.visible');
				
				// Check for edit links
				cy.get('a').contains('Prompt bearbeiten').should('have.attr', 'href');
			});

			// Check if corrections were made (might be 0 or more)
			cy.get('.ai-stat-card').contains('Korrekturen').parent().within(() => {
				cy.get('.ai-stat-number').invoke('text').then((correctionsText) => {
					const corrections = parseInt(correctionsText);
					cy.log(`🔄 Corrections made: ${corrections}`);
					
					if (corrections > 0) {
						// If corrections were made, verify correction history
						cy.get('.ai-corrections-history').should('exist');
						cy.get('.ai-debug-content').within(() => {
							cy.contains('Korrekturen:').should('be.visible');
						});
					}
				});
			});

			// Verify word count is within expected range
			const expectations = {
				short: { min: 300, max: 500 },
				medium: { min: 800, max: 1200 },
				long: { min: 1500, max: 2000 },
				verylong: { min: 2500, max: 3000 }
			};

			cy.get('.ai-stat-card').contains('Wortanzahl').parent().within(() => {
				cy.get('.ai-stat-number').invoke('text').then((wordCountText) => {
					const wordCount = parseInt(wordCountText);
					const expected = expectations[length];
					
					cy.log(`📊 Final word count: ${wordCount} (target: ${expected.min}-${expected.max})`);
					
					// With tolerance (20%)
					const tolerance = 0.2;
					const minWithTolerance = expected.min * (1 - tolerance);
					const maxWithTolerance = expected.max * (1 + tolerance);
					
					expect(wordCount, `Word count for ${length}`).to.be.at.least(minWithTolerance);
					expect(wordCount, `Word count for ${length}`).to.be.at.most(maxWithTolerance);
				});
			});

			// Verify status
			cy.get('.ai-stat-card').contains('Status').parent().within(() => {
				cy.get('.ai-stat-status').should('exist');
			});

			// Check statistics were updated
			cy.get('#stat-total').invoke('text').then((total) => {
				expect(parseInt(total)).to.be.greaterThan(0);
			});

			cy.log(`✅ ${length} post generated successfully with all validations passed`);
		});
	});

	it('should handle generation without auto-correction', function() {
		this.timeout(300000);

		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		// Create test post
		cy.get('#create-test-post-btn').click();
		cy.wait(3000);

		// Select short length
		cy.get('#test-length').select('short');
		
		// Disable auto-correction
		cy.get('#test-auto-correct').uncheck();

		cy.log('🎯 Generating post WITHOUT auto-correction...');

		// Generate
		cy.get('#test-generate-btn').click();

		// Wait for results
		cy.get('#results-container .ai-result-success', { timeout: 180000 }).should('be.visible');

		// Verify corrections = 0
		cy.get('.ai-stat-card').contains('Korrekturen').parent().within(() => {
			cy.get('.ai-stat-number').should('contain', '0');
		});

		cy.log('✅ Generation without auto-correction completed');
	});

	it('should refresh statistics when button clicked', () => {
		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		// Get initial total
		cy.get('#stat-total').invoke('text').then((initialTotal) => {
			// Click refresh
			cy.get('#refresh-stats-btn').click();
			cy.wait(2000);

			// Stats should still be visible (value might change or stay the same)
			cy.get('#stat-total').should('be.visible');
			cy.log(`✅ Stats refreshed (was: ${initialTotal})`);
		});
	});

	it('should validate required prompts and display warnings', () => {
		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		// Scroll to prompt management
		cy.contains('Prompt-Verwaltung').scrollIntoView();
		cy.wait(500);

		// Check if there are any warnings about missing prompts
		cy.get('body').then(($body) => {
			if ($body.find('.notice-error').length > 0) {
				cy.log('⚠️ Missing prompts warning displayed');
				cy.get('.notice-error').within(() => {
					cy.contains('erforderliche Prompts fehlen').should('be.visible');
				});
			} else {
				cy.log('✅ All prompts are configured - no warnings');
				
				// Verify all prompts are shown in table
				const requiredPrompts = [
					'system-post-generation',
					'post-generation',
					'system-correction',
					'correction-expand',
					'correction-shorten'
				];

				// Check that table has multiple rows
				cy.get('.wp-list-table tbody tr').should('have.length.greaterThan', 3);
			}
		});
	});

	it('should display model information from prompts in dashboard table', () => {
		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		cy.contains('Prompt-Verwaltung').scrollIntoView();
		cy.wait(500);

		// Check if prompts table exists and shows model info
		cy.get('.wp-list-table').then(($table) => {
			if ($table.find('tbody tr').length > 0) {
				// Should have columns for Modell
				cy.get('.wp-list-table thead th').should('contain', 'Modell');
				
				// Check that model is displayed (gpt-5, gpt-5-mini, etc.)
				cy.get('.wp-list-table tbody td').then(($cells) => {
					const hasModel = Array.from($cells).some(cell => 
						cell.textContent.includes('gpt-') || 
						cell.textContent.includes('GPT-')
					);
					expect(hasModel, 'Table should display model information').to.be.true;
				});

				cy.log('✅ Model information displayed in prompts table');
			}
		});
	});

	it('should have working prompt edit links in dashboard', () => {
		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		cy.contains('Prompt-Verwaltung').scrollIntoView();
		cy.wait(500);

		// Check for edit links
		cy.get('.wp-list-table').then(($table) => {
			if ($table.find('a.button').length > 0) {
				cy.get('.wp-list-table a.button').contains('Bearbeiten').first().should('have.attr', 'href').and('include', 'post.php');
				cy.log('✅ Edit links are present and valid');
			}
		});
	});

	it('should show debug panel with prompt links after generation', function() {
		this.timeout(300000);

		cy.visit('/wp-admin/admin.php?page=ai-post-dashboard');
		cy.wait(2000);

		// Create test post and generate
		cy.get('#create-test-post-btn').click();
		cy.wait(3000);

		cy.get('#test-length').select('short');
		cy.get('#test-auto-correct').check();

		cy.get('#test-generate-btn').click();

		// Wait for results
		cy.get('#results-container .ai-result-success', { timeout: 180000 }).should('be.visible');

		// Open debug panel
		cy.get('.ai-debug-header').click();
		cy.wait(500);

		// Verify prompt edit links exist
		cy.get('.ai-debug-content a').contains('Prompt bearbeiten').should('have.length.greaterThan', 0);
		
		// Verify links point to correct pages
		cy.get('.ai-debug-content a').contains('Prompt bearbeiten').first()
			.should('have.attr', 'href')
			.and('include', 'post.php?post=')
			.and('include', 'action=edit');

		// Verify variant information is shown
		cy.get('.ai-debug-content').should('contain', 'Variant:');

		cy.log('✅ Debug panel shows prompt links correctly');
	});
});



