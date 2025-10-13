/**
 * AI Featured Image Dashboard JavaScript
 * Handles live testing and result display
 */

(function($) {
	'use strict';

	const AIDashboard = {
		init: function() {
			this.bindEvents();
			this.setupProgressAnimation();
		},

		bindEvents: function() {
			$('#ai-test-form').on('submit', this.handleTestSubmit.bind(this));
			$('#create-test-post-btn').on('click', this.handleCreateTestPost.bind(this));
			$('#refresh-stats-btn').on('click', this.handleRefreshStats.bind(this));
		},

		setupProgressAnimation: function() {
			// Animated progress bar fill
			this.progressInterval = null;
		},

		handleTestSubmit: function(e) {
			e.preventDefault();

			const postId = $('#test-post-select').val();
			const length = $('#test-length').val();
			const autoCorrect = $('#test-auto-correct').is(':checked');
			const maxCorrections = $('#test-max-corrections').val();

			if (!postId) {
				this.showError('Bitte wähle einen Post aus oder erstelle einen neuen.');
				return;
			}

			this.startTest({
				post_id: postId,
				length: length,
				auto_correct: autoCorrect,
				max_corrections: maxCorrections
			});
		},

		handleCreateTestPost: function(e) {
			e.preventDefault();
			
			const $btn = $(e.currentTarget);
			$btn.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: aiDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_dashboard_create_test_post',
					nonce: aiDashboard.nonce
				},
				success: (response) => {
					if (response.success) {
						const data = response.data;
						
						// Add to dropdown
						const option = $('<option>')
							.val(data.post_id)
							.text(`#${data.post_id} - ${data.post_title}`)
							.prop('selected', true);
						
						$('#test-post-select').append(option);
						
						this.showSuccess(`Test-Post erstellt: ${data.post_title}`);
					} else {
						this.showError(response.data.message || 'Fehler beim Erstellen des Posts');
					}
				},
				error: () => {
					this.showError('Netzwerkfehler beim Erstellen des Posts');
				},
				complete: () => {
					$btn.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

		handleRefreshStats: function(e) {
			e.preventDefault();
			
			const $btn = $(e.currentTarget);
			$btn.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: aiDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_dashboard_get_stats',
					nonce: aiDashboard.nonce
				},
				success: (response) => {
					if (response.success) {
						this.updateStats(response.data);
						this.showSuccess('Statistiken aktualisiert');
					}
				},
				complete: () => {
					$btn.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

	startTest: function(params) {
		this.showProgress('Generiere AI-Content...');
		$('#test-generate-btn').prop('disabled', true);
		$('#results-container').html('<div class="ai-loading"><span class="spinner is-active"></span><p>Bitte warten...</p></div>');

			const startTime = Date.now();

			$.ajax({
				url: aiDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_dashboard_test_generation',
					nonce: aiDashboard.nonce,
					...params
				},
				timeout: 300000, // 5 minutes
				success: (response) => {
					const duration = ((Date.now() - startTime) / 1000).toFixed(1);
					
					if (response.success) {
						this.showResults(response.data, duration);
						this.refreshStats();
					} else {
						this.showError(response.data.message || 'Fehler bei der Generierung');
					}
				},
				error: (xhr, status, error) => {
					if (status === 'timeout') {
						this.showError('Timeout: Die Anfrage hat zu lange gedauert. Bitte versuche es erneut.');
					} else {
						this.showError(`Netzwerkfehler: ${error}`);
					}
				},
				complete: () => {
					this.hideProgress();
					$('#test-generate-btn').prop('disabled', false);
				}
			});
		},

		showProgress: function(message) {
			$('#progress-message').text(message);
			$('#test-progress').fadeIn();
			
			// Animate progress bar
			let progress = 0;
			const $fill = $('.ai-progress-fill');
			
			this.progressInterval = setInterval(() => {
				progress += Math.random() * 15;
				if (progress > 90) progress = 90;
				$fill.css('width', progress + '%');
			}, 1000);
		},

		hideProgress: function() {
			clearInterval(this.progressInterval);
			$('.ai-progress-fill').css('width', '100%');
			
			setTimeout(() => {
				$('#test-progress').fadeOut(() => {
					$('.ai-progress-fill').css('width', '0%');
				});
			}, 500);
		},

		showResults: function(data, duration) {
			const wordCount = data.data.word_count;
			const corrections = data.data.corrections;
			const content = data.data.content_html;
			
			const isValid = wordCount.valid;
			const statusClass = isValid ? 'success' : 'warning';
			const statusIcon = isValid ? 'yes-alt' : 'warning';
			const statusText = isValid ? 'Gültig ✓' : 'Außerhalb Zielbereich';

			// Build corrections history HTML
			let correctionsHtml = '';
			if (corrections.history && corrections.history.length > 0) {
				correctionsHtml = '<div class="ai-corrections-history"><h4>Korrektur-Verlauf:</h4><ol>';
				corrections.history.forEach((corr, index) => {
					const direction = corr.direction === 'expand' ? 'Erweitert' : 'Gekürzt';
					const arrow = corr.direction === 'expand' ? '↑' : '↓';
					correctionsHtml += `<li><strong>${direction} ${arrow}</strong>: ${corr.before_words} → ${corr.after_words} Wörter</li>`;
				});
				correctionsHtml += '</ol></div>';
			}

			// Build category and tags HTML
			let metaHtml = '';
			if (data.data.category_name) {
				metaHtml += `<div class="ai-meta-item"><strong>Kategorie:</strong> ${this.escapeHtml(data.data.category_name)}</div>`;
			}
			if (data.data.tags && data.data.tags.length > 0) {
				const tagsHtml = data.data.tags.map(tag => `<span class="ai-tag">${this.escapeHtml(tag)}</span>`).join(' ');
				metaHtml += `<div class="ai-meta-item"><strong>Tags:</strong> ${tagsHtml}</div>`;
			}

			const html = `
				<div class="ai-result-success">
					<div class="ai-result-header ${statusClass}">
						<span class="dashicons dashicons-${statusIcon}"></span>
						<h3>Generierung erfolgreich!</h3>
						<span class="ai-duration">Dauer: ${duration}s</span>
					</div>

					<div class="ai-result-stats">
						<div class="ai-stat-card">
							<div class="ai-stat-icon">📊</div>
							<div class="ai-stat-content">
								<div class="ai-stat-label">Wortanzahl</div>
								<div class="ai-stat-number">${wordCount.final}</div>
								<div class="ai-stat-detail">Ziel: ${wordCount.target_min}-${wordCount.target_max}</div>
							</div>
						</div>

						<div class="ai-stat-card">
							<div class="ai-stat-icon">🎯</div>
							<div class="ai-stat-content">
								<div class="ai-stat-label">Status</div>
								<div class="ai-stat-status ${statusClass}">${statusText}</div>
								<div class="ai-stat-detail">${wordCount.message}</div>
							</div>
						</div>

						<div class="ai-stat-card">
							<div class="ai-stat-icon">🔄</div>
							<div class="ai-stat-content">
								<div class="ai-stat-label">Korrekturen</div>
								<div class="ai-stat-number">${corrections.made}</div>
								<div class="ai-stat-detail">
									${corrections.enabled ? `Max: ${corrections.max_allowed}` : 'Deaktiviert'}
								</div>
							</div>
						</div>

						<div class="ai-stat-card">
							<div class="ai-stat-icon">📈</div>
							<div class="ai-stat-content">
								<div class="ai-stat-label">Änderung</div>
								<div class="ai-stat-number ${wordCount.final > wordCount.initial ? 'increase' : (wordCount.final < wordCount.initial ? 'decrease' : '')}">
									${wordCount.initial} → ${wordCount.final}
								</div>
								<div class="ai-stat-detail">
									${this.calculatePercentageChange(wordCount.initial, wordCount.final)}
								</div>
							</div>
						</div>
					</div>

					${correctionsHtml}

					${metaHtml ? `<div class="ai-meta-info">${metaHtml}</div>` : ''}

					<div class="ai-content-preview">
						<h4>Content-Vorschau:</h4>
						<div class="ai-content-box">
							${content.substring(0, 2000)}${content.length > 2000 ? '...' : ''}
						</div>
						<p class="ai-content-note">
							<span class="dashicons dashicons-info"></span>
							Content wurde nicht automatisch gespeichert. Bitte im WordPress-Editor prüfen und manuell speichern.
						</p>
					</div>

					${this.renderDebugPanel(data.data.debug)}
				</div>
			`;

			$('#results-container').html(html);
		},

		calculatePercentageChange: function(initial, final) {
			if (initial === 0) return '+100%';
			const change = ((final - initial) / initial * 100).toFixed(1);
			if (change > 0) return `+${change}%`;
			if (change < 0) return `${change}%`;
			return '±0%';
		},

		updateStats: function(stats) {
			$('#stat-total').text(stats.total);
			$('#stat-today').text(stats.today);
			$('#stat-success').text(stats.success_rate + '%');
			$('#stat-corrections').text(stats.avg_corrections);
		},

		refreshStats: function() {
			$.ajax({
				url: aiDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_dashboard_get_stats',
					nonce: aiDashboard.nonce
				},
				success: (response) => {
					if (response.success) {
						this.updateStats(response.data);
					}
				}
			});
		},

		showError: function(message) {
			const html = `
				<div class="ai-result-error">
					<span class="dashicons dashicons-dismiss"></span>
					<h3>Fehler</h3>
					<p>${this.escapeHtml(message)}</p>
				</div>
			`;
			$('#results-container').html(html);
		},

		showSuccess: function(message) {
			// Create a temporary notice
			const notice = $(`
				<div class="notice notice-success is-dismissible">
					<p>${this.escapeHtml(message)}</p>
				</div>
			`);
			
			$('.ai-dashboard-wrap h1').after(notice);
			
			setTimeout(() => {
				notice.fadeOut(() => notice.remove());
			}, 3000);
		},

	escapeHtml: function(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, m => map[m]);
	},

	renderDebugPanel: function(debug) {
		if (!debug) return '';

		const initial = debug.initial_generation;
		const corrections = debug.corrections || [];

		let correctionsHtml = '';
		if (corrections.length > 0) {
			correctionsHtml = '<h5>🔄 Korrekturen:</h5>';
			corrections.forEach((corr, idx) => {
				const dirIcon = corr.direction === 'expand' ? '↑ Erweitern' : '↓ Kürzen';
				correctionsHtml += `
					<div class="ai-debug-correction">
						<h6>Korrektur ${idx + 1}: ${dirIcon}</h6>
						<div class="ai-debug-row">
							<strong>Request:</strong>
							<ul>
								<li><strong>Modell:</strong> ${this.escapeHtml(corr.request.model)}</li>
								<li><strong>Temperature:</strong> ${corr.request.temperature}</li>
								<li><strong>Max Tokens:</strong> ${corr.request.max_tokens}</li>
								<li><strong>Aktuelle Wörter:</strong> ${corr.request.current_words}</li>
								<li><strong>System Prompt:</strong> 
									${corr.request.system_prompt_edit_link ? 
										`<a href="${corr.request.system_prompt_edit_link}" target="_blank" style="color: #2271b1; text-decoration: underline;">✏️ Prompt bearbeiten</a><br>` : ''}
									<code>${this.escapeHtml(corr.request.system_prompt)}</code>
								</li>
								<li><strong>User Prompt (${corr.request.user_prompt_slug || 'correction'}):</strong> 
									${corr.request.user_prompt_edit_link ? 
										`<a href="${corr.request.user_prompt_edit_link}" target="_blank" style="color: #2271b1; text-decoration: underline;">✏️ Prompt bearbeiten</a><br>` : ''}
									<code>${this.escapeHtml(corr.request.user_prompt)}</code>
								</li>
							</ul>
						</div>
						<div class="ai-debug-row">
							<strong>Response:</strong>
							<ul>
								<li><strong>Neue Wörter:</strong> ${corr.response.new_word_count}</li>
								<li><strong>Verwendetes Modell:</strong> ${this.escapeHtml(corr.response.model)}</li>
								${corr.response.usage ? `
									<li><strong>Token Usage:</strong> 
										Input: ${corr.response.usage.prompt_tokens || 0}, 
										Output: ${corr.response.usage.completion_tokens || 0}, 
										Total: ${corr.response.usage.total_tokens || 0}
									</li>
								` : ''}
								<li><strong>Content Preview:</strong> <code>${this.escapeHtml(corr.response.content_preview)}</code></li>
							</ul>
						</div>
					</div>
				`;
			});
		}

		return `
			<div class="ai-debug-panel">
				<div class="ai-debug-header" onclick="this.parentElement.classList.toggle('expanded')">
					<span class="dashicons dashicons-code-standards"></span>
					<h4>🔍 Debug-Informationen (OpenAI Kommunikation)</h4>
					<span class="ai-debug-toggle">▼</span>
				</div>
				<div class="ai-debug-content">
					<h5>📤 Initiale Generierung:</h5>
					<div class="ai-debug-row">
						<strong>Request:</strong>
						<ul>
							<li><strong>Modell:</strong> ${this.escapeHtml(initial.request.model)}</li>
							<li><strong>Temperature:</strong> ${initial.request.temperature}</li>
							<li><strong>Max Tokens:</strong> ${initial.request.max_tokens}</li>
							<li><strong>Response Format:</strong> ${initial.request.response_format || 'text'}</li>
							<li><strong>System Prompt:</strong> 
								${initial.request.system_prompt_edit_link ? 
									`<a href="${initial.request.system_prompt_edit_link}" target="_blank" style="color: #2271b1; text-decoration: underline;">✏️ Prompt bearbeiten</a><br>` : ''}
								<code>${this.escapeHtml(initial.request.system_prompt)}</code>
							</li>
							<li><strong>User Prompt (Variant: ${initial.request.user_prompt_variant || 'default'}):</strong> 
								${initial.request.user_prompt_edit_link ? 
									`<a href="${initial.request.user_prompt_edit_link}" target="_blank" style="color: #2271b1; text-decoration: underline;">✏️ Prompt bearbeiten</a><br>` : ''}
								<code>${this.escapeHtml(initial.request.user_prompt)}</code>
							</li>
						</ul>
					</div>
					<div class="ai-debug-row">
						<strong>Response:</strong>
						<ul>
							<li><strong>Verwendetes Modell:</strong> ${this.escapeHtml(initial.response.model)}</li>
							${initial.response.usage ? `
								<li><strong>Token Usage:</strong> 
									Input: ${initial.response.usage.prompt_tokens || 0}, 
									Output: ${initial.response.usage.completion_tokens || 0}, 
									Total: ${initial.response.usage.total_tokens || 0}
								</li>
							` : ''}
							<li><strong>Raw Content (gekürzt):</strong> <code>${this.escapeHtml(initial.response.raw_content)}</code></li>
						</ul>
					</div>

					${correctionsHtml}
				</div>
			</div>
		`;
	}
	};

	// Initialize on document ready
	$(document).ready(function() {
		AIDashboard.init();
	});

})(jQuery);

