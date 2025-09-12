<?php
/**
 * Plugin Name:       WP AI Image Generator
 * Plugin URI:        https://example.com/
 * Description:       Automatically generates a featured image for any post using the OpenAI API.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-featured-image
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Load plugin textdomain.
 */
function ai_featured_image_load_textdomain() {
    load_plugin_textdomain( 'ai-featured-image', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'ai_featured_image_load_textdomain' );

// Enable debug logging by default in dev; override via wp-config.php if needed.
if ( ! defined( 'AI_FEATURED_IMAGE_DEBUG' ) ) {
    define( 'AI_FEATURED_IMAGE_DEBUG', true );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-editor-integration.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-api-connector.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-logger.php';

new AI_Featured_Image_Settings();
new AI_Featured_Image_Editor_Integration();
new AI_Featured_Image_API_Connector(); 