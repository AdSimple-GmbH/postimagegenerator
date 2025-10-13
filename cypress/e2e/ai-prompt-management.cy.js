// AI Prompt Management Tests
// Tests for the Custom Post Type based prompt management system
// Code comments are in English per project rule.

describe('AI Prompt Management', () => {
	beforeEach(() => {
		cy.wpLogin();
	});

	it('should display AI Prompts menu item', () => {
		cy.visit('/wp-admin/');
		cy.wait(2000);

		// Check for AI Prompts menu
		cy.get('#menu-posts-ai_prompt').should('exist');
		cy.get('#menu-posts-ai_prompt a').should('contain', 'AI Prompts');

		cy.log('✅ AI Prompts menu exists');
	});

	it('should list existing prompts', () => {
		cy.visit('/wp-admin/edit.php?post_type=ai_prompt');
		cy.wait(2000);

		// Verify page loaded
		cy.contains('AI Prompts').should('be.visible');

		// Should have at least the default prompts
		cy.get('.wp-list-table tbody tr').should('have.length.greaterThan', 0);

		// Check for required default prompts
		const requiredPrompts = [
			'System: Post-Generierung',
			'Post-Generierung',
			'System: Korrektur',
			'Korrektur: Erweitern',
			'Korrektur: Kürzen',
			'Bild-Generierung'
		];

		cy.get('.wp-list-table').then(($table) => {
			const tableText = $table.text();
			let foundCount = 0;
			
			requiredPrompts.forEach((promptName) => {
				if (tableText.includes(promptName)) {
					foundCount++;
					cy.log(`✓ Found: ${promptName}`);
				}
			});

			expect(foundCount, 'Should find most required prompts').to.be.greaterThan(3);
		});

		cy.log('✅ Prompts list displayed with default prompts');
	});

	it('should display prompt columns correctly', () => {
		cy.visit('/wp-admin/edit.php?post_type=ai_prompt');
		cy.wait(2000);

		// Check for custom columns
		cy.get('.wp-list-table thead th').should('contain', 'Slug');
		cy.get('.wp-list-table thead th').should('contain', 'Typ');
		cy.get('.wp-list-table thead th').should('contain', 'Modell');
		cy.get('.wp-list-table thead th').should('contain', 'Status');

		cy.log('✅ Prompt list columns are correct');
	});

	it('should allow creating a new prompt', () => {
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Verify we're on new prompt page
		cy.get('#title').should('exist');

		// Check for custom meta boxes
		cy.get('#ai_prompt_config').should('exist');
		cy.get('#ai_prompt_gpt_params').should('exist');

		// Check for fields
		cy.get('input[name="_prompt_slug"]').should('exist');
		cy.get('select[name="_prompt_type"]').should('exist');
		cy.get('select[name="_gpt_model"]').should('exist');
		cy.get('input[name="_gpt_temperature"]').should('exist');
		cy.get('input[name="_gpt_max_tokens"]').should('exist');

		cy.log('✅ New prompt form has all required fields');
	});

	it('should show GPT model options including GPT-5 variants', () => {
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		cy.get('select[name="_gpt_model"]').within(() => {
			// Check for GPT-5 options
			cy.get('option').should('contain', 'GPT-5');
			cy.get('option').should('contain', 'GPT-5 mini');
			cy.get('option').should('contain', 'GPT-5 nano');
		});

		cy.log('✅ GPT-5 model variants are available');
	});

	it('should display prompt type taxonomy', () => {
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Check for Prompt-Typen taxonomy box
		cy.get('#prompt_typediv').should('exist');
		cy.get('#prompt_typediv').should('contain', 'Prompt-Typen');

		// Should have checkboxes for different types
		cy.get('#prompt_typediv .categorychecklist').should('exist');

		cy.log('✅ Prompt type taxonomy is displayed');
	});

	it('should have default values for GPT parameters', () => {
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Check default model
		cy.get('select[name="_gpt_model"]').should('have.value', 'gpt-5-mini');

		// Check default temperature
		cy.get('input[name="_gpt_temperature"]').should('have.value', '0.7');

		// Check default response format
		cy.get('select[name="_gpt_response_format"]').should('have.value', 'text');

		// Max tokens should have a default based on prompt type
		cy.get('input[name="_gpt_max_tokens"]').invoke('val').then((val) => {
			const maxTokens = parseInt(val);
			expect(maxTokens).to.be.greaterThan(0);
			cy.log(`Default max tokens: ${maxTokens}`);
		});

		cy.log('✅ Default GPT parameters are set correctly');
	});

	it('should show helpful descriptions for GPT parameters', () => {
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Check for descriptions
		cy.get('#ai_prompt_gpt_params').within(() => {
			cy.contains('Empfehlung').should('exist');
			cy.contains('Deterministisch').should('exist');
			cy.contains('Kreativ').should('exist');
		});

		cy.log('✅ Parameter descriptions are displayed');
	});

	it('should edit an existing prompt', () => {
		cy.visit('/wp-admin/edit.php?post_type=ai_prompt');
		cy.wait(2000);

		// Click edit on first prompt
		cy.get('.wp-list-table tbody tr').first().within(() => {
			cy.get('.row-title').click();
		});

		cy.wait(2000);

		// Verify we're in edit mode
		cy.url().should('include', 'post.php?post=');
		cy.url().should('include', 'action=edit');

		// Check fields are populated
		cy.get('#title').invoke('val').should('not.be.empty');
		cy.get('input[name="_prompt_slug"]').invoke('val').should('not.be.empty');

		cy.log('✅ Can edit existing prompt');
	});

	it('should have test function in prompt editor', () => {
		cy.visit('/wp-admin/edit.php?post_type=ai_prompt');
		cy.wait(2000);

		// Open first prompt for editing
		cy.get('.wp-list-table tbody tr').first().within(() => {
			cy.get('.row-title').click();
		});

		cy.wait(2000);

		// Check for test meta box
		cy.get('#ai_prompt_test').should('exist');
		cy.get('#ai_prompt_test').should('contain', 'Prompt testen');

		// Check for test button
		cy.get('#test-prompt-btn').should('exist');

		cy.log('✅ Test function is available in prompt editor');
	});

	it('should validate required fields when saving', () => {
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Try to publish without required fields
		cy.get('#publish').click();
		cy.wait(2000);

		// Should show validation message or stay on same page
		// WordPress will show "Der Titel wird benötigt" error
		cy.url().should('include', 'post-new.php');

		cy.log('✅ Validation prevents saving without required fields');
	});

	it('should save prompt with all fields', () => {
		const timestamp = Date.now();
		
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Fill in title
		cy.get('#title').type(`Test Prompt ${timestamp}`);

		// Fill in slug
		cy.get('input[name="_prompt_slug"]').clear().type(`test-prompt-${timestamp}`);

		// Select prompt type
		cy.get('select[name="_prompt_type"]').select('Generation');

		// Select model
		cy.get('select[name="_gpt_model"]').select('gpt-5-mini');

		// Set temperature
		cy.get('input[name="_gpt_temperature"]').clear().type('0.7');

		// Set max tokens
		cy.get('input[name="_gpt_max_tokens"]').clear().type('2000');

		// Add content
		cy.get('#content').then($editor => {
			if ($editor.is(':visible')) {
				cy.get('#content').type('This is a test prompt content');
			} else {
				// If visual editor is active, use tinyMCE
				cy.window().then(win => {
					if (win.tinyMCE && win.tinyMCE.activeEditor) {
						win.tinyMCE.activeEditor.setContent('This is a test prompt content');
					}
				});
			}
		});

		// Publish
		cy.get('#publish').click();
		cy.wait(4000);

		// Verify published
		cy.get('#message').should('be.visible').and('contain', 'veröffentlicht');

		cy.log('✅ Prompt saved successfully with all fields');

		// Clean up - trash the test prompt
		cy.get('#delete-action a.submitdelete').click();
		cy.wait(2000);
	});

	it('should show active/inactive status', () => {
		cy.visit('/wp-admin/edit.php?post_type=ai_prompt');
		cy.wait(2000);

		// Check status column exists and shows values
		cy.get('.wp-list-table tbody tr').first().within(() => {
			cy.get('td').should('contain.text', 'Aktiv').or('contain.text', 'Inaktiv');
		});

		cy.log('✅ Prompt status is displayed');
	});

	it('should allow toggling active status', () => {
		cy.visit('/wp-admin/edit.php?post_type=ai_prompt');
		cy.wait(2000);

		// Open first prompt
		cy.get('.wp-list-table tbody tr').first().within(() => {
			cy.get('.row-title').click();
		});

		cy.wait(2000);

		// Find and toggle active checkbox
		cy.get('input[name="_is_active"]').then($checkbox => {
			const initialState = $checkbox.is(':checked');
			
			if (initialState) {
				cy.get('input[name="_is_active"]').uncheck();
			} else {
				cy.get('input[name="_is_active"]').check();
			}

			// Update
			cy.get('#publish').click();
			cy.wait(3000);

			// Verify saved
			cy.get('#message').should('be.visible');

			// Toggle back
			cy.get('input[name="_is_active"]').then($checkbox2 => {
				if (initialState) {
					cy.get('input[name="_is_active"]').check();
				} else {
					cy.get('input[name="_is_active"]').uncheck();
				}
			});

			cy.get('#publish').click();
			cy.wait(2000);

			cy.log('✅ Active status can be toggled');
		});
	});

	it('should support JSON variants for multi-length prompts', () => {
		cy.visit('/wp-admin/edit.php?post_type=ai_prompt');
		cy.wait(2000);

		// Find "Post-Generierung (mit Varianten)" prompt
		cy.get('.wp-list-table').then($table => {
			if ($table.text().includes('Post-Generierung')) {
				cy.contains('.row-title', 'Post-Generierung').click();
				cy.wait(2000);

				// Check for variants textarea
				cy.get('textarea[name="_prompt_variants"]').should('exist');
				
				// Verify it contains JSON
				cy.get('textarea[name="_prompt_variants"]').invoke('val').then((variants) => {
					if (variants && variants.trim().length > 0) {
						expect(() => JSON.parse(variants)).to.not.throw();
						const parsed = JSON.parse(variants);
						
						// Should have length variants
						expect(parsed).to.have.any.keys('short', 'medium', 'long', 'verylong');
						
						cy.log('✅ Variants are valid JSON with length options');
					} else {
						cy.log('ℹ️ No variants defined for this prompt');
					}
				});
			}
		});
	});

	it('should display model pricing information', () => {
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Check that model select shows pricing
		cy.get('select[name="_gpt_model"]').within(() => {
			// Should show pricing in option text
			cy.get('option').then($options => {
				const hasPrice = Array.from($options).some(opt => 
					opt.textContent.includes('$')
				);
				expect(hasPrice, 'Model options should show pricing').to.be.true;
			});
		});

		cy.log('✅ Model pricing is displayed');
	});

	it('should sync prompt type meta with taxonomy', () => {
		const timestamp = Date.now();
		
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Create test prompt
		cy.get('#title').type(`Sync Test ${timestamp}`);
		cy.get('input[name="_prompt_slug"]').clear().type(`sync-test-${timestamp}`);
		
		// Select prompt type in meta field
		cy.get('select[name="_prompt_type"]').select('System: Generation');

		// Add content
		cy.get('#content').type('Test content for sync');

		// Publish
		cy.get('#publish').click();
		cy.wait(4000);

		// Verify taxonomy was synced
		cy.get('#prompt_typediv').within(() => {
			// "System" term should be checked
			cy.get('input[type="checkbox"]').then($checkboxes => {
				const systemChecked = Array.from($checkboxes).some(cb => 
					cb.checked && cb.nextSibling && cb.nextSibling.textContent.includes('System')
				);
				expect(systemChecked, 'System taxonomy term should be checked').to.be.true;
			});
		});

		cy.log('✅ Prompt type meta syncs with taxonomy');

		// Clean up
		cy.get('#delete-action a.submitdelete').click();
		cy.wait(2000);
	});

	it('should show max tokens recommendations based on prompt type', () => {
		cy.visit('/wp-admin/post-new.php?post_type=ai_prompt');
		cy.wait(2000);

		// Check for recommendations text
		cy.get('#ai_prompt_gpt_params').within(() => {
			cy.contains('Empfehlung').should('exist');
			
			// Should show different recommendations for different types
			cy.get('select[name="_prompt_type"]').select('Generation');
			cy.wait(500);
			
			// Look for recommendation text
			cy.get('.description').then($desc => {
				const hasRecommendation = Array.from($desc).some(el => 
					el.textContent.includes('3000-4000') || 
					el.textContent.includes('Empfehlung')
				);
				expect(hasRecommendation, 'Should show token recommendations').to.be.true;
			});
		});

		cy.log('✅ Max tokens recommendations are displayed');
	});
});



