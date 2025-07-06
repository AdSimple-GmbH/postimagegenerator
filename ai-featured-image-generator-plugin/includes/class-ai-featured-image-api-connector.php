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
     * The path to the log file.
     * @var string
     */
    private $log_file;

    /**
     * AI_Featured_Image_API_Connector constructor.
     */
    public function __construct() {
        $this->log_file = plugin_dir_path( __FILE__ ) . 'debug.log';
        // AJAX action for generating the image.
        add_action( 'wp_ajax_generate_ai_image', array( $this, 'generate_image_callback' ) );
        add_action( 'wp_ajax_set_ai_featured_image', array( $this, 'set_featured_image_callback' ) );
    }

    private function log_message( $message ) {
        $timestamp = date( 'Y-m-d H:i:s' );
        if ( is_array( $message ) || is_object( $message ) ) {
            $log_message = print_r( $message, true );
        } else {
            $log_message = $message;
        }
        file_put_contents( $this->log_file, "[$timestamp] " . $log_message . "\n", FILE_APPEND );
    }

    /**
     * AJAX callback to generate the image.
     */
    public function generate_image_callback() {
        // Clear the log file for this new request
        if ( file_exists( $this->log_file ) ) {
            unlink( $this->log_file );
        }
        $this->log_message('--- AJAX Request Start (Log file cleared) ---');
        
        check_ajax_referer( 'ai_featured_image_nonce', 'nonce' );
        $this->log_message('Nonce check passed.');

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'ai-featured-image' ) ) );
        }
        $this->log_message('User permission check passed.');

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $style   = isset( $_POST['style'] ) ? sanitize_text_field( $_POST['style'] ) : 'realistic';
        $quality = isset( $_POST['quality'] ) ? sanitize_text_field( $_POST['quality'] ) : 'standard';

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid Post ID.', 'ai-featured-image' ) ) );
        }
        $this->log_message("Post ID: $post_id");
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => __( 'Post not found.', 'ai-featured-image' ) ) );
        }
        $this->log_message('Post object found.');

        $options = get_option( 'ai_featured_image_options' );
        $api_key = ! empty( $options['api_key'] ) ? $options['api_key'] : '';

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'OpenAI API key is not set.', 'ai-featured-image' ) ) );
        }
        $this->log_message('API key found.');

        $image_model = ! empty( $options['image_model'] ) ? $options['image_model'] : 'gpt-image-1';
        $this->log_message("Image Model: $image_model");

        $prompt = sprintf(
            'A visually compelling, text-free %s image for a blog post titled "%s". The image should not contain any letters, words, or watermarks. The post content is about: %s',
            $style,
            $post->post_title,
            wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 50 ) )
        );
        $this->log_message("Generated Prompt: $prompt");

        $api_url = 'https://api.openai.com/v1/images/generations';
        $body    = array(
            'model'           => $image_model,
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => $options['image_dimensions'] ?? '1024x1024',
        );

        if ( 'dall-e-3' === $image_model ) {
            $body['quality'] = $quality;
            $body['style']   = $style;
        }
        $this->log_message('Request body prepared:');
        $this->log_message($body);

        $this->log_message('Calling wp_remote_post...');
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
        $this->log_message('wp_remote_post finished.');
        $this->log_message('Response object:');
        $this->log_message($response);

        if ( is_wp_error( $response ) ) {
            $this->log_message('Response is WP_Error: ' . $response->get_error_message());
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );
        
        $log_data_for_display = $data;
        if ( isset( $log_data_for_display['data'][0]['b64_json'] ) ) {
            $log_data_for_display['data'][0]['b64_json'] = "\n[b64_json data received, not fully logged to save space]\n";
        }
        $this->log_message('Decoded response body:');
        $this->log_message($log_data_for_display);

        if ( isset( $data['error'] ) ) {
            $this->log_message('API returned an error: ' . $data['error']['message']);
            wp_send_json_error( array( 'message' => $data['error']['message'] ) );
        }

        if ( empty( $data['data'][0]['b64_json'] ) ) {
            $this->log_message('API response did not contain b64_json data.');
            wp_send_json_error( array( 'message' => __( 'Could not retrieve image data from OpenAI.', 'ai-featured-image' ) ) );
        }
        
        $this->log_message('b64_json data found. Decoding and uploading.');
        
        $image_data = base64_decode( $data['data'][0]['b64_json'] );
        $post_title = sanitize_file_name( $post->post_title );
        $filename   = $post_title . '-' . time() . '.png';
        
        $upload = wp_upload_bits( $filename, null, $image_data );
        
        if ( ! empty( $upload['error'] ) ) {
            $this->log_message('wp_upload_bits failed: ' . $upload['error']);
            wp_send_json_error( array( 'message' => $upload['error'] ) );
        }

        $this->log_message('Image uploaded successfully. URL: ' . $upload['url']);

        // Create the attachment
        $attachment = array(
            'guid'           => $upload['url'],
            'post_mime_type' => $upload['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            $this->log_message('wp_insert_attachment failed: ' . $attachment_id->get_error_message());
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        // Generate attachment metadata
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $attachment_data );

        $this->log_message('Attachment created with ID: ' . $attachment_id);
        
        // We are sending back a custom structure now, not the direct OpenAI response
        $images_response = array(
            array(
                'url' => $upload['url'],
                'attachment_id' => $attachment_id
            )
        );

        wp_send_json_success( array( 'images' => $images_response ) );
    }

    /**
     * Sets the featured image for a post (used by Classic Editor).
     */
    public function set_featured_image_callback() {
        check_ajax_referer( 'ai_featured_image_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

        if ( ! $post_id || ! $attachment_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied or invalid data.', 'ai-featured-image' ) ) );
        }

        set_post_thumbnail( $post_id, $attachment_id );
        
        wp_send_json_success( array(
            'thumbnail_html' => get_the_post_thumbnail( $post_id, 'medium' )
        ) );
    }
} 