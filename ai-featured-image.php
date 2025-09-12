<?php
/**
 * Plugin Name:       AI Featured Image
 * Description:       Generate and set a featured image for posts using OpenAI gpt-image-1.
 * Version:           1.0.0
 * Author:            Andreas Ostheimer
 * Text Domain:       ai-featured-image
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AI_FEATURED_IMAGE_VERSION' ) ) {
	define( 'AI_FEATURED_IMAGE_VERSION', '1.0.0' );
}

function ai_featured_image_load_textdomain() {
	load_plugin_textdomain( 'ai-featured-image', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'ai_featured_image_load_textdomain' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-editor-integration.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-api-connector.php';

new AI_Featured_Image_Settings();
new AI_Featured_Image_Editor_Integration();
new AI_Featured_Image_API_Connector();
