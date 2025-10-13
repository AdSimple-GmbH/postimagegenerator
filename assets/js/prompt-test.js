/**
 * AI Prompt Test Functionality
 */

(function($) {
	'use strict';

	const PromptTest = {
		init: function() {
			this.bindEvents();
			this.setupCostCalculator();
		},

		bindEvents: function() {
			$('#test-prompt-btn').on('click', this.openTestModal.bind(this));
			$(document).on('click', '#run-test-btn', this.runTest.bind(this));
			$(document).on('click', '.test-modal-close', this.closeTestModal.bind(this));
			$(document).on('click', '#test-prompt-modal', function(e) {
				if (e.target === this) {
					PromptTest.closeTestModal();
				}
			});
		},

		setupCostCalculator: function() {
			$('#gpt_model').on('change', function() {
				const model = $(this).val();
				const costs = {
					'gpt-5': 1.25,
					'gpt-5-mini': 0.25,
					'gpt-5-nano': 0.05,
					'gpt-image-1': 0.00
				};
				const cost = costs[model] || 0;
				$('#estimated_cost').text('$' + cost.toFixed(2));
			});
		},

		openTestModal: function(e) {
			e.preventDefault();

			const modal = $('<div id="test-modal-overlay" class="test-modal-overlay"></div>');
			const content = `
				<div class="test-modal">
					<div class="test-modal-header">
						<h2>Prompt testen</h2>
						<button type="button" class="test-modal-close">&times;</button>
					</div>
					<div class="test-modal-body">
						<table class="form-table">
							<tr>
								<th><label for="modal-test-post-id">Test-Post ID</label></th>
								<td>
									<input type="number" id="modal-test-post-id" class="regular-text" placeholder="Optional">
									<p class="description">Gib eine Post-ID an, um Variablen zu ersetzen</p>
								</td>
							</tr>
							<tr>
								<th><label for="modal-test-variant">Variante</label></th>
								<td>
									<input type="text" id="modal-test-variant" class="regular-text" placeholder="z.B. short, medium">
									<p class="description">Nur für Prompts mit Varianten</p>
								</td>
							</tr>
						</table>
						
						<div id="modal-test-progress" style="display: none;">
							<p><span class="spinner is-active"></span> Teste Prompt...</p>
							<p class="description">Dies kann 30-120 Sekunden dauern...</p>
						</div>
						
						<div id="modal-test-result"></div>
					</div>
					<div class="test-modal-footer">
						<button type="button" id="run-test-btn" class="button button-primary">
							<span class="dashicons dashicons-yes"></span> Test starten
						</button>
						<button type="button" class="button test-modal-close">Abbrechen</button>
					</div>
				</div>
			`;

			modal.html(content);
			$('body').append(modal);
			modal.fadeIn(200);
		},

		closeTestModal: function() {
			$('#test-modal-overlay').fadeOut(200, function() {
				$(this).remove();
			});
		},

		runTest: function(e) {
			e.preventDefault();

			const postId = $('#modal-test-post-id').val();
			const variant = $('#modal-test-variant').val();

			$('#run-test-btn').prop('disabled', true);
			$('#modal-test-progress').show();
			$('#modal-test-result').empty();

			$.ajax({
				url: aiPromptTest.ajaxUrl,
				type: 'POST',
				data: {
					action: 'test_ai_prompt',
					nonce: aiPromptTest.nonce,
					prompt_id: aiPromptTest.postId,
					test_post_id: postId,
					test_variant: variant
				},
				timeout: 180000, // 3 minutes
				success: function(response) {
					$('#modal-test-progress').hide();

					if (response.success) {
						PromptTest.showTestSuccess(response.data);
					} else {
						PromptTest.showTestError(response.data.message);
					}
				},
				error: function(xhr, status, error) {
					$('#modal-test-progress').hide();
					
					if (status === 'timeout') {
						PromptTest.showTestError('Timeout: Der Test hat zu lange gedauert.');
					} else {
						PromptTest.showTestError('Netzwerkfehler: ' + error);
					}
				},
				complete: function() {
					$('#run-test-btn').prop('disabled', false);
				}
			});
		},

		showTestSuccess: function(data) {
			let html = '<div class="test-result-success">';
			html += '<h3 style="color: green;"><span class="dashicons dashicons-yes"></span> Test erfolgreich!</h3>';

			// Show usage info if available
			if (data.result && data.result.usage) {
				const usage = data.result.usage;
				html += '<div class="test-usage-info">';
				html += '<h4>Token-Verwendung:</h4>';
				html += '<ul>';
				if (usage.prompt_tokens) {
					html += '<li>Prompt Tokens: <strong>' + usage.prompt_tokens + '</strong></li>';
				}
				if (usage.completion_tokens) {
					html += '<li>Completion Tokens: <strong>' + usage.completion_tokens + '</strong></li>';
				}
				if (usage.total_tokens) {
					html += '<li>Total Tokens: <strong>' + usage.total_tokens + '</strong></li>';
				}
				html += '</ul>';
				html += '</div>';
			}

			// Show response preview
			if (data.data) {
				html += '<div class="test-response-preview">';
				html += '<h4>Antwort-Vorschau:</h4>';

				if (data.data.choices && data.data.choices[0]) {
					const content = data.data.choices[0].message.content;
					const preview = content.substring(0, 1000) + (content.length > 1000 ? '...' : '');
					html += '<pre>' + this.escapeHtml(preview) + '</pre>';
				} else if (data.data.data && data.data.data[0]) {
					// Image generation result
					html += '<p>✓ Bild erfolgreich generiert</p>';
					if (data.data.data[0].url) {
						html += '<p><a href="' + data.data.data[0].url + '" target="_blank">Bild öffnen</a></p>';
					}
				}

				html += '</div>';
			}

			html += '<p class="description">Das Test-Ergebnis wurde im Prompt gespeichert.</p>';
			html += '</div>';

			$('#modal-test-result').html(html);

			// Reload page after 3 seconds to show updated test result
			setTimeout(function() {
				location.reload();
			}, 3000);
		},

		showTestError: function(message) {
			let html = '<div class="test-result-error">';
			html += '<h3 style="color: red;"><span class="dashicons dashicons-dismiss"></span> Test fehlgeschlagen</h3>';
			html += '<p>' + this.escapeHtml(message) + '</p>';
			html += '</div>';

			$('#modal-test-result').html(html);
		},

		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		PromptTest.init();
	});

})(jQuery);


