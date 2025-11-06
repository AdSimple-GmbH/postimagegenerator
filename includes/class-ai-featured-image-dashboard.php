<?php
/**
 * Dashboard for testing AI post generation features.
 *
 * @package AI_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class AI_Featured_Image_Dashboard
 */
class AI_Featured_Image_Dashboard {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_dashboard_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
		add_action( 'wp_ajax_ai_dashboard_test_generation', array( $this, 'ajax_test_generation' ) );
		add_action( 'wp_ajax_ai_dashboard_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_ai_dashboard_create_test_post', array( $this, 'ajax_create_test_post' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_debug_meta_box' ) );
	}

	/**
	 * Add dashboard menu to WordPress admin.
	 */
	public function add_dashboard_menu() {
		add_menu_page(
			__( 'AI Post Generator Dashboard', 'ai-featured-image' ),
			__( 'AI Dashboard', 'ai-featured-image' ),
			'edit_posts',
			'ai-post-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-analytics',
			30
		);
	}

	/**
	 * Enqueue dashboard assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_dashboard_assets( $hook ) {
		if ( 'toplevel_page_ai-post-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ai-dashboard-css',
			plugins_url( '../assets/css/dashboard.css', __FILE__ ),
			array(),
			'1.0.1'
		);

		wp_enqueue_script(
			'ai-dashboard-js',
			plugins_url( '../assets/js/dashboard.js', __FILE__ ),
			array( 'jquery' ),
			'1.0.2',
			true
		);

		wp_localize_script(
			'ai-dashboard-js',
			'aiDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ai_dashboard_nonce' ),
				'restUrl' => rest_url( 'ai-featured-image/v1/generate-post' ),
			)
		);
	}

	/**
	 * Render the dashboard page.
	 */
	public function render_dashboard() {
		// Get recent posts for dropdown
		$recent_posts = get_posts( array(
			'numberposts' => 50,
			'post_status' => array( 'draft', 'publish' ),
			'orderby' => 'date',
			'order' => 'DESC',
		) );

		// Get statistics
		$stats = $this->get_generation_stats();

		?>
		<div class="wrap ai-dashboard-wrap">
			<h1><?php esc_html_e( 'AI Post Generator Dashboard', 'ai-featured-image' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Teste die AI-Post-Generierung mit automatischer Längenkorrektur.', 'ai-featured-image' ); ?>
			</p>

			<div class="ai-dashboard-grid">
				<!-- Left Column: Test Form -->
				<div class="ai-dashboard-column">
					<div class="ai-dashboard-card">
						<h2><?php esc_html_e( 'Test Configuration', 'ai-featured-image' ); ?></h2>
						
						<form id="ai-test-form">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="test-post-select"><?php esc_html_e( 'Wähle einen Post', 'ai-featured-image' ); ?></label>
									</th>
									<td>
										<select id="test-post-select" name="post_id" class="regular-text">
											<option value=""><?php esc_html_e( '-- Bitte wählen --', 'ai-featured-image' ); ?></option>
											<?php foreach ( $recent_posts as $post ) : ?>
												<option value="<?php echo esc_attr( $post->ID ); ?>">
													<?php echo esc_html( sprintf( '#%d - %s', $post->ID, $post->post_title ) ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description">
											<?php esc_html_e( 'Oder:', 'ai-featured-image' ); ?>
											<button type="button" id="create-test-post-btn" class="button">
												<?php esc_html_e( 'Neuen Test-Post erstellen', 'ai-featured-image' ); ?>
											</button>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="test-length"><?php esc_html_e( 'Länge', 'ai-featured-image' ); ?></label>
									</th>
									<td>
										<select id="test-length" name="length" class="regular-text">
											<option value="short"><?php esc_html_e( 'Kurz (300-500 Wörter)', 'ai-featured-image' ); ?></option>
											<option value="medium" selected><?php esc_html_e( 'Mittel (800-1200 Wörter)', 'ai-featured-image' ); ?></option>
											<option value="long"><?php esc_html_e( 'Lang (1500-2000 Wörter)', 'ai-featured-image' ); ?></option>
											<option value="verylong"><?php esc_html_e( 'Sehr Lang (2500-3000 Wörter)', 'ai-featured-image' ); ?></option>
										</select>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="test-auto-correct"><?php esc_html_e( 'Auto-Korrektur', 'ai-featured-image' ); ?></label>
									</th>
									<td>
										<label>
											<input type="checkbox" id="test-auto-correct" name="auto_correct" checked>
											<?php esc_html_e( 'Automatische Längenkorrektur aktivieren', 'ai-featured-image' ); ?>
										</label>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="test-max-corrections"><?php esc_html_e( 'Max. Korrekturen', 'ai-featured-image' ); ?></label>
									</th>
									<td>
										<input type="number" id="test-max-corrections" name="max_corrections" value="2" min="0" max="3" class="small-text">
										<p class="description"><?php esc_html_e( 'Maximale Anzahl an Korrekturversuchen (0-3)', 'ai-featured-image' ); ?></p>
									</td>
								</tr>
							</table>

							<p class="submit">
								<button type="submit" id="test-generate-btn" class="button button-primary button-large">
									<span class="dashicons dashicons-admin-generic"></span>
									<?php esc_html_e( 'Content generieren', 'ai-featured-image' ); ?>
								</button>
							</p>
						</form>

						<div id="test-progress" class="ai-progress" style="display: none;">
							<div class="ai-spinner">
								<span class="spinner is-active"></span>
							</div>
							<p id="progress-message"><?php esc_html_e( 'Generiere Content...', 'ai-featured-image' ); ?></p>
							<div class="ai-progress-bar">
								<div class="ai-progress-fill"></div>
							</div>
						</div>
					</div>

					<!-- Statistics Card -->
					<div class="ai-dashboard-card ai-stats-card">
						<h2><?php esc_html_e( 'Statistiken', 'ai-featured-image' ); ?></h2>
						<div class="ai-stats-grid">
							<div class="ai-stat-box">
								<span class="ai-stat-label"><?php esc_html_e( 'Gesamt generiert', 'ai-featured-image' ); ?></span>
								<span class="ai-stat-value" id="stat-total"><?php echo esc_html( $stats['total'] ); ?></span>
							</div>
							<div class="ai-stat-box">
								<span class="ai-stat-label"><?php esc_html_e( 'Heute', 'ai-featured-image' ); ?></span>
								<span class="ai-stat-value" id="stat-today"><?php echo esc_html( $stats['today'] ); ?></span>
							</div>
							<div class="ai-stat-box">
								<span class="ai-stat-label"><?php esc_html_e( 'Erfolgsrate', 'ai-featured-image' ); ?></span>
								<span class="ai-stat-value" id="stat-success"><?php echo esc_html( $stats['success_rate'] ); ?>%</span>
							</div>
							<div class="ai-stat-box">
								<span class="ai-stat-label"><?php esc_html_e( 'Ø Korrekturen', 'ai-featured-image' ); ?></span>
								<span class="ai-stat-value" id="stat-corrections"><?php echo esc_html( $stats['avg_corrections'] ); ?></span>
							</div>
						</div>
						<button type="button" id="refresh-stats-btn" class="button button-secondary">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Aktualisieren', 'ai-featured-image' ); ?>
						</button>
					</div>
				</div>

				<!-- Right Column: Results -->
				<div class="ai-dashboard-column">
					<div class="ai-dashboard-card ai-results-card">
						<h2><?php esc_html_e( 'Ergebnisse', 'ai-featured-image' ); ?></h2>
						
						<div id="results-container">
							<div class="ai-empty-state">
								<span class="dashicons dashicons-chart-line"></span>
								<p><?php esc_html_e( 'Starte einen Test, um Ergebnisse zu sehen.', 'ai-featured-image' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Prompt Management Section -->
			<div class="ai-dashboard-column" style="grid-column: 1 / -1;">
				<div class="ai-dashboard-card">
					<h2><?php esc_html_e( 'Prompt-Verwaltung', 'ai-featured-image' ); ?></h2>
					<?php $this->render_prompt_management(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render prompt management section.
	 */
	private function render_prompt_management() {
		// Check for missing prompts
		$loader = new AI_Featured_Image_Prompt_Loader();
		$missing = $loader->check_required_prompts();
		$all_prompts = $loader->get_all_active_prompts();

		if ( ! empty( $missing ) ) {
			?>
			<div class="notice notice-error" style="margin: 0 0 20px;">
				<p>
					<strong><?php esc_html_e( 'Achtung:', 'ai-featured-image' ); ?></strong>
					<?php esc_html_e( 'Folgende erforderliche Prompts fehlen:', 'ai-featured-image' ); ?>
				</p>
				<ul style="margin-left: 20px;">
					<?php foreach ( $missing as $slug ) : ?>
						<li><code><?php echo esc_html( $slug ); ?></code></li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ai_prompt' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Prompts verwalten', 'ai-featured-image' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Prompt', 'ai-featured-image' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'ai-featured-image' ); ?></th>
					<th><?php esc_html_e( 'Typ', 'ai-featured-image' ); ?></th>
					<th><?php esc_html_e( 'Modell', 'ai-featured-image' ); ?></th>
					<th><?php esc_html_e( 'Aktionen', 'ai-featured-image' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $all_prompts ) ) : ?>
					<tr>
						<td colspan="5" style="text-align: center; padding: 40px;">
							<?php esc_html_e( 'Keine aktiven Prompts gefunden.', 'ai-featured-image' ); ?>
							<br><br>
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ai_prompt' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Ersten Prompt erstellen', 'ai-featured-image' ); ?>
							</a>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $all_prompts as $prompt ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $prompt['title'] ); ?></strong></td>
							<td><code><?php echo esc_html( $prompt['slug'] ); ?></code></td>
							<td><?php echo esc_html( $prompt['type'] ); ?></td>
							<td><?php echo esc_html( $prompt['model'] ); ?></td>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $prompt['id'] ) ); ?>" class="button button-small">
									<?php esc_html_e( 'Bearbeiten', 'ai-featured-image' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p style="margin-top: 20px;">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ai_prompt' ) ); ?>" class="button">
				<?php esc_html_e( 'Alle Prompts verwalten', 'ai-featured-image' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ai_prompt' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Neuen Prompt erstellen', 'ai-featured-image' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Get generation statistics from logs.
	 *
	 * @return array Statistics data.
	 */
	private function get_generation_stats() {
		$upload = wp_upload_dir();
		$log_file = trailingslashit( $upload['basedir'] ) . 'ai-featured-image.log';

		$stats = array(
			'total' => 0,
			'today' => 0,
			'success_rate' => 0,
			'avg_corrections' => 0,
		);

		if ( ! file_exists( $log_file ) ) {
			return $stats;
		}

		$lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! $lines ) {
			return $stats;
		}

		$today = gmdate( 'Y-m-d' );
		$completions = 0;
		$total_corrections = 0;
		$success_count = 0;

		foreach ( $lines as $line ) {
			$entry = json_decode( $line, true );
			if ( ! $entry || ! isset( $entry['message'] ) ) {
				continue;
			}

			// Count completions
			if ( $entry['message'] === 'rest_api_post_complete' || $entry['message'] === 'ai_post_response' ) {
				$completions++;
				
				// Count today's generations
				if ( isset( $entry['ts'] ) && strpos( $entry['ts'], $today ) === 0 ) {
					$stats['today']++;
				}

				// Count corrections
				if ( isset( $entry['context']['corrections_made'] ) ) {
					$total_corrections += intval( $entry['context']['corrections_made'] );
				}

				// Count successful validations
				if ( isset( $entry['context']['valid'] ) && $entry['context']['valid'] ) {
					$success_count++;
				}
			}
		}

		$stats['total'] = $completions;
		$stats['success_rate'] = $completions > 0 ? round( ( $success_count / $completions ) * 100 ) : 0;
		$stats['avg_corrections'] = $completions > 0 ? round( $total_corrections / $completions, 1 ) : 0;

		return $stats;
	}

	/**
	 * AJAX handler for testing generation.
	 */
	public function ajax_test_generation() {
		check_ajax_referer( 'ai_dashboard_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'ai-featured-image' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$length = isset( $_POST['length'] ) ? sanitize_text_field( $_POST['length'] ) : 'medium';
		$auto_correct = isset( $_POST['auto_correct'] ) && $_POST['auto_correct'] === 'true';
		$max_corrections = isset( $_POST['max_corrections'] ) ? intval( $_POST['max_corrections'] ) : 2;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Post-ID erforderlich.', 'ai-featured-image' ) ) );
		}

		// Call the REST API internally
		$request = new WP_REST_Request( 'POST', '/ai-featured-image/v1/generate-post' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'length', $length );
		$request->set_param( 'auto_correct', $auto_correct );
		$request->set_param( 'max_corrections', $max_corrections );

		$response = rest_do_request( $request );
		
		if ( $response->is_error() ) {
			wp_send_json_error( array( 
				'message' => $response->as_error()->get_error_message() 
			) );
		}

		wp_send_json_success( $response->get_data() );
	}

	/**
	 * AJAX handler for getting updated statistics.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'ai_dashboard_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'ai-featured-image' ) ) );
		}

		$stats = $this->get_generation_stats();
		wp_send_json_success( $stats );
	}

	/**
	 * AJAX handler for creating a test post.
	 */
	public function ajax_create_test_post() {
		check_ajax_referer( 'ai_dashboard_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'ai-featured-image' ) ) );
		}

		$titles = array(
			'Künstliche Intelligenz revolutioniert die Arbeitswelt',
			'Die Zukunft des maschinellen Lernens',
			'Blockchain-Technologie: Chancen und Risiken',
			'Cloud Computing für Unternehmen',
			'Cybersecurity in der digitalen Welt',
			'Internet der Dinge: Smart Home Lösungen',
			'Quantencomputer: Die nächste Revolution',
			'5G-Netzwerke und ihre Auswirkungen',
			'Virtuelle Realität im Bildungsbereich',
			'Big Data Analytics für bessere Entscheidungen',
		);

		$random_title = $titles[ array_rand( $titles ) ] . ' - Test ' . gmdate( 'Y-m-d H:i:s' );

		$post_id = wp_insert_post( array(
			'post_title' => $random_title,
			'post_status' => 'draft',
			'post_type' => 'post',
			'post_content' => '',
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 
				'message' => $post_id->get_error_message() 
			) );
		}

		wp_send_json_success( array(
			'post_id' => $post_id,
			'post_title' => $random_title,
			'edit_link' => get_edit_post_link( $post_id, 'raw' ),
		) );
	}

	/**
	 * Add debug meta box to post editor.
	 */
	public function add_debug_meta_box() {
		add_meta_box(
			'ai_debug_info',
			__( 'AI Debug-Informationen', 'ai-featured-image' ),
			array( $this, 'render_debug_meta_box' ),
			'post',
			'normal',
			'low'
		);
	}

	/**
	 * Render debug meta box content.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_debug_meta_box( $post ) {
		$debug_log = get_post_meta( $post->ID, '_ai_debug_log', true );
		$summary = get_post_meta( $post->ID, '_ai_generation_summary', true );
		
		if ( ! $debug_log && ! $summary ) {
			echo '<p>' . esc_html__( 'Keine Debug-Informationen verfügbar. Dieser Post wurde nicht über AI generiert oder wurde vor dem Debug-Feature erstellt.', 'ai-featured-image' ) . '</p>';
			return;
		}

		// Display summary if available
		if ( $summary ) {
			echo '<div class="ai-debug-summary" style="margin-bottom: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">';
			echo '<h4 style="margin-top: 0;">' . esc_html__( 'Generierungs-Zusammenfassung', 'ai-featured-image' ) . '</h4>';
			echo '<table class="widefat"><tbody>';
			
			if ( isset( $summary['timestamp'] ) ) {
				echo '<tr><th style="width: 150px;">' . esc_html__( 'Zeitstempel', 'ai-featured-image' ) . ':</th><td>' . esc_html( $summary['timestamp'] ) . '</td></tr>';
			}
			if ( isset( $summary['model'] ) ) {
				echo '<tr><th>' . esc_html__( 'Modell', 'ai-featured-image' ) . ':</th><td><code>' . esc_html( $summary['model'] ) . '</code></td></tr>';
			}
			if ( isset( $summary['length'] ) ) {
				echo '<tr><th>' . esc_html__( 'Länge', 'ai-featured-image' ) . ':</th><td>' . esc_html( $summary['length'] ) . '</td></tr>';
			}
			if ( isset( $summary['target_range'] ) ) {
				echo '<tr><th>' . esc_html__( 'Zielbereich', 'ai-featured-image' ) . ':</th><td>' . esc_html( $summary['target_range'] ) . ' Wörter</td></tr>';
			}
			if ( isset( $summary['word_count'] ) ) {
				echo '<tr><th>' . esc_html__( 'Finale Wortanzahl', 'ai-featured-image' ) . ':</th><td><strong>' . esc_html( $summary['word_count'] ) . '</strong> Wörter</td></tr>';
			}
			if ( isset( $summary['corrections'] ) ) {
				echo '<tr><th>' . esc_html__( 'Korrekturen', 'ai-featured-image' ) . ':</th><td>' . esc_html( $summary['corrections'] ) . '</td></tr>';
			}
			if ( isset( $summary['status'] ) ) {
				$status_class = $summary['status'] === 'valid' ? 'green' : 'orange';
				$status_text = $summary['status'] === 'valid' ? __( 'Gültig ✓', 'ai-featured-image' ) : __( 'Außerhalb Zielbereich', 'ai-featured-image' );
				echo '<tr><th>' . esc_html__( 'Status', 'ai-featured-image' ) . ':</th><td><span style="color: ' . esc_attr( $status_class ) . ';">' . esc_html( $status_text ) . '</span></td></tr>';
			}
			
			echo '</tbody></table>';
			echo '</div>';
		}

		// Display full debug log
		if ( $debug_log ) {
			$debug = json_decode( $debug_log, true );
			
			if ( $debug && is_array( $debug ) ) {
				echo '<div class="ai-debug-full">';
				echo '<h4>' . esc_html__( 'Vollständige Debug-Informationen', 'ai-featured-image' ) . '</h4>';
				echo '<p><em>' . esc_html__( 'Klicken Sie auf die Sections um Details anzuzeigen.', 'ai-featured-image' ) . '</em></p>';
				
				// Initial Generation
				if ( isset( $debug['initial_generation'] ) ) {
					$initial = $debug['initial_generation'];
					echo '<details style="margin-bottom: 15px; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
					echo '<summary style="cursor: pointer; font-weight: bold; padding: 5px;">' . esc_html__( '📤 Initiale Generierung', 'ai-featured-image' ) . '</summary>';
					echo '<div style="margin-top: 10px;">';
					
					// Request
					if ( isset( $initial['request'] ) ) {
						echo '<h5>' . esc_html__( 'Request:', 'ai-featured-image' ) . '</h5>';
						echo '<table class="widefat"><tbody>';
						echo '<tr><th style="width: 150px;">' . esc_html__( 'Modell', 'ai-featured-image' ) . ':</th><td><code>' . esc_html( $initial['request']['model'] ?? 'N/A' ) . '</code></td></tr>';
						echo '<tr><th>' . esc_html__( 'Temperature', 'ai-featured-image' ) . ':</th><td>' . esc_html( $initial['request']['temperature'] ?? 'N/A' ) . '</td></tr>';
						echo '<tr><th>' . esc_html__( 'Max Tokens', 'ai-featured-image' ) . ':</th><td>' . esc_html( $initial['request']['max_tokens'] ?? 'N/A' ) . '</td></tr>';
						echo '<tr><th>' . esc_html__( 'Response Format', 'ai-featured-image' ) . ':</th><td>' . esc_html( $initial['request']['response_format'] ?? 'N/A' ) . '</td></tr>';
						
						if ( isset( $initial['request']['system_prompt_edit_link'] ) && $initial['request']['system_prompt_edit_link'] ) {
							echo '<tr><th>' . esc_html__( 'System Prompt', 'ai-featured-image' ) . ':</th><td><a href="' . esc_url( $initial['request']['system_prompt_edit_link'] ) . '" target="_blank">✏️ Prompt bearbeiten</a></td></tr>';
						}
						
						if ( isset( $initial['request']['user_prompt_edit_link'] ) && $initial['request']['user_prompt_edit_link'] ) {
							echo '<tr><th>' . esc_html__( 'User Prompt', 'ai-featured-image' ) . ':</th><td><a href="' . esc_url( $initial['request']['user_prompt_edit_link'] ) . '" target="_blank">✏️ Prompt bearbeiten</a> (Variant: ' . esc_html( $initial['request']['user_prompt_variant'] ?? 'N/A' ) . ')</td></tr>';
						}
						
						echo '</tbody></table>';
						
						// Full prompts in expandable sections
						if ( isset( $initial['request']['system_prompt_full'] ) ) {
							echo '<details style="margin-top: 10px;"><summary style="cursor: pointer; font-weight: bold;">System Prompt (vollständig)</summary>';
							echo '<pre style="white-space: pre-wrap; background: #f0f0f0; padding: 10px; border-radius: 4px; max-height: 300px; overflow-y: auto;">' . esc_html( $initial['request']['system_prompt_full'] ) . '</pre></details>';
						}
						
						if ( isset( $initial['request']['user_prompt_full'] ) ) {
							echo '<details style="margin-top: 10px;"><summary style="cursor: pointer; font-weight: bold;">User Prompt (vollständig)</summary>';
							echo '<pre style="white-space: pre-wrap; background: #f0f0f0; padding: 10px; border-radius: 4px; max-height: 300px; overflow-y: auto;">' . esc_html( $initial['request']['user_prompt_full'] ) . '</pre></details>';
						}
					}
					
					// Response
					if ( isset( $initial['response'] ) ) {
						echo '<h5 style="margin-top: 15px;">' . esc_html__( 'Response:', 'ai-featured-image' ) . '</h5>';
						echo '<table class="widefat"><tbody>';
						echo '<tr><th style="width: 150px;">' . esc_html__( 'Verwendetes Modell', 'ai-featured-image' ) . ':</th><td><code>' . esc_html( $initial['response']['model'] ?? 'N/A' ) . '</code></td></tr>';
						
						if ( isset( $initial['response']['usage'] ) && is_array( $initial['response']['usage'] ) ) {
							echo '<tr><th>' . esc_html__( 'Token Usage', 'ai-featured-image' ) . ':</th><td>';
							echo 'Input: ' . esc_html( $initial['response']['usage']['prompt_tokens'] ?? 0 ) . ', ';
							echo 'Output: ' . esc_html( $initial['response']['usage']['completion_tokens'] ?? 0 ) . ', ';
							echo 'Total: <strong>' . esc_html( $initial['response']['usage']['total_tokens'] ?? 0 ) . '</strong>';
							echo '</td></tr>';
						}
						
						echo '</tbody></table>';
					}
					
					echo '</div></details>';
				}
				
				// Corrections
				if ( isset( $debug['corrections'] ) && is_array( $debug['corrections'] ) && count( $debug['corrections'] ) > 0 ) {
					echo '<details style="margin-bottom: 15px; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
					echo '<summary style="cursor: pointer; font-weight: bold; padding: 5px;">' . esc_html__( '🔄 Korrekturen', 'ai-featured-image' ) . ' (' . count( $debug['corrections'] ) . ')</summary>';
					echo '<div style="margin-top: 10px;">';
					
					foreach ( $debug['corrections'] as $idx => $corr ) {
						$dir_icon = $corr['direction'] === 'expand' ? '↑ Erweitern' : '↓ Kürzen';
						echo '<h5>Korrektur ' . ( $idx + 1 ) . ': ' . esc_html( $dir_icon ) . '</h5>';
						echo '<table class="widefat"><tbody>';
						echo '<tr><th style="width: 150px;">' . esc_html__( 'Modell', 'ai-featured-image' ) . ':</th><td><code>' . esc_html( $corr['request']['model'] ?? 'N/A' ) . '</code></td></tr>';
						echo '<tr><th>' . esc_html__( 'Aktuelle Wörter', 'ai-featured-image' ) . ':</th><td>' . esc_html( $corr['request']['current_words'] ?? 'N/A' ) . '</td></tr>';
						echo '<tr><th>' . esc_html__( 'Neue Wörter', 'ai-featured-image' ) . ':</th><td>' . esc_html( $corr['response']['new_word_count'] ?? 'N/A' ) . '</td></tr>';
						
						if ( isset( $corr['request']['user_prompt_edit_link'] ) && $corr['request']['user_prompt_edit_link'] ) {
							echo '<tr><th>' . esc_html__( 'Prompt', 'ai-featured-image' ) . ':</th><td><a href="' . esc_url( $corr['request']['user_prompt_edit_link'] ) . '" target="_blank">✏️ ' . esc_html( $corr['request']['user_prompt_slug'] ?? 'Bearbeiten' ) . '</a></td></tr>';
						}
						
						echo '</tbody></table>';
					}
					
					echo '</div></details>';
				}
				
				// Raw JSON export
				echo '<details style="margin-top: 15px;"><summary style="cursor: pointer; font-weight: bold;">🔧 Raw JSON Export</summary>';
				echo '<textarea readonly style="width: 100%; height: 300px; font-family: monospace; margin-top: 10px;">' . esc_textarea( $debug_log ) . '</textarea>';
				echo '<p><button type="button" class="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert(\'JSON in Zwischenablage kopiert!\')">In Zwischenablage kopieren</button></p>';
				echo '</details>';
				
				echo '</div>';
			}
		}
	}
}

