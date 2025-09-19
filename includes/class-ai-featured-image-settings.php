<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_Featured_Image_Settings {
	private $option_group = 'ai_featured_image_settings';
	private $option_name  = 'ai_featured_image_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_settings_page() {
		add_options_page(
			__( 'AI Featured Image Settings', 'ai-featured-image' ),
			__( 'AI Featured Image', 'ai-featured-image' ),
			'manage_options',
			'ai-featured-image-settings',
			array( $this, 'settings_page_html' )
		);
	}

	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( $this->option_group );
				do_settings_sections( 'ai-featured-image-settings' );
				submit_button( __( 'Save Settings', 'ai-featured-image' ) );
				?>
			</form>
		</div>
		<?php
	}

	public function register_settings() {
		register_setting( $this->option_group, $this->option_name, array( $this, 'sanitize_options' ) );

		add_settings_section( 'api_settings', __( 'API Settings', 'ai-featured-image' ), '__return_false', 'ai-featured-image-settings' );
		add_settings_field( 'api_key', __( 'OpenAI API Key', 'ai-featured-image' ), array( $this, 'render_api_key_field' ), 'ai-featured-image-settings', 'api_settings' );

		add_settings_section( 'image_settings', __( 'Default Image Settings', 'ai-featured-image' ), '__return_false', 'ai-featured-image-settings' );
		add_settings_field( 'image_dimensions', __( 'Image Dimensions', 'ai-featured-image' ), array( $this, 'render_image_dimensions_field' ), 'ai-featured-image-settings', 'image_settings' );
		add_settings_field( 'file_format', __( 'File Format', 'ai-featured-image' ), array( $this, 'render_file_format_field' ), 'ai-featured-image-settings', 'image_settings' );
		add_settings_field( 'quality_presets', __( 'Quality Presets', 'ai-featured-image' ), array( $this, 'render_quality_presets_field' ), 'ai-featured-image-settings', 'image_settings' );
		add_settings_field( 'styles_moods', __( 'Available Styles/Moods', 'ai-featured-image' ), array( $this, 'render_styles_moods_field' ), 'ai-featured-image-settings', 'image_settings' );
		add_settings_field( 'image_style', __( 'Render Style (gpt-image-1)', 'ai-featured-image' ), array( $this, 'render_image_style_field' ), 'ai-featured-image-settings', 'image_settings' );
		add_settings_field( 'num_images', __( 'Number of Images', 'ai-featured-image' ), array( $this, 'render_num_images_field' ), 'ai-featured-image-settings', 'image_settings' );

		add_settings_section( 'content_settings', __( 'Default Content Settings', 'ai-featured-image' ), '__return_false', 'ai-featured-image-settings' );
		add_settings_field( 'default_post_length', __( 'Default Post Length', 'ai-featured-image' ), array( $this, 'render_default_post_length_field' ), 'ai-featured-image-settings', 'content_settings' );

		add_settings_section( 'automation_settings', __( 'Automation', 'ai-featured-image' ), '__return_false', 'ai-featured-image-settings' );
		add_settings_field( 'auto_on_publish', __( 'Auto-generate on publish', 'ai-featured-image' ), array( $this, 'render_auto_on_publish_field' ), 'ai-featured-image-settings', 'automation_settings' );
		add_settings_field( 'auto_only_if_missing', __( 'Only if no featured image is set', 'ai-featured-image' ), array( $this, 'render_auto_only_if_missing_field' ), 'ai-featured-image-settings', 'automation_settings' );
	}

	public function render_api_key_field() {
		$options = get_option( $this->option_name );
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
		?>
		<input type="password" name="<?php echo esc_attr( $this->option_name ); ?>[api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Enter your OpenAI API key.', 'ai-featured-image' ); ?></p>
		<?php
	}

	public function render_image_dimensions_field() {
		$options = get_option( $this->option_name );
		$dimensions = isset( $options['image_dimensions'] ) ? $options['image_dimensions'] : '1024x1024';
		$available  = array( '1024x1024', '1024x1536', '1536x1024' );
		?>
		<select name="<?php echo esc_attr( $this->option_name ); ?>[image_dimensions]">
			<?php foreach ( $available as $dim ) : ?>
				<option value="<?php echo esc_attr( $dim ); ?>" <?php selected( $dimensions, $dim ); ?>><?php echo esc_html( $dim ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_file_format_field() {
		$options = get_option( $this->option_name );
		$file_format = isset( $options['file_format'] ) ? $options['file_format'] : 'jpeg';
		?>
		<label><input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[file_format]" value="jpeg" <?php checked( $file_format, 'jpeg' ); ?>> JPEG</label><br />
		<label><input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[file_format]" value="png" <?php checked( $file_format, 'png' ); ?>> PNG</label>
		<?php
	}

	public function render_quality_presets_field() {
		$options = get_option( $this->option_name );
		$quality = isset( $options['quality_presets'] ) ? (array) $options['quality_presets'] : array( 'high' );
		$available = array( 'high', 'medium', 'low' );
		foreach ( $available as $q ) {
			echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[quality_presets][]" value="' . esc_attr( $q ) . '" ' . checked( in_array( $q, $quality, true ), true, false ) . '> ' . esc_html( ucfirst( $q ) ) . '</label><br />';
		}
	}

	public function render_styles_moods_field() {
		$options = get_option( $this->option_name );
		$styles = isset( $options['styles_moods'] ) ? $options['styles_moods'] : 'realistic, cartoon, minimal';
		?>
		<input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[styles_moods]" value="<?php echo esc_attr( $styles ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Comma separated list of allowed styles/moods.', 'ai-featured-image' ); ?></p>
		<?php
	}

	public function render_image_style_field() {
		$options = get_option( $this->option_name );
		$image_style = isset( $options['image_style'] ) ? $options['image_style'] : 'vivid';
		$available = array( 'vivid', 'natural' );
		?>
		<select name="<?php echo esc_attr( $this->option_name ); ?>[image_style]">
			<?php foreach ( $available as $s ) : ?>
				<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $image_style, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_num_images_field() {
		$options = get_option( $this->option_name );
		$num = isset( $options['num_images'] ) ? intval( $options['num_images'] ) : 1;
		?>
		<select name="<?php echo esc_attr( $this->option_name ); ?>[num_images]">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $num, $i ); ?>><?php echo esc_html( $i ); ?></option>
			<?php endfor; ?>
		</select>
		<?php
	}

	public function render_default_post_length_field() {
		$options = get_option( $this->option_name );
		$len = isset( $options['default_post_length'] ) ? $options['default_post_length'] : 'short';
		$choices = array(
			'short'    => __( 'KurZ (300–500 Worte)', 'ai-featured-image' ),
			'medium'   => __( 'Mittel (800–1200 Worte)', 'ai-featured-image' ),
			'long'     => __( 'Lang (1500–2000 Worte)', 'ai-featured-image' ),
			'verylong' => __( 'Sehr lang (2500+ Worte)', 'ai-featured-image' ),
		);
		?>
		<select name="<?php echo esc_attr( $this->option_name ); ?>[default_post_length]">
			<?php foreach ( $choices as $k => $label ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $len, $k ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_auto_on_publish_field() {
		$options = get_option( $this->option_name );
		$enabled = ! empty( $options['auto_on_publish'] );
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[auto_on_publish]" value="1" <?php checked( $enabled, true ); ?> /> <?php esc_html_e( 'Generate an AI featured image automatically on publish', 'ai-featured-image' ); ?></label>
		<?php
	}

	public function render_auto_only_if_missing_field() {
		$options = get_option( $this->option_name );
		$only_missing = isset( $options['auto_only_if_missing'] ) ? (bool) $options['auto_only_if_missing'] : true;
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[auto_only_if_missing]" value="1" <?php checked( $only_missing, true ); ?> /> <?php esc_html_e( 'Only run if no featured image is already set', 'ai-featured-image' ); ?></label>
		<?php
	}

	public function sanitize_options( $input ) {
		$san = array();
		if ( isset( $input['api_key'] ) ) $san['api_key'] = sanitize_text_field( $input['api_key'] );
		if ( isset( $input['image_dimensions'] ) ) $san['image_dimensions'] = sanitize_text_field( $input['image_dimensions'] );
		if ( isset( $input['file_format'] ) ) $san['file_format'] = in_array( $input['file_format'], array( 'jpeg', 'png' ), true ) ? $input['file_format'] : 'jpeg';
		if ( isset( $input['quality_presets'] ) && is_array( $input['quality_presets'] ) ) $san['quality_presets'] = array_map( 'sanitize_text_field', $input['quality_presets'] );
		if ( isset( $input['styles_moods'] ) ) $san['styles_moods'] = sanitize_text_field( $input['styles_moods'] );
		if ( isset( $input['image_style'] ) ) { $style = sanitize_text_field( $input['image_style'] ); $san['image_style'] = in_array( $style, array( 'vivid','natural' ), true ) ? $style : 'vivid'; }
		if ( isset( $input['num_images'] ) ) { $n = max(1, min(4, intval( $input['num_images'] ))); $san['num_images'] = $n; }
		if ( isset( $input['default_post_length'] ) ) {
			$len = sanitize_text_field( $input['default_post_length'] );
			$san['default_post_length'] = in_array( $len, array( 'short','medium','long','verylong' ), true ) ? $len : 'short';
		}
		$san['auto_on_publish']      = ! empty( $input['auto_on_publish'] ) ? 1 : 0;
		$san['auto_only_if_missing'] = isset( $input['auto_only_if_missing'] ) ? ( $input['auto_only_if_missing'] ? 1 : 0 ) : 1;
		return $san;
	}
}
