<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_Featured_Image_Editor_Integration {
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_ai_meta_box' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_modal_html' ) );
	}

	public function register_ai_meta_box( $post_type ) {
		if ( post_type_supports( $post_type, 'thumbnail' ) && current_user_can( 'upload_files' ) ) {
			add_meta_box(
				'ai-post-generator',
				__( 'AI Post Generator', 'ai-featured-image' ),
				array( $this, 'render_ai_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public function render_ai_meta_box( $post ) {
		$options = get_option( 'ai_featured_image_options' );
		$default_len = isset( $options['default_post_length'] ) ? $options['default_post_length'] : 'short';
		?>
		<p>
			<button type="button" class="button button-secondary" id="ai-featured-image-generate-button"><?php esc_html_e( 'AI Beitragsbild festlegen', 'ai-featured-image' ); ?></button>
		</p>
		<hr />
		<p><strong><?php esc_html_e( 'AI-Beitrag erstellen', 'ai-featured-image' ); ?></strong></p>
		<p>
			<label for="ai-post-length"><small><?php esc_html_e( 'Länge', 'ai-featured-image' ); ?></small></label><br />
			<select id="ai-post-length">
				<option value="short" <?php selected( $default_len, 'short' ); ?>><?php esc_html_e( 'kurz (300–500)', 'ai-featured-image' ); ?></option>
				<option value="medium" <?php selected( $default_len, 'medium' ); ?>><?php esc_html_e( 'mittel (800–1200)', 'ai-featured-image' ); ?></option>
				<option value="long" <?php selected( $default_len, 'long' ); ?>><?php esc_html_e( 'lang (1500–2000)', 'ai-featured-image' ); ?></option>
				<option value="verylong" <?php selected( $default_len, 'verylong' ); ?>><?php esc_html_e( 'sehr lang (2500+)', 'ai-featured-image' ); ?></option>
			</select>
		</p>
		<p>
			<button type="button" class="button button-primary" id="ai-generate-post-button"><?php esc_html_e( 'AI-Beitrag erstellen', 'ai-featured-image' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'Erstellt Inhalt, wählt Kategorie und setzt 7–10 Schlagwörter.', 'ai-featured-image' ); ?></p>
		<?php
	}

	public function enqueue_block_editor_assets() {
		// Placeholder for potential Gutenberg panel script; we rely on admin.js for UI
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) return;

		$css = plugin_dir_path( __FILE__ ) . '../assets/css/admin.css';
		$js  = plugin_dir_path( __FILE__ ) . '../assets/js/admin.js';
		$css_ver = file_exists( $css ) ? filemtime( $css ) : AI_FEATURED_IMAGE_VERSION;
		$js_ver  = file_exists( $js ) ? filemtime( $js ) : AI_FEATURED_IMAGE_VERSION;

		wp_enqueue_style( 'ai-featured-image-admin', plugin_dir_url( __FILE__ ) . '../assets/css/admin.css', array(), $css_ver );
		wp_enqueue_script( 'ai-featured-image-admin', plugin_dir_url( __FILE__ ) . '../assets/js/admin.js', array( 'jquery' ), $js_ver, true );

		wp_localize_script( 'ai-featured-image-admin', 'aiFeaturedImageData', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'post_id'  => get_the_ID(),
			'nonce'    => wp_create_nonce( 'ai_featured_image_nonce' ),
			'is_gutenberg' => get_current_screen()->is_block_editor(),
			'i18n'     => array(
				'generating_keywords' => __( 'Generating...', 'ai-featured-image' ),
				'generating_post'     => __( 'Generating post…', 'ai-featured-image' ),
			),
			'asset_version' => $js_ver,
		) );
	}

	public function render_modal_html() {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) return;
		$options = get_option( 'ai_featured_image_options' );
		$dimensions = ! empty( $options['image_dimensions'] ) ? $options['image_dimensions'] : '1024x1024';
		$num_images = ! empty( $options['num_images'] ) ? intval( $options['num_images'] ) : 1;
		?>
		<div id="ai-featured-image-modal" class="ai-modal" style="display:none;">
			<div class="ai-modal-content">
				<span class="ai-modal-close">&times;</span>
				<h2><?php esc_html_e( 'Generate AI Featured Image', 'ai-featured-image' ); ?></h2>
				<div class="ai-modal-body">
					<div id="ai-modal-error-container" class="notice notice-error" style="display:none;"></div>
					<table class="form-table"><tbody>
						<tr>
							<th scope="row"><label for="ai-num-images"><?php esc_html_e( 'Number of Images', 'ai-featured-image' ); ?></label></th>
							<td>
								<select id="ai-num-images">
									<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
										<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $num_images, $i ); ?>><?php echo esc_html( $i ); ?></option>
									<?php endfor; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ai-image-dimensions"><?php esc_html_e( 'Dimensions', 'ai-featured-image' ); ?></label></th>
							<td><input type="text" id="ai-image-dimensions" value="<?php echo esc_attr( $dimensions ); ?>" readonly></td>
						</tr>
					</tbody></table>
					<div id="ai-loading" class="ai-loading" style="display:none;">
						<div class="ai-spinner"></div>
						<span class="ai-loading-text">Generating...</span>
					</div>
					<div id="ai-image-preview-container" style="margin-top:20px;"></div>
				</div>
				<div class="ai-modal-footer">
					<button class="button" id="ai-set-featured-image-button" style="display:none;" disabled><?php esc_html_e( 'Set as Featured Image', 'ai-featured-image' ); ?></button>
					<button class="button button-primary" id="ai-generate-image-button"><?php esc_html_e( 'Generate', 'ai-featured-image' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
