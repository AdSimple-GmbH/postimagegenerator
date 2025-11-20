<?php
/**
 * Custom Post Type for AI Prompt Management.
 *
 * @package AI_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Featured_Image_Prompt_CPT
 */
class AI_Featured_Image_Prompt_CPT {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_ai_prompt', array( $this, 'save_meta_boxes' ) );
		// Auto-invalidate prompt cache on changes
		add_action( 'save_post_ai_prompt', array( $this, 'clear_prompt_cache' ), 10, 1 );
		add_action( 'delete_post', array( $this, 'clear_prompt_cache' ), 10, 1 );
		add_filter( 'manage_ai_prompt_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_ai_prompt_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'add_admin_filters' ) );
		add_filter( 'parse_query', array( $this, 'filter_by_meta' ) );
		add_action( 'wp_ajax_test_ai_prompt', array( $this, 'ajax_test_prompt' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_validation_notices' ) );
	}

	/**
	 * Clear prompt cache when a prompt is saved or deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_prompt_cache( $post_id ) {
		if ( get_post_type( $post_id ) !== 'ai_prompt' ) {
			return;
		}
		$loader = new AI_Featured_Image_Prompt_Loader();
		$loader->clear_cache();
	}

	/**
	 * Register Custom Post Type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'AI Prompts', 'ai-featured-image' ),
			'singular_name'         => __( 'AI Prompt', 'ai-featured-image' ),
			'menu_name'             => __( 'AI Prompts', 'ai-featured-image' ),
			'add_new'               => __( 'Hinzufügen', 'ai-featured-image' ),
			'add_new_item'          => __( 'Neuer AI Prompt', 'ai-featured-image' ),
			'edit_item'             => __( 'Prompt bearbeiten', 'ai-featured-image' ),
			'new_item'              => __( 'Neuer Prompt', 'ai-featured-image' ),
			'view_item'             => __( 'Prompt ansehen', 'ai-featured-image' ),
			'search_items'          => __( 'Prompts durchsuchen', 'ai-featured-image' ),
			'not_found'             => __( 'Keine Prompts gefunden', 'ai-featured-image' ),
			'not_found_in_trash'    => __( 'Keine Prompts im Papierkorb', 'ai-featured-image' ),
			'all_items'             => __( 'Alle Prompts', 'ai-featured-image' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 31,
			'menu_icon'           => 'dashicons-admin-post',
			'capability_type'     => 'post',
			'capabilities'        => array(
				'edit_post'          => 'edit_posts',
				'edit_posts'         => 'edit_posts',
				'edit_others_posts'  => 'edit_posts',
				'publish_posts'      => 'edit_posts',
				'read_post'          => 'edit_posts',
				'read_private_posts' => 'edit_posts',
				'delete_post'        => 'edit_posts',
			),
			'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields' ),
			'has_archive'         => false,
			'hierarchical'        => false,
			'rewrite'             => false,
			'show_in_rest'        => false,
		);

		register_post_type( 'ai_prompt', $args );
	}

	/**
	 * Register Custom Taxonomy.
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => __( 'Prompt-Typen', 'ai-featured-image' ),
			'singular_name'     => __( 'Prompt-Typ', 'ai-featured-image' ),
			'search_items'      => __( 'Typen durchsuchen', 'ai-featured-image' ),
			'all_items'         => __( 'Alle Typen', 'ai-featured-image' ),
			'edit_item'         => __( 'Typ bearbeiten', 'ai-featured-image' ),
			'update_item'       => __( 'Typ aktualisieren', 'ai-featured-image' ),
			'add_new_item'      => __( 'Neuer Typ', 'ai-featured-image' ),
			'new_item_name'     => __( 'Neuer Typ-Name', 'ai-featured-image' ),
			'menu_name'         => __( 'Prompt-Typen', 'ai-featured-image' ),
		);

		register_taxonomy( 'prompt_type', array( 'ai_prompt' ), array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => false,
		) );

		// Register default terms
		if ( ! term_exists( 'generation', 'prompt_type' ) ) {
			wp_insert_term( 'Generation', 'prompt_type', array( 'slug' => 'generation' ) );
			wp_insert_term( 'Correction', 'prompt_type', array( 'slug' => 'correction' ) );
			wp_insert_term( 'System', 'prompt_type', array( 'slug' => 'system' ) );
			wp_insert_term( 'Image', 'prompt_type', array( 'slug' => 'image' ) );
		}
	}

	/**
	 * Add Meta Boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'ai_prompt_config',
			__( 'Prompt-Konfiguration', 'ai-featured-image' ),
			array( $this, 'render_config_meta_box' ),
			'ai_prompt',
			'normal',
			'high'
		);

		add_meta_box(
			'ai_prompt_gpt_params',
			__( 'GPT-Parameter', 'ai-featured-image' ),
			array( $this, 'render_gpt_params_meta_box' ),
			'ai_prompt',
			'side',
			'default'
		);

		add_meta_box(
			'ai_prompt_test',
			__( 'Prompt testen', 'ai-featured-image' ),
			array( $this, 'render_test_meta_box' ),
			'ai_prompt',
			'side',
			'default'
		);

		add_meta_box(
			'ai_prompt_status',
			__( 'Status', 'ai-featured-image' ),
			array( $this, 'render_status_meta_box' ),
			'ai_prompt',
			'side',
			'default'
		);
	}

	/**
	 * Render Config Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_config_meta_box( $post ) {
		wp_nonce_field( 'ai_prompt_meta_box', 'ai_prompt_meta_box_nonce' );

		$prompt_slug = get_post_meta( $post->ID, '_prompt_slug', true );
		$prompt_type = get_post_meta( $post->ID, '_prompt_type', true );
		$prompt_variants = get_post_meta( $post->ID, '_prompt_variants', true );

		// Convert array to JSON string for display in textarea
		if ( is_array( $prompt_variants ) ) {
			$prompt_variants = wp_json_encode( $prompt_variants, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		}

		?>
		<table class="form-table">
			<tr>
				<th><label for="prompt_slug"><?php esc_html_e( 'Prompt-Slug', 'ai-featured-image' ); ?></label></th>
				<td>
					<input type="text" id="prompt_slug" name="prompt_slug" value="<?php echo esc_attr( $prompt_slug ); ?>" class="regular-text" required>
					<p class="description"><?php esc_html_e( 'Eindeutige Kennung (z.B. "post-generation", "image-generation")', 'ai-featured-image' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="prompt_type"><?php esc_html_e( 'Prompt-Typ', 'ai-featured-image' ); ?></label></th>
				<td>
					<select id="prompt_type" name="prompt_type" class="regular-text">
						<option value="generation" <?php selected( $prompt_type, 'generation' ); ?>><?php esc_html_e( 'Generation', 'ai-featured-image' ); ?></option>
						<option value="correction_expand" <?php selected( $prompt_type, 'correction_expand' ); ?>><?php esc_html_e( 'Korrektur: Erweitern', 'ai-featured-image' ); ?></option>
						<option value="correction_shorten" <?php selected( $prompt_type, 'correction_shorten' ); ?>><?php esc_html_e( 'Korrektur: Kürzen', 'ai-featured-image' ); ?></option>
						<option value="system_generation" <?php selected( $prompt_type, 'system_generation' ); ?>><?php esc_html_e( 'System: Generation', 'ai-featured-image' ); ?></option>
						<option value="system_correction" <?php selected( $prompt_type, 'system_correction' ); ?>><?php esc_html_e( 'System: Korrektur', 'ai-featured-image' ); ?></option>
						<option value="image" <?php selected( $prompt_type, 'image' ); ?>><?php esc_html_e( 'Bild-Generierung', 'ai-featured-image' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="prompt_variants"><?php esc_html_e( 'Varianten (JSON)', 'ai-featured-image' ); ?></label></th>
				<td>
					<textarea id="prompt_variants" name="prompt_variants" rows="10" class="large-text code"><?php echo esc_textarea( $prompt_variants ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'JSON-Format für Varianten, z.B.: {"short": "Text...", "medium": "Text...", "long": "Text..."}', 'ai-featured-image' ); ?><br>
						<?php esc_html_e( 'Variablen: {post_title}, {post_excerpt}, {post_content}, {min_words}, {max_words}, {current_words}, {length}', 'ai-featured-image' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render GPT Parameters Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_gpt_params_meta_box( $post ) {
		$gpt_model = get_post_meta( $post->ID, '_gpt_model', true );
		$gpt_temperature = get_post_meta( $post->ID, '_gpt_temperature', true );
		$gpt_max_tokens = get_post_meta( $post->ID, '_gpt_max_tokens', true );
		$gpt_response_format = get_post_meta( $post->ID, '_gpt_response_format', true );
		$prompt_type = get_post_meta( $post->ID, '_prompt_type', true );

		// Set sensible defaults
		if ( empty( $gpt_model ) ) {
			$gpt_model = 'gpt-5-mini';
		}
		if ( $gpt_temperature === '' ) {
			$gpt_temperature = '0.2';
		}
		if ( empty( $gpt_response_format ) ) {
			$gpt_response_format = 'text';
		}
		
		// Set default max_tokens based on prompt type
		if ( empty( $gpt_max_tokens ) || $gpt_max_tokens == 0 ) {
			$default_tokens = array(
				'generation' => 5000,
				'correction_expand' => 6000,
				'correction_shorten' => 6000,
				'system_generation' => 100,
				'system_correction' => 100,
				'image' => 1000,
			);
			$gpt_max_tokens = isset( $default_tokens[ $prompt_type ] ) ? $default_tokens[ $prompt_type ] : 4000;
		}

		// Calculate estimated cost
		$cost_per_1m = array(
			'gpt-5.1' => 1.25,
			'gpt-5-mini' => 0.25,
			'gpt-5-nano' => 0.05,
			'gpt-image-1' => 0.00,
		);
		$estimated_cost = isset( $cost_per_1m[ $gpt_model ] ) ? $cost_per_1m[ $gpt_model ] : 0;

		?>
		<table class="form-table">
			<tr>
				<th><label for="gpt_model"><?php esc_html_e( 'Modell', 'ai-featured-image' ); ?></label></th>
				<td>
					<select id="gpt_model" name="gpt_model" class="widefat">
						<option value="gpt-5.1" <?php selected( $gpt_model, 'gpt-5.1' ); ?>>GPT-5.1 ($1.25)</option>
						<option value="gpt-5-mini" <?php selected( $gpt_model, 'gpt-5-mini' ); ?>>GPT-5 mini ($0.25)</option>
						<option value="gpt-5-nano" <?php selected( $gpt_model, 'gpt-5-nano' ); ?>>GPT-5 nano ($0.05)</option>
						<option value="gpt-image-1" <?php selected( $gpt_model, 'gpt-image-1' ); ?>>GPT Image-1</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="gpt_temperature"><?php esc_html_e( 'Temperature', 'ai-featured-image' ); ?></label></th>
				<td>
					<input type="number" id="gpt_temperature" name="gpt_temperature" value="<?php echo esc_attr( $gpt_temperature ); ?>" min="0" max="2" step="0.1" class="regular-text">
					<p class="description">
						<?php esc_html_e( '0.0-0.3: Deterministisch (Content-Generierung)', 'ai-featured-image' ); ?><br>
						<?php esc_html_e( '0.4-0.7: Ausgewogen (Kreative Texte)', 'ai-featured-image' ); ?><br>
						<?php esc_html_e( '0.8-2.0: Sehr kreativ (Bilder, Varianz)', 'ai-featured-image' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="gpt_max_tokens"><?php esc_html_e( 'Max Tokens', 'ai-featured-image' ); ?></label></th>
				<td>
					<input type="number" id="gpt_max_tokens" name="gpt_max_tokens" value="<?php echo esc_attr( $gpt_max_tokens ); ?>" min="1" max="100000" step="1" class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Empfohlung:', 'ai-featured-image' ); ?><br>
						• <?php esc_html_e( 'Kurze Texte (short): 2000-3000', 'ai-featured-image' ); ?><br>
						• <?php esc_html_e( 'Mittlere Texte (medium): 4000-5000', 'ai-featured-image' ); ?><br>
						• <?php esc_html_e( 'Lange Texte (long/verylong): 6000-8000', 'ai-featured-image' ); ?><br>
						• <?php esc_html_e( 'Korrektur: 6000', 'ai-featured-image' ); ?><br>
						• <?php esc_html_e( 'System-Prompts: 100-500', 'ai-featured-image' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="gpt_response_format"><?php esc_html_e( 'Response Format', 'ai-featured-image' ); ?></label></th>
				<td>
					<select id="gpt_response_format" name="gpt_response_format" class="widefat">
						<option value="text" <?php selected( $gpt_response_format, 'text' ); ?>><?php esc_html_e( 'Text', 'ai-featured-image' ); ?></option>
						<option value="json_object" <?php selected( $gpt_response_format, 'json_object' ); ?>><?php esc_html_e( 'JSON Object', 'ai-featured-image' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Geschätzte Kosten', 'ai-featured-image' ); ?></th>
				<td>
					<strong id="estimated_cost">$<?php echo esc_html( number_format( $estimated_cost, 2 ) ); ?></strong> / 1M tokens
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Test Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_test_meta_box( $post ) {
		$last_tested = get_post_meta( $post->ID, '_last_tested', true );
		$test_result = get_post_meta( $post->ID, '_test_result', true );

		?>
		<div class="ai-prompt-test-section">
			<p>
				<button type="button" id="test-prompt-btn" class="button button-primary button-large" style="width: 100%;">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Prompt testen', 'ai-featured-image' ); ?>
				</button>
			</p>

			<?php if ( $last_tested ) : ?>
				<p class="description">
					<?php esc_html_e( 'Letzter Test:', 'ai-featured-image' ); ?>
					<strong><?php echo esc_html( gmdate( 'd.m.Y H:i', strtotime( $last_tested ) ) ); ?></strong>
				</p>
			<?php endif; ?>

			<?php if ( $test_result ) : ?>
				<div class="ai-test-result">
					<h4><?php esc_html_e( 'Letztes Ergebnis:', 'ai-featured-image' ); ?></h4>
					<pre><?php echo esc_html( $test_result ); ?></pre>
				</div>
			<?php endif; ?>
		</div>

		<div id="test-prompt-modal" style="display: none;">
			<div class="test-modal-content ai-prompt-test-box">
				<h3><?php esc_html_e( 'Prompt testen', 'ai-featured-image' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><label for="test_post_id"><?php esc_html_e( 'Test-Post ID', 'ai-featured-image' ); ?></label></th>
						<td>
							<p class="description"><?php esc_html_e( 'Gib eine Post-ID an, um Variablen zu ersetzen (z.B. {post_title}).', 'ai-featured-image' ); ?></p>
							<input type="number" id="test_post_id" class="regular-text" placeholder="<?php esc_attr_e( 'Optional', 'ai-featured-image' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="test_variant"><?php esc_html_e( 'Variante', 'ai-featured-image' ); ?></label></th>
						<td>
							<p class="description"><?php esc_html_e( 'Nur für Prompts mit Varianten (z.B. short, medium, long).', 'ai-featured-image' ); ?></p>
							<input type="text" id="test_variant" class="regular-text" placeholder="z.B. short, medium">
						</td>
					</tr>
				</table>
				<div id="test-progress" style="display: none;">
					<p><span class="spinner is-active"></span> <?php esc_html_e( 'Teste Prompt...', 'ai-featured-image' ); ?></p>
				</div>
				<div id="test-result-display" class="ai-prompt-test-result"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Status Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_status_meta_box( $post ) {
		$is_active = get_post_meta( $post->ID, '_is_active', true );
		if ( $is_active === '' ) {
			$is_active = '1';
		}

		?>
		<p>
			<label>
				<input type="checkbox" name="is_active" value="1" <?php checked( $is_active, '1' ); ?>>
				<?php esc_html_e( 'Prompt ist aktiv', 'ai-featured-image' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Nur aktive Prompts werden vom System verwendet.', 'ai-featured-image' ); ?>
		</p>
		<?php
	}

	/**
	 * Save Meta Boxes.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta_boxes( $post_id ) {
		if ( ! isset( $_POST['ai_prompt_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['ai_prompt_meta_box_nonce'], 'ai_prompt_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save Config fields
		if ( isset( $_POST['prompt_slug'] ) ) {
			update_post_meta( $post_id, '_prompt_slug', sanitize_text_field( $_POST['prompt_slug'] ) );
		}

		if ( isset( $_POST['prompt_type'] ) ) {
			$prompt_type = sanitize_text_field( $_POST['prompt_type'] );
			update_post_meta( $post_id, '_prompt_type', $prompt_type );
			
			// Synchronize with taxonomy
			$this->sync_prompt_type_taxonomy( $post_id, $prompt_type );
		}
		
		// Validate prompt before saving
		$post_content = isset( $_POST['content'] ) ? $_POST['content'] : get_post_field( 'post_content', $post_id );
		$variants_for_validation = null;
		if ( isset( $_POST['prompt_variants'] ) && ! empty( $_POST['prompt_variants'] ) ) {
			$variants_input = wp_kses_post( $_POST['prompt_variants'] );
			$variants_array = json_decode( $variants_input, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $variants_array ) ) {
				$variants_for_validation = $variants_array;
			}
		}
		
		$validation_errors = $this->validate_prompt( 
			isset( $prompt_type ) ? $prompt_type : get_post_meta( $post_id, '_prompt_type', true ), 
			$post_content, 
			$variants_for_validation 
		);
		
		if ( ! empty( $validation_errors ) ) {
			set_transient( 'ai_prompt_validation_errors_' . $post_id, $validation_errors, 45 );
			// Don't block saving, just show warning
		}

		if ( isset( $_POST['prompt_variants'] ) ) {
			$variants_input = wp_kses_post( $_POST['prompt_variants'] );
			
			// Try to decode JSON to array for better storage
			if ( ! empty( $variants_input ) ) {
				$variants_array = json_decode( $variants_input, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $variants_array ) ) {
					// Store as array (WordPress will serialize automatically)
					update_post_meta( $post_id, '_prompt_variants', $variants_array );
				} else {
					// Store as string if not valid JSON
					update_post_meta( $post_id, '_prompt_variants', $variants_input );
				}
			} else {
				// Delete if empty
				delete_post_meta( $post_id, '_prompt_variants' );
			}
		}

		// Save GPT Parameters
		if ( isset( $_POST['gpt_model'] ) ) {
			update_post_meta( $post_id, '_gpt_model', sanitize_text_field( $_POST['gpt_model'] ) );
		}

		if ( isset( $_POST['gpt_temperature'] ) ) {
			update_post_meta( $post_id, '_gpt_temperature', floatval( $_POST['gpt_temperature'] ) );
		}

		if ( isset( $_POST['gpt_max_tokens'] ) ) {
			update_post_meta( $post_id, '_gpt_max_tokens', intval( $_POST['gpt_max_tokens'] ) );
		}

		if ( isset( $_POST['gpt_response_format'] ) ) {
			update_post_meta( $post_id, '_gpt_response_format', sanitize_text_field( $_POST['gpt_response_format'] ) );
		}

		// Save Status
		$is_active = isset( $_POST['is_active'] ) ? '1' : '0';
		update_post_meta( $post_id, '_is_active', $is_active );

		// Clear cache
		wp_cache_delete( 'ai_prompt_' . get_post_meta( $post_id, '_prompt_slug', true ), 'ai_prompts' );
	}

	/**
	 * Synchronize prompt type meta field with taxonomy.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type Prompt type value.
	 */
	private function sync_prompt_type_taxonomy( $post_id, $type ) {
		// Map meta field values to taxonomy terms
		$type_mapping = array(
			'generation' => 'Generation',
			'correction_expand' => 'Correction',
			'correction_shorten' => 'Correction',
			'system_generation' => 'System',
			'system_correction' => 'System',
			'image' => 'Image',
		);

		if ( isset( $type_mapping[ $type ] ) ) {
			$term_name = $type_mapping[ $type ];
			
			// Get or create the term
			$term = term_exists( $term_name, 'prompt_type' );
			if ( ! $term ) {
				$term = wp_insert_term( $term_name, 'prompt_type', array( 'slug' => strtolower( $term_name ) ) );
			}

			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? $term['term_id'] : $term;
				wp_set_post_terms( $post_id, array( $term_id ), 'prompt_type', false );
			}
		}
	}

	/**
	 * Add custom columns to post list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_custom_columns( $columns ) {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];
		$new_columns['prompt_slug'] = __( 'Slug', 'ai-featured-image' );
		$new_columns['prompt_type'] = __( 'Typ', 'ai-featured-image' );
		$new_columns['gpt_model'] = __( 'Modell', 'ai-featured-image' );
		$new_columns['status'] = __( 'Status', 'ai-featured-image' );
		$new_columns['last_tested'] = __( 'Letzter Test', 'ai-featured-image' );
		$new_columns['date'] = $columns['date'];

		return $new_columns;
	}

	/**
	 * Render custom columns.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'prompt_slug':
				$slug = get_post_meta( $post_id, '_prompt_slug', true );
				echo esc_html( $slug ? $slug : '-' );
				break;

			case 'prompt_type':
				$type = get_post_meta( $post_id, '_prompt_type', true );
				$type_labels = array(
					'generation' => __( 'Generation', 'ai-featured-image' ),
					'correction_expand' => __( 'Korrektur: Erweitern', 'ai-featured-image' ),
					'correction_shorten' => __( 'Korrektur: Kürzen', 'ai-featured-image' ),
					'system_generation' => __( 'System: Generation', 'ai-featured-image' ),
					'system_correction' => __( 'System: Korrektur', 'ai-featured-image' ),
					'image' => __( 'Bild', 'ai-featured-image' ),
				);
				echo esc_html( isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type );
				break;

			case 'gpt_model':
				$model = get_post_meta( $post_id, '_gpt_model', true );
				echo esc_html( $model ? $model : '-' );
				break;

			case 'status':
				$is_active = get_post_meta( $post_id, '_is_active', true );
				if ( $is_active === '1' ) {
					echo '<span style="color: green;">✓ ' . esc_html__( 'Aktiv', 'ai-featured-image' ) . '</span>';
				} else {
					echo '<span style="color: red;">✗ ' . esc_html__( 'Inaktiv', 'ai-featured-image' ) . '</span>';
				}
				break;

			case 'last_tested':
				$last_tested = get_post_meta( $post_id, '_last_tested', true );
				if ( $last_tested ) {
					echo esc_html( gmdate( 'd.m.Y H:i', strtotime( $last_tested ) ) );
				} else {
					echo '-';
				}
				break;
		}
	}

	/**
	 * Add admin filters.
	 */
	public function add_admin_filters() {
		global $typenow;

		if ( 'ai_prompt' !== $typenow ) {
			return;
		}

		// Filter by status
		$status = isset( $_GET['prompt_status'] ) ? $_GET['prompt_status'] : '';
		?>
		<select name="prompt_status">
			<option value=""><?php esc_html_e( 'Alle Status', 'ai-featured-image' ); ?></option>
			<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Aktiv', 'ai-featured-image' ); ?></option>
			<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inaktiv', 'ai-featured-image' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Filter posts by meta.
	 *
	 * @param WP_Query $query Query object.
	 */
	public function filter_by_meta( $query ) {
		global $pagenow, $typenow;

		if ( 'edit.php' !== $pagenow || 'ai_prompt' !== $typenow || ! is_admin() ) {
			return;
		}

		if ( isset( $_GET['prompt_status'] ) && ! empty( $_GET['prompt_status'] ) ) {
			$value = $_GET['prompt_status'] === 'active' ? '1' : '0';
			$query->set( 'meta_key', '_is_active' );
			$query->set( 'meta_value', $value );
		}
	}

	/**
	 * AJAX handler to test prompt.
	 */
	public function ajax_test_prompt() {
		check_ajax_referer( 'ai_prompt_test_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'ai-featured-image' ) ) );
		}

		$prompt_id = isset( $_POST['prompt_id'] ) ? intval( $_POST['prompt_id'] ) : 0;
		$test_post_id = isset( $_POST['test_post_id'] ) ? intval( $_POST['test_post_id'] ) : 0;
		$test_variant = isset( $_POST['test_variant'] ) ? sanitize_text_field( $_POST['test_variant'] ) : '';

		if ( ! $prompt_id ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Prompt-ID.', 'ai-featured-image' ) ) );
		}

		// Get prompt content
		$prompt_post = get_post( $prompt_id );
		if ( ! $prompt_post ) {
			wp_send_json_error( array( 'message' => __( 'Prompt nicht gefunden.', 'ai-featured-image' ) ) );
		}

		$prompt_content = $prompt_post->post_content;
		$variants = get_post_meta( $prompt_id, '_prompt_variants', true );

		// If variant specified, get variant prompt
		if ( $test_variant && $variants ) {
			// Handle both array and JSON string
			$variants_array = is_array( $variants ) ? $variants : json_decode( $variants, true );
			if ( isset( $variants_array[ $test_variant ] ) ) {
				$prompt_content = $variants_array[ $test_variant ];
			}
		}

		// Get GPT parameters
		$gpt_model = get_post_meta( $prompt_id, '_gpt_model', true );
		$gpt_temperature = get_post_meta( $prompt_id, '_gpt_temperature', true );
		$gpt_max_tokens = get_post_meta( $prompt_id, '_gpt_max_tokens', true );
		$gpt_response_format = get_post_meta( $prompt_id, '_gpt_response_format', true );

		// Replace variables if test post specified
		if ( $test_post_id ) {
			$test_post = get_post( $test_post_id );
			if ( $test_post ) {
				$prompt_content = str_replace( '{post_title}', $test_post->post_title, $prompt_content );
				$prompt_content = str_replace( '{post_excerpt}', $test_post->post_excerpt, $prompt_content );
				$prompt_content = str_replace( '{post_content}', wp_strip_all_tags( $test_post->post_content ), $prompt_content );
			}
		}

		// Test API call
		$options = get_option( 'ai_featured_image_options' );
		$api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : ( ! empty( $options['api_key'] ) ? $options['api_key'] : '' );

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'OpenAI API Key nicht konfiguriert.', 'ai-featured-image' ) ) );
		}

		// Build request based on model type
		if ( $gpt_model === 'gpt-image-1' ) {
			// Image generation test
			$response = wp_remote_post( 'https://api.openai.com/v1/images/generations', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( array(
					'model' => 'gpt-image-1',
					'prompt' => $prompt_content,
					'n' => 1,
					'size' => '1024x1024',
				) ),
				'timeout' => 60,
			) );
		} else {
			// Text generation test
			$payload = array(
				'model' => $gpt_model,
				'messages' => array(
					array( 'role' => 'user', 'content' => $prompt_content ),
				),
				'temperature' => floatval( $gpt_temperature ),
			);

			if ( $gpt_max_tokens ) {
				$payload['max_completion_tokens'] = intval( $gpt_max_tokens );
			}

			if ( $gpt_response_format === 'json_object' ) {
				$payload['response_format'] = array( 'type' => 'json_object' );
			}

			$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $payload ),
				'timeout' => 120,
			) );
		}

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			wp_send_json_error( array( 'message' => $data['error']['message'] ) );
		}

		// Save test result
		$test_result = array(
			'success' => true,
			'timestamp' => gmdate( 'Y-m-d H:i:s' ),
			'model' => $gpt_model,
			'usage' => isset( $data['usage'] ) ? $data['usage'] : array(),
		);

		update_post_meta( $prompt_id, '_last_tested', gmdate( 'Y-m-d H:i:s' ) );
		update_post_meta( $prompt_id, '_test_result', wp_json_encode( $test_result, JSON_PRETTY_PRINT ) );

		wp_send_json_success( array(
			'message' => __( 'Test erfolgreich!', 'ai-featured-image' ),
			'data' => $data,
			'result' => $test_result,
		) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		global $post_type;

		if ( 'ai_prompt' !== $post_type ) {
			return;
		}

		wp_enqueue_style(
			'ai-prompt-admin',
			plugins_url( '../assets/css/prompt-admin.css', __FILE__ ),
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'ai-prompt-test',
			plugins_url( '../assets/js/prompt-test.js', __FILE__ ),
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'ai-prompt-test',
			'aiPromptTest',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ai_prompt_test_nonce' ),
				'postId' => isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0,
			)
		);
	}

	/**
	 * Setup default prompts on plugin activation.
	 */
	public static function setup_default_prompts() {
		// Check if defaults already exist
		$existing = get_posts( array(
			'post_type' => 'ai_prompt',
			'meta_key' => '_prompt_slug',
			'meta_value' => 'post-generation',
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing ) ) {
			return; // Already setup
		}

		// System Post Generation Prompt
		self::create_default_prompt(
			'System: Post-Generierung',
			'Du bist ein professioneller Content-Writer fuer deutsche Artikel.

BLATTLINIE: {editorial_line}
SCHREIBSTIL: {author_style}
ZIELGRUPPE: {target_audience}

Du schreibst Artikel auf Deutsch, die EXAKT die Wortzahl-Anforderungen erfuellen. Du schreibst IMMER ALLE Abschnitte vollstaendig. Du antwortest IMMER mit gueltigem JSON mit den Feldern content_html, category_name und tags.',
			'system-post-generation',
			'system_generation',
			'gpt-5-mini',
			0.2,
			null,
			'json_object'
		);

		// System Correction Prompt
		self::create_default_prompt(
			'System: Korrektur',
			'Du bist ein professioneller deutscher Content-Editor. Du passt Textlängen präzise an, ohne die Qualität zu beeinträchtigen. Du antwortest NUR mit dem angepassten HTML-Inhalt.',
			'system-correction',
			'system_correction',
			'gpt-5-mini',
			0.3,
			6000,
			'text'
		);

		// Post Generation with variants
		$post_gen_variants = array(
			'short' => 'Schreibe einen deutschen Artikel zum Thema: {post_title}

Kontext: {post_excerpt}

WORTANZAHL-ANFORDERUNG:
- Zielbereich: {min_words} bis {max_words} Woerter
- NICHT weniger als {min_words} Woerter
- NICHT mehr als {max_words} Woerter

STRUKTUR (5 Abschnitte):
1. Einleitung (~80 Woerter)
2. Was ist [Thema]? (~70 Woerter)
3. Hauptmerkmale (~70 Woerter)
4. Anwendung/Vorteile (~70 Woerter)
5. Fazit (~60 Woerter)

STIL:
- Informativ und praezise
- HTML: <h2> fuer Abschnitte, <p> fuer Absaetze
- Keine Code-Beispiele

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}',
			'medium' => 'Schreibe einen deutschen Artikel zum Thema: {post_title}

Kontext: {post_excerpt}

WORTANZAHL-ANFORDERUNG:
- Zielbereich: {min_words} bis {max_words} Woerter

STRUKTUR (7 Abschnitte):
1. Einleitung (~150 Woerter) - 2-3 Absaetze
2. Was ist [Thema]? - Grundlagen (~140 Woerter)
3. Warum ist das wichtig? (~140 Woerter)
4. Hauptmerkmale und Funktionen (~150 Woerter)
5. Vorteile und Nutzen (~140 Woerter)
6. Herausforderungen und Loesungen (~140 Woerter)
7. Fazit und Ausblick (~140 Woerter)

STIL:
- Informativ und praezise
- HTML: <h2> fuer Abschnitte, <p> fuer Absaetze
- Keine Code-Beispiele

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}',
			'long' => 'Schreibe einen deutschen Artikel zum Thema: {post_title}

Kontext: {post_excerpt}

WORTANZAHL: {min_words} bis {max_words} Woerter

STRUKTUR (9 Abschnitte a ~200 Woerter):
1. Einleitung
2. Grundlagen und Definition
3. Bedeutung und Relevanz
4. Hauptmerkmale im Detail
5. Vorteile und Chancen
6. Nachteile und Risiken
7. Anwendungsbereiche
8. Best Practices
9. Fazit

STIL:
- Informativ und praezise
- HTML: <h2> fuer Abschnitte, <p> fuer Absaetze
- Keine Code-Beispiele

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}',
			'verylong' => 'Schreibe einen deutschen Artikel zum Thema: {post_title}

Kontext: {post_excerpt}

WORTANZAHL: {min_words} bis {max_words} Woerter

STRUKTUR (11 Abschnitte a ~250 Woerter):
1. Einleitung
2. Grundlagen und Hintergrund
3. Bedeutung und Relevanz heute
4. Hauptmerkmale im Detail
5. Vorteile und Chancen
6. Nachteile und Herausforderungen
7. Anwendungsbereiche und Beispiele
8. Best Practices und Empfehlungen
9. Praktische Umsetzung
10. Zukunftsperspektiven
11. Fazit

STIL:
- Informativ und praezise
- HTML: <h2> fuer Abschnitte, <p> fuer Absaetze
- Keine Code-Beispiele

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}'
		);

		// Fix Windows line breaks (CRLF) to Unix (LF) in variants
		foreach ( $post_gen_variants as $key => $value ) {
			$post_gen_variants[ $key ] = str_replace( "\r\n", "\n", $value );
			$post_gen_variants[ $key ] = str_replace( "\r", "\n", $post_gen_variants[ $key ] );
		}
		
		// Pass array directly, not JSON string
		self::create_default_prompt(
			'Post-Generierung (mit Varianten)',
			$post_gen_variants,
			'post-generation',
			'generation',
			'gpt-5',
			0.2,
			10000,
			'json_object',
			true
		);

		// Correction Expand
		self::create_default_prompt(
			'Korrektur: Erweitern',
			'Der folgende Artikel hat nur {current_words} Wörter, braucht aber {min_words}-{max_words} Wörter.

WICHTIG: Erweitere den Inhalt, indem du:
1. Bestehende Abschnitte mit mehr Details und Beispielen erweiterst
2. Tiefergehende Erklärungen hinzufügst
3. Die Struktur und alle HTML-Tags beibehältst
4. NICHT neue Abschnitte hinzufügst

Aktueller Artikel:
{post_content}

Antworte NUR mit dem erweiterten HTML-Inhalt (keine JSON, kein zusätzlicher Text). Ziel: {min_words}-{max_words} Wörter.',
			'correction-expand',
			'correction_expand',
			'gpt-5-mini',
			0.3,
			6000,
			'text'
		);

		// Correction Shorten
		self::create_default_prompt(
			'Korrektur: Kürzen',
			'Der folgende Artikel hat {current_words} Wörter, darf aber nur {min_words}-{max_words} Wörter haben.

WICHTIG: Kürze den Inhalt, indem du:
1. Redundante Informationen entfernst
2. Absätze prägnanter formulierst
3. Die Kernaussagen und Struktur beibehältst
4. Die HTML-Struktur beibehältst

Aktueller Artikel:
{post_content}

Antworte NUR mit dem gekürzten HTML-Inhalt (keine JSON, kein zusätzlicher Text). Ziel: {min_words}-{max_words} Wörter.',
			'correction-shorten',
			'correction_shorten',
			'gpt-5-mini',
			0.3,
			6000,
			'text'
		);

		// Image Generation
		self::create_default_prompt(
			'Bild-Generierung',
			'Create a high-quality featured image for a blog post titled "{post_title}". The content is about: {post_excerpt}. Do not include any text, captions, labels, watermarks, typography, or logos in the image (text-free image).',
			'image-generation',
			'image',
			'gpt-image-1',
			0.7,
			null,
			'text'
		);
	}

	/**
	 * Helper to create default prompt.
	 *
	 * @param string $title Title.
	 * @param string $content Content.
	 * @param string $slug Slug.
	 * @param string $type Type.
	 * @param string $model Model.
	 * @param float  $temperature Temperature.
	 * @param int    $max_tokens Max tokens.
	 * @param string $response_format Response format.
	 * @param bool   $is_variants Whether content is JSON variants.
	 */
	private static function create_default_prompt( $title, $content, $slug, $type, $model, $temperature, $max_tokens, $response_format, $is_variants = false ) {
		$post_id = wp_insert_post( array(
			'post_title' => $title,
			'post_content' => $is_variants ? '' : $content,
			'post_status' => 'publish',
			'post_type' => 'ai_prompt',
		) );

		if ( $post_id ) {
			update_post_meta( $post_id, '_prompt_slug', $slug );
			update_post_meta( $post_id, '_prompt_type', $type );
			update_post_meta( $post_id, '_gpt_model', $model );
			update_post_meta( $post_id, '_gpt_temperature', $temperature );
			update_post_meta( $post_id, '_gpt_response_format', $response_format );
			update_post_meta( $post_id, '_is_active', '1' );

			if ( $max_tokens ) {
				update_post_meta( $post_id, '_gpt_max_tokens', $max_tokens );
			}

			if ( $is_variants ) {
				// If $content is already an array, WordPress will serialize it automatically
				// If it's a JSON string, store it as-is
				update_post_meta( $post_id, '_prompt_variants', $content );
			}

			// Set taxonomy term based on type
			$type_mapping = array(
				'generation' => 'Generation',
				'correction_expand' => 'Correction',
				'correction_shorten' => 'Correction',
				'system_generation' => 'System',
				'system_correction' => 'System',
				'image' => 'Image',
			);

			if ( isset( $type_mapping[ $type ] ) ) {
				$term_name = $type_mapping[ $type ];
				$term = term_exists( $term_name, 'prompt_type' );
				if ( ! $term ) {
					$term = wp_insert_term( $term_name, 'prompt_type', array( 'slug' => strtolower( $term_name ) ) );
				}
				if ( ! is_wp_error( $term ) ) {
					$term_id = is_array( $term ) ? $term['term_id'] : $term;
					wp_set_post_terms( $post_id, array( $term_id ), 'prompt_type', false );
				}
			}
		}
	}

	/**
	 * Show validation notices for prompts.
	 */
	public function show_validation_notices() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'ai_prompt' ) {
			return;
		}
		
		global $post;
		if ( ! $post ) {
			return;
		}
		
		$errors = get_transient( 'ai_prompt_validation_errors_' . $post->ID );
		if ( ! empty( $errors ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p><strong><?php esc_html_e( 'Prompt-Warnung:', 'ai-featured-image' ); ?></strong></p>
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p><?php esc_html_e( 'Der Prompt wurde gespeichert, enthält aber möglicherweise nicht alle erforderlichen Bestandteile.', 'ai-featured-image' ); ?></p>
			</div>
			<?php
			delete_transient( 'ai_prompt_validation_errors_' . $post->ID );
		}
	}

	/**
	 * Validate prompt content for required patterns.
	 *
	 * @param string $prompt_type Prompt type.
	 * @param string $content Prompt content.
	 * @param mixed  $variants Prompt variants (array or null).
	 * @return array Array of validation errors (empty if valid).
	 */
	private function validate_prompt( $prompt_type, $content, $variants ) {
		$errors = array();
		
		// Pflicht-Bestandteile je nach Typ
		$required_patterns = array(
			'generation' => array(
				'json' => '/JSON.*Format/i',
				'structure' => '/STRUKTUR/i',
			),
			'system_generation' => array(
				'json_response' => '/JSON/i',
			),
			'correction_expand' => array(
				'word_requirement' => '/\{min_words\}.*\{max_words\}/i',
			),
			'correction_shorten' => array(
				'word_requirement' => '/\{min_words\}.*\{max_words\}/i',
			),
		);
		
		if ( isset( $required_patterns[ $prompt_type ] ) ) {
			foreach ( $required_patterns[ $prompt_type ] as $key => $pattern ) {
				$search_in = $variants ? wp_json_encode( $variants ) : $content;
				if ( ! preg_match( $pattern, $search_in ) ) {
					$errors[] = sprintf( __( 'Fehlt: %s', 'ai-featured-image' ), $key );
				}
			}
		}
		
		return $errors;
	}
}

