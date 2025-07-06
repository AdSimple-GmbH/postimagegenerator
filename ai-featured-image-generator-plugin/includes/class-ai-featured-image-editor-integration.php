<?php
/**
 * Handles the editor integration for the AI Featured Image plugin.
 *
 * @package AI_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class AI_Featured_Image_Editor_Integration
 */
class AI_Featured_Image_Editor_Integration {
    
    /**
     * AI_Featured_Image_Editor_Integration constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_link_to_classic_editor' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_footer', array( $this, 'render_modal_html' ) );
    }

    /**
     * Add the link to the classic editor.
     *
     * @param string $post_type The current post type.
     */
    public function add_link_to_classic_editor( $post_type ) {
        if ( post_type_supports( $post_type, 'thumbnail' ) && current_user_can('upload_files') ) {
            add_action( 'admin_footer-post.php', array( $this, 'render_classic_editor_link' ) );
            add_action( 'admin_footer-post-new.php', array( $this, 'render_classic_editor_link' ) );
        }
    }

    /**
     * Render the link in the classic editor using JavaScript.
     */
    public function render_classic_editor_link() {
        // Security check
        $screen = get_current_screen();
        if ( ! $screen || $screen->is_block_editor() ) {
            return;
        }
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var featuredImageDiv = document.getElementById('postimagediv');
                if (featuredImageDiv) {
                    var link = document.createElement('a');
                    link.href = '#';
                    link.id = 'ai-featured-image-generate-button';
                    link.className = 'button';
                    link.style.marginTop = '10px';
                    link.innerText = '<?php echo esc_js( __( 'AI Beitragsbild festlegen', 'ai-featured-image' ) ); ?>';
                    
                    var p = featuredImageDiv.querySelector('.inside');
                    if(p) {
                       p.appendChild(link);
                    }
                }
            });
        </script>
        <?php
    }

    /**
     * Enqueue assets for the block editor.
     */
    public function enqueue_block_editor_assets() {
        $asset_file = include( plugin_dir_path( __FILE__ ) . '../assets/js/editor.asset.php');

        wp_enqueue_script(
            'ai-featured-image-editor',
            plugin_dir_url( __FILE__ ) . '../assets/js/editor.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );
    }

    /**
     * Enqueue scripts and styles for the admin area.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ai-featured-image-admin',
            plugin_dir_url( __FILE__ ) . '../assets/css/admin.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'ai-featured-image-admin',
            plugin_dir_url( __FILE__ ) . '../assets/js/admin.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_localize_script(
            'ai-featured-image-admin',
            'aiFeaturedImageData',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'post_id'  => get_the_ID(),
                'nonce'    => wp_create_nonce( 'ai_featured_image_nonce' ),
                'is_gutenberg' => get_current_screen()->is_block_editor(),
            )
        );
    }

    /**
     * Render the modal HTML structure in the admin footer.
     */
    public function render_modal_html() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) {
            return;
        }

        $options = get_option( 'ai_featured_image_options' );
        $styles_moods = ! empty( $options['styles_moods'] ) ? array_map( 'trim', explode( ',', $options['styles_moods'] ) ) : array();
        $quality_presets = ! empty( $options['quality_presets'] ) ? $options['quality_presets'] : array();
        $dimensions = ! empty( $options['image_dimensions'] ) ? $options['image_dimensions'] : '1024x1024';

        ?>
        <div id="ai-featured-image-modal" class="ai-modal" style="display: none;">
            <div class="ai-modal-content">
                <span class="ai-modal-close">&times;</span>
                <h2><?php esc_html_e( 'Generate AI Featured Image', 'ai-featured-image' ); ?></h2>
                <div class="ai-modal-body">
                    <div id="ai-modal-error-container" class="notice notice-error" style="display: none;"></div>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="ai-image-style"><?php esc_html_e( 'Style/Mood', 'ai-featured-image' ); ?></label></th>
                                <td>
                                    <select id="ai-image-style" name="ai_image_style">
                                        <?php foreach ( $styles_moods as $style ) : ?>
                                            <option value="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( ucfirst( $style ) ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ai-image-quality"><?php esc_html_e( 'Quality', 'ai-featured-image' ); ?></label></th>
                                <td>
                                    <select id="ai-image-quality" name="ai_image_quality">
                                        <?php foreach ( $quality_presets as $quality ) : ?>
                                            <option value="<?php echo esc_attr( $quality ); ?>"><?php echo esc_html( ucfirst( $quality ) ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                             <tr>
                                <th scope="row"><label for="ai-image-dimensions"><?php esc_html_e( 'Dimensions', 'ai-featured-image' ); ?></label></th>
                                <td>
                                    <input type="text" id="ai-image-dimensions" name="ai_image_dimensions" value="<?php echo esc_attr( $dimensions ); ?>" readonly>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div id="ai-image-preview-container" style="margin-top: 20px;"></div>
                </div>
                <div class="ai-modal-footer">
                    <button class="button" id="ai-set-featured-image-button" style="display:none;"><?php esc_html_e( 'Set as Featured Image', 'ai-featured-image' ); ?></button>
                    <button class="button button-primary" id="ai-generate-image-button"><?php esc_html_e( 'Generate', 'ai-featured-image' ); ?></button>
                    <span class="spinner"></span>
                </div>
            </div>
        </div>
        <?php
    }
} 