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
     * Prompt loader instance.
     *
     * @var AI_Featured_Image_Prompt_Loader
     */
    private $prompt_loader;

    /**
     * AI_Featured_Image_API_Connector constructor.
     */
    public function __construct() {
        // Initialize prompt loader
        $this->prompt_loader = new AI_Featured_Image_Prompt_Loader();

        // AJAX action for generating the image.
        add_action( 'wp_ajax_generate_ai_image', array( $this, 'generate_image_callback' ) );
        add_action( 'wp_ajax_upload_ai_image', array( $this, 'upload_image_callback' ) );
        // Schedule async generation on first publish
        add_action( 'transition_post_status', array( $this, 'maybe_schedule_on_publish' ), 10, 3 );
        add_action( 'ai_featured_image_generate_async', array( $this, 'handle_generate_async' ), 10, 1 );
        add_action( 'wp_ajax_generate_ai_post', array( $this, 'generate_ai_post_callback' ) );
        // Register REST API routes
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    private function build_prompt( $post ) {
        try {
            $variables = array(
                'post_title' => $post->post_title,
                'post_excerpt' => wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 50 ) ),
            );

            return $this->prompt_loader->get_prompt_by_slug( 'image-generation', null, $variables );
        } catch ( Exception $e ) {
            $this->log_line( 'prompt_error', array( 'slug' => 'image-generation', 'error' => $e->getMessage() ) );

            // Fallback to default if prompt not found (but this should cause an error per requirements)
            wp_die(
                esc_html( $e->getMessage() ) . '<br><br>' .
                '<a href="' . admin_url( 'post-new.php?post_type=ai_prompt' ) . '" class="button button-primary">' . __( 'Prompt erstellen', 'ai-featured-image' ) . '</a>',
                __( 'Prompt fehlt', 'ai-featured-image' ),
                array( 'back_link' => true )
            );
        }
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
        $api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : ( ! empty( $options['api_key'] ) ? $options['api_key'] : '' );
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
        $api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : ( ! empty( $options['api_key'] ) ? $options['api_key'] : '' );
		if ( empty( $api_key ) ) wp_send_json_error( array( 'message' => __( 'OpenAI API key is not set.', 'ai-featured-image' ) ) );

		if ( empty( $length ) ) $length = ( isset( $options['default_post_length'] ) ? $options['default_post_length'] : 'short' );
		$target_words = array( 'short'=>'300-500','medium'=>'800-1200','long'=>'1500-2000','verylong'=>'2500-3000' );
		$min_words    = array( 'short'=>300, 'medium'=>800, 'long'=>1500, 'verylong'=>2500 );
		$max_words    = array( 'short'=>500, 'medium'=>1200, 'long'=>2000, 'verylong'=>3000 );
		// Increased max_tokens to ensure GPT has enough space for word count requirements
		$max_tokens   = array( 'short'=>1800, 'medium'=>3500, 'long'=>5000, 'verylong'=>8000 );
		$range = isset( $target_words[$length] ) ? $target_words[$length] : '300-500';
		$minw  = isset( $min_words[$length] ) ? intval( $min_words[$length] ) : 300;
		$maxw  = isset( $max_words[$length] ) ? intval( $max_words[$length] ) : 500;
		$maxt  = isset( $max_tokens[$length] ) ? intval( $max_tokens[$length] ) : 1800;

		$title = $post->post_title;
		$context = wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 80 ) );

		// Load prompts from CPT
		try {
			$system = $this->prompt_loader->get_prompt_by_slug( 'system-post-generation' );
			$system_config = $this->prompt_loader->get_prompt_config( 'system-post-generation' );
		} catch ( Exception $e ) {
			$this->log_line( 'prompt_error', array( 'slug' => 'system-post-generation', 'error' => $e->getMessage() ) );
			wp_send_json_error( array(
				'message' => $e->getMessage() . ' <a href="' . admin_url( 'post-new.php?post_type=ai_prompt' ) . '">' . __( 'Prompt erstellen', 'ai-featured-image' ) . '</a>'
			) );
		}
		
		// Replace editorial variables in system prompt
		$editorial_line = isset( $options['editorial_line'] ) && ! empty( $options['editorial_line'] ) ? $options['editorial_line'] : 'Keine Blattlinie definiert';
		$author_style = isset( $options['author_style'] ) && ! empty( $options['author_style'] ) ? $options['author_style'] : 'Neutral, sachlich';
		$target_audience = isset( $options['target_audience'] ) && ! empty( $options['target_audience'] ) ? $options['target_audience'] : 'Allgemeine Leserschaft';
		
		$system = str_replace( '{editorial_line}', $editorial_line, $system );
		$system = str_replace( '{author_style}', $author_style, $system );
		$system = str_replace( '{target_audience}', $target_audience, $system );
		
		// Load user prompt from CPT with variables
		try {
			$variables = array(
				'post_title' => $title,
				'post_excerpt' => $context,
				'min_words' => $minw,
				'max_words' => $maxw,
				'length' => $length,
			);

			$user = $this->prompt_loader->get_prompt_by_slug( 'post-generation', $length, $variables );
			$user_config = $this->prompt_loader->get_prompt_config( 'post-generation' );
		} catch ( Exception $e ) {
			$this->log_line( 'prompt_error', array( 'slug' => 'post-generation', 'error' => $e->getMessage() ) );
			wp_send_json_error( array(
				'message' => $e->getMessage() . ' <a href="' . admin_url( 'post-new.php?post_type=ai_prompt' ) . '">' . __( 'Prompt erstellen', 'ai-featured-image' ) . '</a>'
			) );
		}

		// Use config from prompt or fallback to system config
		$model = ! empty( $user_config['model'] ) ? $user_config['model'] : $system_config['model'];
		$temperature = isset( $user_config['temperature'] ) ? $user_config['temperature'] : $system_config['temperature'];
		$response_format = ! empty( $user_config['response_format'] ) ? $user_config['response_format'] : $system_config['response_format'];
		
		// Use max_tokens from config if set, otherwise use length-based defaults
		if ( ! empty( $user_config['max_tokens'] ) ) {
			$maxt = $user_config['max_tokens'];
		}

		$this->log_line( 'ai_post_request', array( 'post_id' => $post_id, 'length' => $length, 'min_words'=>$minw, 'max_tokens'=>$maxt ) );

		$response = $this->perform_chat_completion( $model, $system, $user, $maxt, $temperature, $response_format, $api_key );
		if ( isset( $response['error'] ) && is_wp_error( $response['error'] ) ) {
			$this->log_line( 'ai_post_transport_error', array( 'post_id' => $post_id, 'error' => $response['error']->get_error_message() ) );
			wp_send_json_error( array( 'message' => $response['error']->get_error_message() ) );
		}
		$status = $response['status'];
		$raw    = $response['raw'];
		$this->log_line( 'ai_post_response', array( 'post_id' => $post_id, 'status' => $status, 'body_excerpt' => mb_substr( (string) $raw, 0, 600 ) ) );

		$data = $response['data'];
		if ( isset( $data['error'] ) ) wp_send_json_error( array( 'message' => $data['error']['message'] ) );
		$content = is_string( $response['content'] ) ? $response['content'] : '';

		$json = json_decode( $content, true );
		if ( ! is_array( $json ) || empty( $json['content_html'] ) ) {
			// Log the actual content for debugging
			error_log( '=== AI Post Parse Error ===' );
			error_log( 'Post ID: ' . $post_id );
			error_log( 'Content length: ' . strlen( $content ) );
			error_log( 'Content preview: ' . mb_substr( (string) $content, 0, 1000 ) );
			error_log( 'JSON decode error: ' . json_last_error_msg() );
			
			$this->log_line( 'ai_post_parse_error', array( 
				'post_id' => $post_id, 
				'content_excerpt' => mb_substr( (string) $content, 0, 600 ),
				'json_error' => json_last_error_msg(),
				'content_length' => strlen( $content )
			) );
			
			wp_send_json_error( array( 
				'message' => __( 'Model returned unexpected format.', 'ai-featured-image' ) . ' JSON Error: ' . json_last_error_msg()
			) );
		}

		$category_name = isset( $json['category_name'] ) ? sanitize_text_field( $json['category_name'] ) : '';
		$tags          = isset( $json['tags'] ) && is_array( $json['tags'] ) ? array_map( 'sanitize_text_field', $json['tags'] ) : array();

		$cat_id = 0;
		if ( $category_name ) {
			$exist = get_term_by( 'name', $category_name, 'category' );
			if ( $exist && ! is_wp_error( $exist ) ) { $cat_id = intval( $exist->term_id ); }
			else { $res = wp_insert_term( $category_name, 'category' ); if ( ! is_wp_error( $res ) ) $cat_id = intval( $res['term_id'] ); }
			
			// Set category for the post
			if ( $cat_id > 0 ) {
				wp_set_post_categories( $post_id, array( $cat_id ), false );
			}
		}
		
		// Count words
		$word_count = str_word_count( wp_strip_all_tags( $json['content_html'] ) );
		
		// Get prompt IDs for debug links
		$system_prompt_id = $this->prompt_loader->get_prompt_id( 'system-post-generation' );
		$user_prompt_id = $this->prompt_loader->get_prompt_id( 'post-generation' );
		
		// Save debug information
		$debug_info = array(
			'timestamp' => current_time( 'mysql' ),
			'initial_generation' => array(
				'request' => array(
					'model' => $model,
					'temperature' => ( strpos( $model, 'gpt-5' ) === false && strpos( $model, 'o1' ) === false ) ? $temperature : 1,
					'max_tokens' => $maxt,
					'response_format' => $response_format,
					'system_prompt' => mb_substr( $system, 0, 500 ) . '...',
					'system_prompt_full' => $system,
					'system_prompt_id' => $system_prompt_id,
					'system_prompt_edit_link' => $system_prompt_id ? admin_url( 'post.php?post=' . $system_prompt_id . '&action=edit' ) : null,
					'user_prompt' => mb_substr( $user, 0, 500 ) . '...',
					'user_prompt_full' => $user,
					'user_prompt_id' => $user_prompt_id,
					'user_prompt_slug' => 'post-generation',
					'user_prompt_variant' => $length,
					'user_prompt_edit_link' => $user_prompt_id ? admin_url( 'post.php?post=' . $user_prompt_id . '&action=edit' ) : null,
				),
				'response' => array(
					'status' => $status,
					'word_count' => $word_count,
					'content_preview' => mb_substr( $json['content_html'], 0, 500 ) . '...',
				),
			),
		);
		
		update_post_meta( $post_id, '_ai_debug_log', wp_json_encode( $debug_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
		update_post_meta( $post_id, '_ai_generation_summary', array(
			'timestamp' => current_time( 'mysql' ),
			'model' => $model,
			'length' => $length,
			'word_count' => $word_count,
			'corrections' => 0,
			'status' => 'generated',
			'target_range' => $minw . '-' . $maxw,
		) );

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
     * Perform a chat completion request and normalize the response.
     *
     * @param string $model
     * @param string $system System prompt
     * @param string $user   User prompt
     * @param int    $max_tokens Max completion tokens
     * @param float|int $temperature Temperature (ignored for GPT-5 family)
     * @param string $response_format 'text' or 'json_object'
     * @param string $api_key OpenAI API key
     * @return array Array with keys: status, raw, data (decoded JSON), content (string)
     */
    private function perform_chat_completion( $model, $system, $user, $max_tokens, $temperature, $response_format, $api_key ) {
        $payload = array(
            'model' => $model,
            'messages' => array(
                array('role' => 'system', 'content' => $system),
                array('role' => 'user',   'content' => $user),
            ),
            'max_completion_tokens' => $max_tokens,
        );

        // GPT-5 family models only support temperature=1 (default)
        if ( strpos( $model, 'gpt-5' ) === false && strpos( $model, 'o1' ) === false ) {
            $payload['temperature'] = $temperature;
        }

        if ( $response_format === 'json_object' || $response_format === 'json' ) {
            $payload['response_format'] = array( 'type' => 'json_object' );
        }

        $resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 180,
        ) );

        if ( is_wp_error( $resp ) ) {
            return array( 'status' => 0, 'raw' => '', 'data' => null, 'content' => '', 'error' => $resp );
        }

        $status = wp_remote_retrieve_response_code( $resp );
        $raw    = wp_remote_retrieve_body( $resp );
        $data   = json_decode( $raw, true );
        $content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';

        // Extract braces if model returned text around JSON
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

        return compact( 'status', 'raw', 'data', 'content' );
    }

	/**
	 * Count words in HTML content.
	 * Strips HTML tags and counts German words.
	 *
	 * @param string $html HTML content to count words in.
	 * @return int Word count.
	 */
	private function count_html_words( $html ) {
		$text = wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );
		if ( empty( $text ) ) return 0;
		return count( preg_split( '/\s+/u', $text ) );
	}

	/**
	 * Validate if word count is within acceptable range.
	 *
	 * @param int $word_count Actual word count.
	 * @param int $min_words Minimum required words.
	 * @param int $max_words Maximum allowed words.
	 * @return array Array with 'valid' boolean and 'message' string.
	 */
	private function validate_word_count( $word_count, $min_words, $max_words ) {
		// Allow 10% tolerance for flexibility
		$tolerance_low = (int) ( $min_words * 0.9 );
		$tolerance_high = (int) ( $max_words * 1.1 );
		
		if ( $word_count < $tolerance_low ) {
			return array(
				'valid' => false,
				'message' => sprintf( 'Content too short: %d words (min: %d)', $word_count, $min_words ),
				'direction' => 'expand'
			);
		}
		
		if ( $word_count > $tolerance_high ) {
			return array(
				'valid' => false,
				'message' => sprintf( 'Content too long: %d words (max: %d)', $word_count, $max_words ),
				'direction' => 'shorten'
			);
		}
		
		return array(
			'valid' => true,
			'message' => sprintf( 'Word count valid: %d words (target: %d-%d)', $word_count, $min_words, $max_words )
		);
	}

	/**
	 * Generate correction prompt for GPT to adjust content length.
	 *
	 * @param string $content Original content HTML.
	 * @param int $current_words Current word count.
	 * @param int $min_words Target minimum words.
	 * @param int $max_words Target maximum words.
	 * @param string $direction Either 'expand' or 'shorten'.
	 * @return string Correction prompt for GPT.
	 */
	private function build_correction_prompt( $content, $current_words, $min_words, $max_words, $direction ) {
		$target = (int) ( ( $min_words + $max_words ) / 2 );
		$diff = abs( $target - $current_words );
		
		if ( $direction === 'expand' ) {
			return sprintf(
				'Der folgende Artikel hat nur %d Wörter, braucht aber %d-%d Wörter (ideal: %d Wörter).

WICHTIG: Erweitere den Inhalt um etwa %d Wörter, indem du:
1. Bestehende Abschnitte mit mehr Details und Beispielen erweiterst
2. Tiefergehende Erklärungen hinzufügst
3. Die Struktur und alle HTML-Tags beibehältst
4. NICHT neue Abschnitte hinzufügst, sondern bestehende ausbaust

Aktueller Artikel:
%s

Antworte NUR mit dem erweiterten HTML-Inhalt (keine JSON, kein zusätzlicher Text). Ziel: %d-%d Wörter.',
				$current_words,
				$min_words,
				$max_words,
				$target,
				$diff,
				$content,
				$min_words,
				$max_words
			);
		} else {
			return sprintf(
				'Der folgende Artikel hat %d Wörter, darf aber nur %d-%d Wörter haben (ideal: %d Wörter).

WICHTIG: Kürze den Inhalt um etwa %d Wörter, indem du:
1. Redundante Informationen entfernst
2. Absätze prägnanter formulierst
3. Die Kernaussagen und Struktur beibehältst
4. Alle wichtigen Informationen erhältst
5. Die HTML-Struktur beibehältst

Aktueller Artikel:
%s

Antworte NUR mit dem gekürzten HTML-Inhalt (keine JSON, kein zusätzlicher Text). Ziel: %d-%d Wörter.',
				$current_words,
				$min_words,
				$max_words,
				$target,
				$diff,
				$content,
				$min_words,
				$max_words
			);
		}
	}

	/**
	 * Correct content length using GPT.
	 *
	 * @param string $content HTML content to correct.
	 * @param int $min_words Target minimum words.
	 * @param int $max_words Target maximum words.
	 * @param string $direction Either 'expand' or 'shorten'.
	 * @param string $api_key OpenAI API key.
	 * @return array|WP_Error Corrected content or error.
	 */
	private function correct_content_length( $content, $min_words, $max_words, $direction, $api_key, &$debug_info = null ) {
		$current_words = $this->count_html_words( $content );
		
		$this->log_line( 'length_correction_request', array(
			'current_words' => $current_words,
			'target_range' => "$min_words-$max_words",
			'direction' => $direction
		) );

		// Load prompts from CPT
		try {
			$system = $this->prompt_loader->get_prompt_by_slug( 'system-correction' );
			$system_config = $this->prompt_loader->get_prompt_config( 'system-correction' );

			$slug = $direction === 'expand' ? 'correction-expand' : 'correction-shorten';
			
			$variables = array(
				'post_content' => $content,
				'current_words' => $current_words,
				'min_words' => $min_words,
				'max_words' => $max_words,
			);

			$prompt = $this->prompt_loader->get_prompt_by_slug( $slug, null, $variables );
			$prompt_config = $this->prompt_loader->get_prompt_config( $slug );
		} catch ( Exception $e ) {
			$this->log_line( 'prompt_error', array( 'slug' => $slug, 'error' => $e->getMessage() ) );
			return new WP_Error( 'prompt_missing', $e->getMessage() );
		}
		
		// Replace editorial variables in system prompt
		$options = get_option( 'ai_featured_image_options' );
		$editorial_line = isset( $options['editorial_line'] ) && ! empty( $options['editorial_line'] ) ? $options['editorial_line'] : 'Keine Blattlinie definiert';
		$author_style = isset( $options['author_style'] ) && ! empty( $options['author_style'] ) ? $options['author_style'] : 'Neutral, sachlich';
		$target_audience = isset( $options['target_audience'] ) && ! empty( $options['target_audience'] ) ? $options['target_audience'] : 'Allgemeine Leserschaft';
		
		$system = str_replace( '{editorial_line}', $editorial_line, $system );
		$system = str_replace( '{author_style}', $author_style, $system );
		$system = str_replace( '{target_audience}', $target_audience, $system );

		// Use config from prompts
		$model = ! empty( $prompt_config['model'] ) ? $prompt_config['model'] : $system_config['model'];
		$temperature = isset( $prompt_config['temperature'] ) ? $prompt_config['temperature'] : $system_config['temperature'];
		$max_tokens = ! empty( $prompt_config['max_tokens'] ) ? $prompt_config['max_tokens'] : 6000;
		
		$payload = array(
			'model' => $model,
			'messages' => array(
				array(
					'role' => 'system',
					'content' => $system
				),
				array(
					'role' => 'user',
					'content' => $prompt
				)
			),
			'max_completion_tokens' => $max_tokens,
		);

		// GPT-5 family models only support temperature=1 (default), so we skip temperature parameter
		if ( strpos( $model, 'gpt-5' ) === false && strpos( $model, 'o1' ) === false ) {
			$payload['temperature'] = $temperature;
		}
		
		$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode( $payload ),
			'timeout' => 180
		) );
		
		if ( is_wp_error( $resp ) ) {
			$this->log_line( 'length_correction_error', array( 'error' => $resp->get_error_message() ) );
			return $resp;
		}
		
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( isset( $data['error'] ) ) {
			$this->log_line( 'length_correction_api_error', array( 'error' => $data['error'] ) );
			return new WP_Error( 'api_error', $data['error']['message'] );
		}
		
		$corrected_content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
		$corrected_content = trim( $corrected_content );
		
		// Clean up if GPT added markdown code blocks
		$corrected_content = preg_replace( '/^```html\s*/i', '', $corrected_content );
		$corrected_content = preg_replace( '/\s*```$/i', '', $corrected_content );
		$corrected_content = trim( $corrected_content );
		
		$new_word_count = $this->count_html_words( $corrected_content );
		
		$this->log_line( 'length_correction_result', array(
			'before' => $current_words,
			'after' => $new_word_count,
			'target' => "$min_words-$max_words"
		) );
		
		// Add debug info if provided
		if ( is_array( $debug_info ) ) {
			$system_prompt_id = $this->prompt_loader->get_prompt_id( 'system-correction' );
			$correction_prompt_id = $this->prompt_loader->get_prompt_id( $slug );
			
			// Check if temperature was actually used (not for GPT-5 family models)
			$temp_display = ( strpos( $model, 'gpt-5' ) === false && strpos( $model, 'o1' ) === false ) ? $temperature : '1 (default)';
			
			$debug_info['corrections'][] = array(
				'direction' => $direction,
				'request' => array(
					'model' => $model,
					'temperature' => $temp_display,
					'max_tokens' => $max_tokens,
					'system_prompt' => substr( $system, 0, 300 ) . '...',
					'system_prompt_id' => $system_prompt_id,
					'system_prompt_edit_link' => $system_prompt_id ? admin_url( 'post.php?post=' . $system_prompt_id . '&action=edit' ) : null,
					'user_prompt' => substr( $prompt, 0, 300 ) . '...',
					'user_prompt_id' => $correction_prompt_id,
					'user_prompt_edit_link' => $correction_prompt_id ? admin_url( 'post.php?post=' . $correction_prompt_id . '&action=edit' ) : null,
					'user_prompt_slug' => $slug,
					'current_words' => $current_words,
				),
				'response' => array(
					'content_preview' => substr( $corrected_content, 0, 500 ) . '...',
					'new_word_count' => $new_word_count,
					'usage' => isset( $data['usage'] ) ? $data['usage'] : array(),
					'model' => isset( $data['model'] ) ? $data['model'] : '',
				)
			);
		}
		
		return array(
			'content' => $corrected_content,
			'word_count' => $new_word_count
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route( 'ai-featured-image/v1', '/generate-post', array(
			'methods' => 'POST',
			'callback' => array( $this, 'rest_generate_post' ),
			'permission_callback' => array( $this, 'rest_permission_check' ),
			'args' => array(
				'post_id' => array(
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => function( $value ) { return absint( $value ); },
					'validate_callback' => function( $param ) { return is_numeric( $param ) && $param > 0; }
				),
				'length' => array(
					'required' => false,
					'type' => 'string',
					'default' => 'short',
					'enum' => array( 'short', 'medium', 'long', 'verylong' ),
					'sanitize_callback' => function( $value ) { return sanitize_text_field( $value ); }
				),
				'auto_correct' => array(
					'required' => false,
					'type' => 'boolean',
					'default' => true,
					'sanitize_callback' => function( $value ) { return (bool) $value; }
				),
				'max_corrections' => array(
					'required' => false,
					'type' => 'integer',
					'default' => 2,
					'minimum' => 0,
					'maximum' => 3,
					'sanitize_callback' => function( $value ) { $v = absint( $value ); return min( max( $v, 0 ), 3 ); },
					'validate_callback' => function( $param ) { return is_numeric( $param ); }
				)
			)
		) );
	}

	/**
	 * Permission check for REST API endpoints.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if user has permission, error otherwise.
	 */
	public function rest_permission_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'ai-featured-image' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * REST API callback to generate AI post with automatic length correction.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function rest_generate_post( $request ) {
		$post_id = $request->get_param( 'post_id' );
		$length = $request->get_param( 'length' );
		$auto_correct = $request->get_param( 'auto_correct' );
		$max_corrections = $request->get_param( 'max_corrections' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				__( 'Post not found.', 'ai-featured-image' ),
				array( 'status' => 404 )
			);
		}

        $options = get_option( 'ai_featured_image_options' );
        $api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : ( ! empty( $options['api_key'] ) ? $options['api_key'] : '' );
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'api_key_missing',
				__( 'OpenAI API key is not set.', 'ai-featured-image' ),
				array( 'status' => 400 )
			);
		}

		// Define length configurations
		$target_words = array( 'short'=>'300-500','medium'=>'800-1200','long'=>'1500-2000','verylong'=>'2500-3000' );
		$min_words    = array( 'short'=>300, 'medium'=>800, 'long'=>1500, 'verylong'=>2500 );
		$max_words    = array( 'short'=>500, 'medium'=>1200, 'long'=>2000, 'verylong'=>3000 );
		$max_tokens   = array( 'short'=>1800, 'medium'=>3500, 'long'=>5000, 'verylong'=>8000 );

		$minw = isset( $min_words[$length] ) ? intval( $min_words[$length] ) : 300;
		$maxw = isset( $max_words[$length] ) ? intval( $max_words[$length] ) : 500;
		$maxt = isset( $max_tokens[$length] ) ? intval( $max_tokens[$length] ) : 1800;

		$title = $post->post_title;
		$context = wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 80 ) );

		// Load prompts from CPT
		try {
			$system_prompt = $this->prompt_loader->get_prompt_by_slug( 'system-post-generation' );
			$system_config = $this->prompt_loader->get_prompt_config( 'system-post-generation' );

			$variables = array(
				'post_title' => $title,
				'post_excerpt' => $context,
				'min_words' => $minw,
				'max_words' => $maxw,
			);

			$user_prompt = $this->prompt_loader->get_prompt_by_slug( 'post-generation', $length, $variables );
			$user_config = $this->prompt_loader->get_prompt_config( 'post-generation' );
		} catch ( Exception $e ) {
			$this->log_line( 'rest_prompt_error', array( 'error' => $e->getMessage() ) );
			return new WP_Error( 'prompt_missing', $e->getMessage(), array( 'status' => 500 ) );
		}
		
		// Replace editorial variables in system prompt
		$editorial_line = isset( $options['editorial_line'] ) && ! empty( $options['editorial_line'] ) ? $options['editorial_line'] : 'Keine Blattlinie definiert';
		$author_style = isset( $options['author_style'] ) && ! empty( $options['author_style'] ) ? $options['author_style'] : 'Neutral, sachlich';
		$target_audience = isset( $options['target_audience'] ) && ! empty( $options['target_audience'] ) ? $options['target_audience'] : 'Allgemeine Leserschaft';
		
		$system_prompt = str_replace( '{editorial_line}', $editorial_line, $system_prompt );
		$system_prompt = str_replace( '{author_style}', $author_style, $system_prompt );
		$system_prompt = str_replace( '{target_audience}', $target_audience, $system_prompt );

		// Use config from prompts
		$model = ! empty( $user_config['model'] ) ? $user_config['model'] : $system_config['model'];
		$temperature = isset( $user_config['temperature'] ) ? $user_config['temperature'] : $system_config['temperature'];
		$response_format = ! empty( $user_config['response_format'] ) ? $user_config['response_format'] : 'json_object';
		$max_tokens_prompt = ! empty( $user_config['max_tokens'] ) ? $user_config['max_tokens'] : $maxt;

		// Get prompt IDs for debug links
		$system_prompt_id = $this->prompt_loader->get_prompt_id( 'system-post-generation' );
		$user_prompt_id = $this->prompt_loader->get_prompt_id( 'post-generation' );

		$system = $system_prompt;
		$user = $user_prompt;

		// Initial generation with CPT prompt settings
		$this->log_line( 'rest_api_post_request', array( 
			'post_id' => $post_id, 
			'length' => $length, 
			'min_words' => $minw,
			'max_words' => $maxw,
			'auto_correct' => $auto_correct
		) );

		$response = $this->perform_chat_completion( $model, $system, $user, $max_tokens_prompt, $temperature, $response_format, $api_key );

		if ( isset( $response['error'] ) && is_wp_error( $response['error'] ) ) {
			$this->log_line( 'rest_api_transport_error', array( 'post_id' => $post_id, 'error' => $response['error']->get_error_message() ) );
			return $response['error'];
		}

		$data = $response['data'];
		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'api_error', $data['error']['message'], array( 'status' => 500 ) );
		}

		$content = isset( $response['content'] ) ? $response['content'] : '';

		// Parse JSON response
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
			$this->log_line( 'rest_api_parse_error', array( 
				'post_id' => $post_id,
				'json_error' => json_last_error_msg()
			) );
			return new WP_Error( 'parse_error', 'Model returned unexpected format: ' . json_last_error_msg(), array( 'status' => 500 ) );
		}

		$content_html = $json['content_html'];
		$category_name = isset( $json['category_name'] ) ? sanitize_text_field( $json['category_name'] ) : '';
		$tags = isset( $json['tags'] ) && is_array( $json['tags'] ) ? array_map( 'sanitize_text_field', $json['tags'] ) : array();

		// Count words in generated content
		$initial_word_count = $this->count_html_words( $content_html );
		$validation = $this->validate_word_count( $initial_word_count, $minw, $maxw );

		$corrections_made = 0;
		$correction_history = array();
		$debug_info = array(
			'initial_generation' => array(
				'request' => array(
					'model' => $model,
					'temperature' => ( strpos( $model, 'gpt-5' ) === false && strpos( $model, 'o1' ) === false ) ? $temperature : '1 (default)',
					'max_tokens' => $max_tokens_prompt,
					'response_format' => ( $response_format === 'json_object' || $response_format === 'json' ) ? 'json_object' : 'text',
					'system_prompt' => substr( $system, 0, 500 ) . '...',
					'system_prompt_full' => $system,
					'system_prompt_id' => $system_prompt_id,
					'system_prompt_edit_link' => $system_prompt_id ? admin_url( 'post.php?post=' . $system_prompt_id . '&action=edit' ) : null,
					'user_prompt' => substr( $user, 0, 500 ) . '...',
					'user_prompt_full' => $user,
					'user_prompt_id' => $user_prompt_id,
					'user_prompt_edit_link' => $user_prompt_id ? admin_url( 'post.php?post=' . $user_prompt_id . '&action=edit' ) : null,
					'user_prompt_variant' => $length,
				),
				'response' => array(
					'raw_content' => substr( $content, 0, 1000 ) . '...',
					'usage' => isset( $data['usage'] ) ? $data['usage'] : array(),
					'model' => isset( $data['model'] ) ? $data['model'] : '',
				)
			),
			'corrections' => array()
		);

		// Automatic length correction loop
		if ( $auto_correct && ! $validation['valid'] && $max_corrections > 0 ) {
			$current_content = $content_html;
			
			while ( $corrections_made < $max_corrections ) {
				$current_word_count = $this->count_html_words( $current_content );
				$check = $this->validate_word_count( $current_word_count, $minw, $maxw );
				
				if ( $check['valid'] ) {
					break; // Content is now valid
				}
				
				$this->log_line( 'rest_api_correction_attempt', array(
					'post_id' => $post_id,
					'attempt' => $corrections_made + 1,
					'current_words' => $current_word_count,
					'direction' => $check['direction']
				) );
				
				$correction_result = $this->correct_content_length(
					$current_content,
					$minw,
					$maxw,
					$check['direction'],
					$api_key,
					$debug_info
				);
				
				if ( is_wp_error( $correction_result ) ) {
					$this->log_line( 'rest_api_correction_failed', array(
						'post_id' => $post_id,
						'attempt' => $corrections_made + 1,
						'error' => $correction_result->get_error_message()
					) );
					break; // Stop corrections on error
				}
				
				$corrections_made++;
				$correction_history[] = array(
					'attempt' => $corrections_made,
					'before_words' => $current_word_count,
					'after_words' => $correction_result['word_count'],
					'direction' => $check['direction']
				);
				
				$current_content = $correction_result['content'];
				
				// Update validation for next iteration
				$validation = $this->validate_word_count( $correction_result['word_count'], $minw, $maxw );
			}
			
			// Use corrected content if corrections were made
			if ( $corrections_made > 0 ) {
				$content_html = $current_content;
			}
		}

		$final_word_count = $this->count_html_words( $content_html );
		$final_validation = $this->validate_word_count( $final_word_count, $minw, $maxw );

		// Process category
		$cat_id = 0;
		if ( $category_name ) {
			$exist = get_term_by( 'name', $category_name, 'category' );
			if ( $exist && ! is_wp_error( $exist ) ) {
				$cat_id = intval( $exist->term_id );
			} else {
				$res = wp_insert_term( $category_name, 'category' );
				if ( ! is_wp_error( $res ) ) {
					$cat_id = intval( $res['term_id'] );
				}
			}
		}

		$this->log_line( 'rest_api_post_complete', array(
			'post_id' => $post_id,
			'initial_words' => $initial_word_count,
			'final_words' => $final_word_count,
			'corrections_made' => $corrections_made,
			'valid' => $final_validation['valid']
		) );

		// Save debug log and summary as custom fields
		update_post_meta( $post_id, '_ai_debug_log', wp_json_encode( $debug_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
		update_post_meta( $post_id, '_ai_generation_summary', array(
			'timestamp' => current_time( 'mysql' ),
			'model' => $model,
			'length' => $length,
			'word_count' => $final_word_count,
			'corrections' => $corrections_made,
			'status' => $final_validation['valid'] ? 'valid' : 'invalid',
			'target_range' => "$minw-$maxw"
		) );

		return new WP_REST_Response( array(
			'success' => true,
			'data' => array(
				'content_html' => wp_kses_post( $content_html ),
				'category_id' => $cat_id,
				'category_name' => $category_name,
				'tags' => $tags,
				'word_count' => array(
					'initial' => $initial_word_count,
					'final' => $final_word_count,
					'target_min' => $minw,
					'target_max' => $maxw,
					'valid' => $final_validation['valid'],
					'message' => $final_validation['message']
				),
				'corrections' => array(
					'enabled' => $auto_correct,
					'made' => $corrections_made,
					'max_allowed' => $max_corrections,
					'history' => $correction_history
				),
				'debug' => $debug_info
			)
		), 200 );
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
        $api_key = defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : ( ! empty( $options['api_key'] ) ? $options['api_key'] : '' );
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
        // Raise memory limit for image processing if possible
        if ( function_exists( 'wp_raise_memory_limit' ) ) {
            wp_raise_memory_limit( 'image' );
        }
        // Increase execution time if allowed (avoid warnings when disabled)
        $disabled = ini_get( 'disable_functions' );
        $set_time_limit_disabled = is_string( $disabled ) && strpos( $disabled, 'set_time_limit' ) !== false;
        if ( ! $set_time_limit_disabled && function_exists( 'set_time_limit' ) ) {
            set_time_limit( 180 );
        }
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