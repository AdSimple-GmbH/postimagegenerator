<?php
/**
 * Handles the OpenAI API connection.
 *
 * @package AI_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class AI_Featured_Image_API_Connector
 */
class AI_Featured_Image_API_Connector {
    /**
     * AI_Featured_Image_API_Connector constructor.
     */
    public function __construct() {
        // AJAX action for generating the image.
        add_action( 'wp_ajax_generate_ai_image', array( $this, 'generate_image_callback' ) );
        add_action( 'wp_ajax_upload_ai_image', array( $this, 'upload_image_callback' ) );
    }

    /**
     * AJAX callback to generate the image.
     */
    public function generate_image_callback() {
        check_ajax_referer( 'ai_featured_image_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'ai-featured-image' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $style   = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'realistic';
        $quality = isset( $_POST['quality'] ) ? sanitize_text_field( $_POST['quality'] ) : 'standard'; // Assuming 'standard' as a default quality.

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid Post ID.', 'ai-featured-image' ) ) );
        }
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => __( 'Post not found.', 'ai-featured-image' ) ) );
        }

        $options = get_option( 'ai_featured_image_options' );
        $api_key = ! empty( $options['api_key'] ) ? $options['api_key'] : '';

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'OpenAI API key is not set.', 'ai-featured-image' ) ) );
        }

        $prompt = sprintf(
            'A %s image for a blog post titled "%s". The content is about: %s',
            $style,
            $post->post_title,
            wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 50 ) )
        );

        $api_url = 'https://api.openai.com/v1/images/generations';
        $body    = array(
            'model'   => 'dall-e-2', // Or dall-e-3, depending on availability
            'prompt'  => $prompt,
            'n'       => 1,
            'size'    => $options['image_dimensions'] ?? '1024x1024',
            'quality' => $quality,
        );

        $response = wp_remote_post(
            $api_url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( isset( $data['error'] ) ) {
            wp_send_json_error( array( 'message' => $data['error']['message'] ) );
        }

        if ( empty( $data['data'][0]['url'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Could not retrieve image from OpenAI.', 'ai-featured-image' ) ) );
        }

        wp_send_json_success( array( 'images' => $data['data'] ) );
    }

    /**
     * AJAX callback to upload the selected image to the media library.
     */
    public function upload_image_callback() {
        check_ajax_referer( 'ai_featured_image_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'ai-featured-image' ) ) );
        }

        $image_url = isset( $_POST['image_url'] ) ? esc_url_raw( $_POST['image_url'] ) : '';
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( empty( $image_url ) || ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid data provided.', 'ai-featured-image' ) ) );
        }

        // Needed for media_sideload_image()
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Sideload the image
        $attachment_id = media_sideload_image( $image_url, $post_id, 'AI Generated Image', 'id' );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        // Set the image as the featured image
        set_post_thumbnail( $post_id, $attachment_id );
        
        wp_send_json_success( array( 
            'attachment_id' => $attachment_id,
            'thumbnail_url' => get_the_post_thumbnail_url( $post_id, 'medium' )
        ) );
    }
} 