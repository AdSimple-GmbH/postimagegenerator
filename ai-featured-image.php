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
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-prompt-loader.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-prompt-cpt.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-api-connector.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-dashboard.php';

new AI_Featured_Image_Settings();
new AI_Featured_Image_Editor_Integration();
new AI_Featured_Image_Prompt_CPT();
new AI_Featured_Image_API_Connector();
new AI_Featured_Image_Dashboard();

// Load WP-CLI commands if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-featured-image-cli.php';
}

/**
 * Plugin activation hook.
 */
function ai_featured_image_activate() {
	// Flush rewrite rules for custom post type
	flush_rewrite_rules();
	
	// Setup default prompts
	AI_Featured_Image_Prompt_CPT::setup_default_prompts();
}
register_activation_hook( __FILE__, 'ai_featured_image_activate' );

/**
 * Plugin deactivation hook.
 */
function ai_featured_image_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ai_featured_image_deactivate' );
