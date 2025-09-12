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
        $n       = isset( $_POST['n'] ) ? max( 1, min( 4, intval( $_POST['n'] ) ) ) : 1;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid Post ID.', 'ai-featured-image' ) ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => __( 'Post not found.', 'ai-featured-image' ) ) );
        }

        $options = get_option( 'ai_featured_image_options' );
        $api_key = ! empty( $options['api_key'] ) ? $options['api_key'] : '';
        $size    = ! empty( $options['image_dimensions'] ) ? $options['image_dimensions'] : '1024x1024';
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'OpenAI API key is not set.', 'ai-featured-image' ) ) );
        }

        $prompt = sprintf(
            'Create a high-quality featured image for a blog post titled "%s". The content is about: %s. Do not include any text, captions, labels, watermarks, typography, or logos in the image (text-free image).',
            $post->post_title,
            wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 50 ) )
        );

        $api_url = 'https://api.openai.com/v1/images/generations';
        $body    = array(
            'model'  => 'gpt-image-1',
            'prompt' => $prompt,
            'n'      => $n,
            'size'   => $size,
        );

        $response = wp_remote_post( $api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 180,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['error'] ) ) {
            wp_send_json_error( array( 'message' => $data['error']['message'] ) );
        }
        if ( empty( $data['data'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Could not retrieve image from OpenAI.', 'ai-featured-image' ) ) );
        }
        wp_send_json_success( array( 'images' => $data['data'] ) );
    }

    /**
     * AJAX callback to upload the selected image to the media library.
     */
    public function upload_image_callback() {
        check_ajax_referer( 'ai_featured_image_nonce', 'nonce' );
        @ini_set( 'memory_limit', '512M' );
        @set_time_limit( 180 );
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'ai-featured-image' ) ) );
        }

        $image_url = isset( $_POST['image_url'] ) ? esc_url_raw( $_POST['image_url'] ) : '';
        $image_b64 = isset( $_POST['image_b64'] ) ? $_POST['image_b64'] : '';
        $image_mime = isset( $_POST['image_mime'] ) ? sanitize_text_field( $_POST['image_mime'] ) : 'image/png';
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid data provided.', 'ai-featured-image' ) ) );
        }

        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        if ( $image_b64 ) {
            $decoded = base64_decode( $image_b64 );
            if ( false === $decoded ) {
                wp_send_json_error( array( 'message' => __( 'Invalid image data.', 'ai-featured-image' ) ) );
            }
            $tmp = wp_tempnam( 'ai-image' );
            file_put_contents( $tmp, $decoded );
            $ext = ( strpos( $image_mime, 'jpeg' ) !== false || strpos( $image_mime, 'jpg' ) !== false ) ? 'jpg' : 'png';
            $file_array = array(
                'name'     => 'ai-image.' . $ext,
                'type'     => $image_mime,
                'tmp_name' => $tmp,
                'size'     => filesize( $tmp ),
            );
            $attachment_id = media_handle_sideload( $file_array, $post_id, 'AI Generated Image' );
            @unlink( $tmp );
            if ( is_wp_error( $attachment_id ) ) {
                wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
            }
        } else {
            if ( empty( $image_url ) ) {
                wp_send_json_error( array( 'message' => __( 'Invalid data provided.', 'ai-featured-image' ) ) );
            }
            $attachment_id = media_sideload_image( $image_url, $post_id, 'AI Generated Image', 'id' );
            if ( is_wp_error( $attachment_id ) ) {
                wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
            }
        }

        set_post_thumbnail( $post_id, $attachment_id );
        wp_send_json_success( array( 'attachment_id' => $attachment_id, 'thumbnail_url' => get_the_post_thumbnail_url( $post_id, 'medium' ) ) );
    }
} 