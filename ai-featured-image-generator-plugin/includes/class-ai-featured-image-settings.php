<?php
/**
 * Handles the settings page for the AI Featured Image plugin.
 *
 * @package AI_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class AI_Featured_Image_Settings
 */
class AI_Featured_Image_Settings {
    
    private $option_group = 'ai_featured_image_settings';
    private $option_name = 'ai_featured_image_options';

    /**
     * AI_Featured_Image_Settings constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add the settings page to the admin menu.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'AI Featured Image Settings', 'ai-featured-image' ),
            __( 'AI Featured Image', 'ai-featured-image' ),
            'manage_options',
            'ai-featured-image-settings',
            array( $this, 'settings_page_html' )
        );
    }

    /**
     * Render the settings page HTML.
     */
    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
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

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        register_setting( $this->option_group, $this->option_name, array( $this, 'sanitize_options' ) );

        // API Settings Section
        add_settings_section(
            'api_settings_section',
            __( 'API Settings', 'ai-featured-image' ),
            '__return_false', // No callback needed for the section description.
            'ai-featured-image-settings'
        );

        add_settings_field(
            'api_key',
            __( 'OpenAI API Key', 'ai-featured-image' ),
            array( $this, 'render_api_key_field' ),
            'ai-featured-image-settings',
            'api_settings_section'
        );

        // Image Settings Section
        add_settings_section(
            'image_settings_section',
            __( 'Default Image Settings', 'ai-featured-image' ),
            '__return_false',
            'ai-featured-image-settings'
        );

        add_settings_field(
            'image_dimensions',
            __( 'Image Dimensions', 'ai-featured-image' ),
            array( $this, 'render_image_dimensions_field' ),
            'ai-featured-image-settings',
            'image_settings_section'
        );

        add_settings_field(
            'image_style',
            __( 'Render Style (gpt-image-1)', 'ai-featured-image' ),
            array( $this, 'render_image_style_field' ),
            'ai-featured-image-settings',
            'image_settings_section'
        );

        add_settings_field(
            'num_images',
            __( 'Number of Images', 'ai-featured-image' ),
            array( $this, 'render_num_images_field' ),
            'ai-featured-image-settings',
            'image_settings_section'
        );

        add_settings_field(
            'file_format',
            __( 'File Format', 'ai-featured-image' ),
            array( $this, 'render_file_format_field' ),
            'ai-featured-image-settings',
            'image_settings_section'
        );

        add_settings_field(
            'quality_presets',
            __( 'Quality Presets', 'ai-featured-image' ),
            array( $this, 'render_quality_presets_field' ),
            'ai-featured-image-settings',
            'image_settings_section'
        );

        add_settings_field(
            'styles_moods',
            __( 'Available Styles/Moods', 'ai-featured-image' ),
            array( $this, 'render_styles_moods_field' ),
            'ai-featured-image-settings',
            'image_settings_section'
        );
    }

    /**
     * Render the API Key field.
     */
    public function render_api_key_field() {
        $options = get_option( $this->option_name );
        $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        ?>
        <input type="password" name="<?php echo esc_attr( $this->option_name ); ?>[api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
        <p class="description"><?php esc_html_e( 'Enter your OpenAI API key.', 'ai-featured-image' ); ?></p>
        <?php
    }

    /**
     * Render the Image Dimensions field.
     */
    public function render_image_dimensions_field() {
        $options = get_option( $this->option_name );
        $dimensions = isset( $options['image_dimensions'] ) ? $options['image_dimensions'] : '1024x1024';
        $available_dimensions = array( '1024x1024', '1024x1536', '1536x1024' );
        ?>
        <select name="<?php echo esc_attr( $this->option_name ); ?>[image_dimensions]">
            <?php foreach ( $available_dimensions as $dim ) : ?>
                <option value="<?php echo esc_attr( $dim ); ?>" <?php selected( $dimensions, $dim ); ?>>
                    <?php echo esc_html( $dim ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render the Image Style field for gpt-image-1.
     */
    public function render_image_style_field() {
        $options = get_option( $this->option_name );
        $image_style = isset( $options['image_style'] ) ? $options['image_style'] : 'vivid';
        $available = array( 'vivid', 'natural' );
        ?>
        <select name="<?php echo esc_attr( $this->option_name ); ?>[image_style]">
            <?php foreach ( $available as $style ) : ?>
                <option value="<?php echo esc_attr( $style ); ?>" <?php selected( $image_style, $style ); ?>>
                    <?php echo esc_html( ucfirst( $style ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render the Number of Images field.
     */
    public function render_num_images_field() {
        $options = get_option( $this->option_name );
        $num_images = isset( $options['num_images'] ) ? intval( $options['num_images'] ) : 1;
        ?>
        <select name="<?php echo esc_attr( $this->option_name ); ?>[num_images]">
            <?php for ( $i = 1; $i <= 4; $i++ ) : ?>
                <option value="<?php echo esc_attr( $i ); ?>" <?php selected( $num_images, $i ); ?>>
                    <?php echo esc_html( $i ); ?>
                </option>
            <?php endfor; ?>
        </select>
        <?php
    }

    /**
     * Render the File Format field.
     */
    public function render_file_format_field() {
        $options = get_option( $this->option_name );
        $file_format = isset( $options['file_format'] ) ? $options['file_format'] : 'jpeg';
        ?>
        <label>
            <input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[file_format]" value="jpeg" <?php checked( $file_format, 'jpeg' ); ?>>
            <?php esc_html_e( 'JPEG', 'ai-featured-image' ); ?>
        </label>
        <br>
        <label>
            <input type="radio" name="<?php echo esc_attr( $this->option_name ); ?>[file_format]" value="png" <?php checked( $file_format, 'png' ); ?>>
            <?php esc_html_e( 'PNG', 'ai-featured-image' ); ?>
        </label>
        <?php
    }

    /**
     * Render the Quality Presets field.
     */
    public function render_quality_presets_field() {
        $options = get_option( $this->option_name );
        $quality_presets = isset( $options['quality_presets'] ) ? $options['quality_presets'] : array( 'high' );
        $available_presets = array( 'high', 'medium', 'low' );
        ?>
        <?php foreach ( $available_presets as $preset ) : ?>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[quality_presets][]" value="<?php echo esc_attr( $preset ); ?>" <?php checked( in_array( $preset, $quality_presets, true ) ); ?>>
                <?php echo esc_html( ucfirst( $preset ) ); ?>
            </label>
            <br>
        <?php endforeach; ?>
        <?php
    }

    /**
     * Render the Styles/Moods field.
     */
    public function render_styles_moods_field() {
        $options = get_option( $this->option_name );
        $styles_moods = isset( $options['styles_moods'] ) ? $options['styles_moods'] : 'realistic, cartoon, minimal';
        ?>
        <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[styles_moods]" value="<?php echo esc_attr( $styles_moods ); ?>" class="regular-text">
        <p class="description"><?php esc_html_e( 'Enter a comma-separated list of available styles or moods.', 'ai-featured-image' ); ?></p>
        <?php
    }

    /**
     * Sanitize the option values.
     *
     * @param array $input The input array.
     * @return array The sanitized array.
     */
    public function sanitize_options( $input ) {
        $sanitized_input = array();

        if ( isset( $input['api_key'] ) ) {
            $sanitized_input['api_key'] = sanitize_text_field( $input['api_key'] );
        }

        if ( isset( $input['image_dimensions'] ) ) {
            $sanitized_input['image_dimensions'] = sanitize_text_field( $input['image_dimensions'] );
        }

        if ( isset( $input['file_format'] ) ) {
            $sanitized_input['file_format'] = in_array( $input['file_format'], array( 'jpeg', 'png' ) ) ? $input['file_format'] : 'jpeg';
        }

        if ( isset( $input['quality_presets'] ) && is_array( $input['quality_presets'] ) ) {
            $sanitized_input['quality_presets'] = array_map( 'sanitize_text_field', $input['quality_presets'] );
        } else {
            $sanitized_input['quality_presets'] = array( 'high' );
        }

        if ( isset( $input['styles_moods'] ) ) {
            $sanitized_input['styles_moods'] = sanitize_text_field( $input['styles_moods'] );
        }

        if ( isset( $input['image_style'] ) ) {
            $allowed = array( 'vivid', 'natural' );
            $style = sanitize_text_field( $input['image_style'] );
            $sanitized_input['image_style'] = in_array( $style, $allowed, true ) ? $style : 'vivid';
        }

        if ( isset( $input['num_images'] ) ) {
            $n = intval( $input['num_images'] );
            if ( $n < 1 ) { $n = 1; }
            if ( $n > 4 ) { $n = 4; }
            $sanitized_input['num_images'] = $n;
        }
        
        return $sanitized_input;
    }
} 