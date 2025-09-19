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
        // Schedule async generation on first publish
        add_action( 'transition_post_status', array( $this, 'maybe_schedule_on_publish' ), 10, 3 );
        add_action( 'ai_featured_image_generate_async', array( $this, 'handle_generate_async' ), 10, 1 );
        add_action( 'wp_ajax_generate_ai_post', array( $this, 'generate_ai_post_callback' ) );
    }

    private function build_prompt( $post ) {
        return sprintf(
            'Create a high-quality featured image for a blog post titled "%s". The content is about: %s. Do not include any text, captions, labels, watermarks, typography, or logos in the image (text-free image).',
            $post->post_title,
            wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 50 ) )
        );
    }

    private function log_line( $message, array $context = array() ) {
        $upload = wp_upload_dir();
        $path   = trailingslashit( $upload['basedir'] ) . 'ai-featured-image.log';
        $entry  = array( 'ts' => gmdate( 'c' ), 'message' => $message, 'context' => $context );
        $line   = wp_json_encode( $entry ) . PHP_EOL;
        // ensure dir exists
        if ( ! is_dir( $upload['basedir'] ) ) { @wp_mkdir_p( $upload['basedir'] ); }
        if ( ! @file_put_contents( $path, $line, FILE_APPEND | LOCK_EX ) ) {
            error_log( '[ai-featured-image] ' . $line );
        }
    }

    public function maybe_schedule_on_publish( $new_status, $old_status, $post ) {
        if ( 'publish' !== $new_status || 'publish' === $old_status ) return; // only first publish
        if ( 'post' !== $post->post_type ) return;

        $options = get_option( 'ai_featured_image_options' );
        if ( empty( $options['auto_on_publish'] ) ) return;
        $only_if_missing = isset( $options['auto_only_if_missing'] ) ? (bool) $options['auto_only_if_missing'] : true;
        if ( $only_if_missing && has_post_thumbnail( $post ) ) return;

        // Avoid duplicate scheduling
        $hook = 'ai_featured_image_generate_async';
        if ( ! wp_next_scheduled( $hook, array( $post->ID ) ) ) {
            wp_schedule_single_event( time() + 10, $hook, array( $post->ID ) );
            $this->log_line( 'scheduled_async_generation', array( 'post_id' => $post->ID ) );
        }
    }

    public function handle_generate_async( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || 'post' !== $post->post_type ) { $this->log_line( 'skip_async_invalid_post', array( 'post_id' => $post_id ) ); return; }
        // Skip if already has thumbnail
        if ( has_post_thumbnail( $post ) ) { $this->log_line( 'skip_async_has_thumbnail', array( 'post_id' => $post_id ) ); return; }

        $options = get_option( 'ai_featured_image_options' );
        $api_key = ! empty( $options['api_key'] ) ? $options['api_key'] : '';
        $size    = ! empty( $options['image_dimensions'] ) ? $options['image_dimensions'] : '1024x1024';
        if ( empty( $api_key ) ) { $this->log_line( 'skip_async_missing_key', array( 'post_id' => $post_id ) ); return; }

        $prompt  = $this->build_prompt( $post );
        $api_url = 'https://api.openai.com/v1/images/generations';
        $body    = array( 'model' => 'gpt-image-1', 'prompt' => $prompt, 'n' => 1, 'size' => $size );
        $this->log_line( 'async_request', array( 'post_id' => $post_id, 'body' => $body ) );

        $response = wp_remote_post( $api_url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 120,
        ) );
        if ( is_wp_error( $response ) ) { $this->log_line( 'async_transport_error', array( 'post_id' => $post_id, 'error' => $response->get_error_message() ) ); return; }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['error'] ) ) { $this->log_line( 'async_api_error', array( 'post_id' => $post_id, 'error' => $data['error'] ) ); return; }
        if ( empty( $data['data'][0] ) ) { $this->log_line( 'async_no_image', array( 'post_id' => $post_id ) ); return; }

        $url = isset( $data['data'][0]['url'] ) ? esc_url_raw( $data['data'][0]['url'] ) : '';
        $b64 = isset( $data['data'][0]['b64_json'] ) ? $data['data'][0]['b64_json'] : '';

        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        $attachment_id = 0;
        if ( $b64 ) {
            $tmp = wp_tempnam( 'ai-image' ); file_put_contents( $tmp, base64_decode( $b64 ) );
            $file_array = array( 'name' => 'ai-image.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'size' => filesize( $tmp ) );
            $attachment_id = media_handle_sideload( $file_array, $post_id, 'AI Generated Image' );
            @unlink( $tmp );
        } elseif ( $url ) {
            $attachment_id = media_sideload_image( $url, $post_id, 'AI Generated Image', 'id' );
        }
        if ( is_wp_error( $attachment_id ) || ! $attachment_id ) { $this->log_line( 'async_media_error', array( 'post_id' => $post_id, 'error' => is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'unknown' ) ); return; }
        set_post_thumbnail( $post_id, $attachment_id );
        $this->log_line( 'async_done', array( 'post_id' => $post_id, 'attachment_id' => $attachment_id ) );
    }

    public function generate_ai_post_callback() {
		check_ajax_referer( 'ai_featured_image_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'ai-featured-image' ) ) );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$length  = isset( $_POST['length'] ) ? sanitize_text_field( $_POST['length'] ) : '';
		$post = get_post( $post_id );
		if ( ! $post ) wp_send_json_error( array( 'message' => __( 'Post not found.', 'ai-featured-image' ) ) );

		$options = get_option( 'ai_featured_image_options' );
		$api_key = ! empty( $options['api_key'] ) ? $options['api_key'] : '';
		if ( empty( $api_key ) ) wp_send_json_error( array( 'message' => __( 'OpenAI API key is not set.', 'ai-featured-image' ) ) );

		if ( empty( $length ) ) $length = ( isset( $options['default_post_length'] ) ? $options['default_post_length'] : 'short' );
		$target_words = array( 'short'=>'300-500','medium'=>'800-1200','long'=>'1500-2000','verylong'=>'2500-3000' );
		$min_words    = array( 'short'=>300, 'medium'=>800, 'long'=>1500, 'verylong'=>2500 );
		$max_tokens   = array( 'short'=>1200, 'medium'=>2200, 'long'=>3200, 'verylong'=>4000 );
		$range = isset( $target_words[$length] ) ? $target_words[$length] : '300-500';
		$minw  = isset( $min_words[$length] ) ? intval( $min_words[$length] ) : 300;
		$maxt  = isset( $max_tokens[$length] ) ? intval( $max_tokens[$length] ) : 1200;

		$title = $post->post_title;
		$context = wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 80 ) );

		$system = 'You are a senior German SEO copywriter. Always respond in valid JSON when asked, and ensure content_html is clean HTML (h2/h3, p, ul/ol, strong/em). Do not include inline CSS or scripts.';
		$user   = sprintf(
			'Title: %s\nTarget length: %s words. Minimum length: %d words. Context: %s\nReturn a strict JSON object with keys: content_html (string), category_name (string), tags (array of 7-10 simple terms without commas).\nConstraints for content_html: write German; start with an introductory h2; include 6-10 well-structured sections (h2/h3) with paragraphs and lists where useful; end with a Schluss/Fazit section; avoid fluff; avoid code; no images; no footers; no author bios.',
			$title,
			$range,
			$minw,
			$context
		);

		$payload = array(
			'model' => 'gpt-4o',
			'messages' => array(
				array('role'=>'system','content'=>$system),
				array('role'=>'user','content'=>$user),
			),
			'temperature' => 0.7,
			'max_tokens'  => $maxt,
			'response_format' => array('type' => 'json_object'),
		);

		$this->log_line( 'ai_post_request', array( 'post_id' => $post_id, 'length' => $length, 'min_words'=>$minw, 'max_tokens'=>$maxt ) );

		$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array( 'Authorization'=>'Bearer '.$api_key, 'Content-Type'=>'application/json' ),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 180
		) );
		if ( is_wp_error( $resp ) ) {
			$this->log_line( 'ai_post_transport_error', array( 'post_id' => $post_id, 'error' => $resp->get_error_message() ) );
			wp_send_json_error( array( 'message' => $resp->get_error_message() ) );
		}
		$status = wp_remote_retrieve_response_code( $resp );
		$raw    = wp_remote_retrieve_body( $resp );
		$this->log_line( 'ai_post_response', array( 'post_id' => $post_id, 'status' => $status, 'body_excerpt' => mb_substr( $raw, 0, 600 ) ) );

		$data = json_decode( $raw, true );
		if ( isset( $data['error'] ) ) wp_send_json_error( array( 'message' => $data['error']['message'] ) );
		$content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';

		if ( is_string( $content ) ) {
			$trim = trim( $content );
			if ( ! $this->is_json_object( $trim ) ) {
				$posStart = strpos( $trim, '{' );
				$posEnd   = strrpos( $trim, '}' );
				if ( $posStart !== false && $posEnd !== false && $posEnd > $posStart ) {
					$trim = substr( $trim, $posStart, $posEnd - $posStart + 1 );
				}
				$content = $trim;
			}
		}

		$json = json_decode( $content, true );
		if ( ! is_array( $json ) || empty( $json['content_html'] ) ) {
			$this->log_line( 'ai_post_parse_error', array( 'post_id' => $post_id, 'content_excerpt' => mb_substr( (string) $content, 0, 600 ) ) );
			wp_send_json_error( array( 'message' => __( 'Model returned unexpected format.', 'ai-featured-image' ) ) );
		}

		$category_name = isset( $json['category_name'] ) ? sanitize_text_field( $json['category_name'] ) : '';
		$tags          = isset( $json['tags'] ) && is_array( $json['tags'] ) ? array_map( 'sanitize_text_field', $json['tags'] ) : array();

		$cat_id = 0;
		if ( $category_name ) {
			$exist = get_term_by( 'name', $category_name, 'category' );
			if ( $exist && ! is_wp_error( $exist ) ) { $cat_id = intval( $exist->term_id ); }
			else { $res = wp_insert_term( $category_name, 'category' ); if ( ! is_wp_error( $res ) ) $cat_id = intval( $res['term_id'] ); }
		}

		wp_send_json_success( array(
			'content_html' => wp_kses_post( $json['content_html'] ),
			'category_id'  => $cat_id,
			'category_name'=> $category_name,
			'tags'         => $tags,
		) );
	}

	private function is_json_object( $text ) {
		if ( ! is_string( $text ) ) return false;
		$text = trim( $text );
		if ( strlen( $text ) < 2 || $text[0] !== '{' || substr( $text, -1 ) !== '}' ) return false;
		json_decode( $text );
		return json_last_error() === JSON_ERROR_NONE;
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

        $prompt = $this->build_prompt( $post );

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