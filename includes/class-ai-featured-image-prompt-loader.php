<?php
/**
 * Prompt Loader for AI Featured Image Plugin.
 * Handles loading, caching and variable replacement for prompts.
 *
 * @package AI_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI_Featured_Image_Prompt_Loader
 */
class AI_Featured_Image_Prompt_Loader {
	/**
	 * Cache group name.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'ai_prompts';

	/**
	 * Cache expiration (24 hours).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 86400;

	/**
	 * Get prompt by slug.
	 *
	 * @param string $slug Prompt slug.
	 * @param string $variant Optional variant name.
	 * @param array  $variables Optional variables for replacement.
	 * @return string Prompt text.
	 * @throws Exception If prompt not found.
	 */
	public function get_prompt_by_slug( $slug, $variant = null, $variables = array() ) {
		$cache_key = 'ai_prompt_' . $slug;
		
		// Try to get from cache
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		
		if ( false === $cached ) {
			// Not in cache, load from database
			$posts = get_posts( array(
				'post_type' => 'ai_prompt',
				'meta_query' => array(
					array(
						'key' => '_prompt_slug',
						'value' => $slug,
					),
					array(
						'key' => '_is_active',
						'value' => '1',
					),
				),
				'posts_per_page' => 1,
			) );

			if ( empty( $posts ) ) {
				$this->log_missing_prompt( $slug );
				throw new Exception( sprintf(
					__( 'Prompt "%s" nicht gefunden. Bitte in AI Prompts → Hinzufügen konfigurieren.', 'ai-featured-image' ),
					$slug
				) );
			}

			$cached = $posts[0];
			wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		$prompt_content = $cached->post_content;

		// If variant requested, get from variants JSON
		if ( $variant ) {
			$variants = get_post_meta( $cached->ID, '_prompt_variants', true );
			
			// DEBUG: Log what was loaded
			$variants_preview = is_array( $variants ) 
				? 'Array with keys: ' . implode( ', ', array_keys( $variants ) ) 
				: ( $variants ? substr( $variants, 0, 200 ) . '...' : 'EMPTY' );
				
			error_log( sprintf(
				'[AI Prompt Loader] Loading variant "%s" for slug "%s". Variants data: %s',
				$variant,
				$slug,
				$variants_preview
			) );
			
			if ( $variants ) {
				// Handle both array (WordPress serialized) and JSON string
				if ( is_array( $variants ) ) {
					$variants_array = $variants;
				} else {
					$variants_array = json_decode( $variants, true );
					
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						error_log( '[AI Prompt Loader] JSON decode error: ' . json_last_error_msg() );
					}
				}
				
				if ( isset( $variants_array[ $variant ] ) ) {
					$prompt_content = $variants_array[ $variant ];
					error_log( sprintf(
						'[AI Prompt Loader] Variant "%s" loaded successfully. Length: %d chars',
						$variant,
						strlen( $prompt_content )
					) );
				} else {
					// WICHTIG: Fallback mit Warnung
					$available = is_array( $variants_array ) ? array_keys( $variants_array ) : array();
					error_log( sprintf(
						'[AI Prompt Loader] WARNING: Variant "%s" not found in variants. Available: %s',
						$variant,
						implode( ', ', $available )
					) );
				}
			}
		}

		// Warnung wenn Prompt leer
		if ( empty( $prompt_content ) ) {
			error_log( sprintf(
				'[AI Prompt Loader] WARNING: Empty prompt content for slug "%s", variant "%s"',
				$slug,
				$variant ?: 'none'
			) );
		}

		// Replace variables
		if ( ! empty( $variables ) ) {
			$prompt_content = $this->replace_variables( $prompt_content, $variables );
		}

		return $prompt_content;
	}

	/**
	 * Get prompt configuration (GPT parameters).
	 *
	 * @param string $slug Prompt slug.
	 * @return array Configuration array.
	 * @throws Exception If prompt not found.
	 */
	public function get_prompt_config( $slug ) {
		$cache_key = 'ai_prompt_config_' . $slug;
		
		// Try to get from cache
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		
		if ( false === $cached ) {
			$posts = get_posts( array(
				'post_type' => 'ai_prompt',
				'meta_query' => array(
					array(
						'key' => '_prompt_slug',
						'value' => $slug,
					),
					array(
						'key' => '_is_active',
						'value' => '1',
					),
				),
				'posts_per_page' => 1,
			) );

			if ( empty( $posts ) ) {
				$this->log_missing_prompt( $slug );
				throw new Exception( sprintf(
					__( 'Prompt "%s" nicht gefunden. Bitte in AI Prompts → Hinzufügen konfigurieren.', 'ai-featured-image' ),
					$slug
				) );
			}

			$post_id = $posts[0]->ID;
			
			$cached = array(
				'model' => get_post_meta( $post_id, '_gpt_model', true ),
				'temperature' => floatval( get_post_meta( $post_id, '_gpt_temperature', true ) ),
				'max_tokens' => intval( get_post_meta( $post_id, '_gpt_max_tokens', true ) ),
				'response_format' => get_post_meta( $post_id, '_gpt_response_format', true ),
			);

			// Set defaults
			if ( empty( $cached['model'] ) ) {
				$cached['model'] = 'gpt-5-mini';
			}
			if ( ! $cached['temperature'] ) {
				$cached['temperature'] = 0.2;
			}
			if ( empty( $cached['response_format'] ) ) {
				$cached['response_format'] = 'text';
			}

			wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $cached;
	}

	/**
	 * Get prompt ID by slug.
	 *
	 * @param string $slug Prompt slug.
	 * @return int|null Prompt post ID or null if not found.
	 */
	public function get_prompt_id( $slug ) {
		$cache_key = 'ai_prompt_id_' . $slug;
		
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		
		if ( false === $cached ) {
			$posts = get_posts( array(
				'post_type' => 'ai_prompt',
				'meta_query' => array(
					array(
						'key' => '_prompt_slug',
						'value' => $slug,
					),
					array(
						'key' => '_is_active',
						'value' => '1',
					),
				),
				'posts_per_page' => 1,
			) );

			$cached = ! empty( $posts ) ? $posts[0]->ID : null;
			wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $cached;
	}

	/**
	 * Get all active prompts.
	 *
	 * @return array Array of prompt objects.
	 */
	public function get_all_active_prompts() {
		$cache_key = 'all_active_prompts';
		
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		
		if ( false === $cached ) {
			$posts = get_posts( array(
				'post_type' => 'ai_prompt',
				'meta_query' => array(
					array(
						'key' => '_is_active',
						'value' => '1',
					),
				),
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
			) );

			$prompts = array();
			foreach ( $posts as $post ) {
				$slug = get_post_meta( $post->ID, '_prompt_slug', true );
				$prompts[ $slug ] = array(
					'id' => $post->ID,
					'title' => $post->post_title,
					'slug' => $slug,
					'type' => get_post_meta( $post->ID, '_prompt_type', true ),
					'model' => get_post_meta( $post->ID, '_gpt_model', true ),
				);
			}

			$cached = $prompts;
			wp_cache_set( $cache_key, $cached, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $cached;
	}

	/**
	 * Replace variables in prompt text.
	 *
	 * @param string $prompt Prompt text with variables.
	 * @param array  $variables Variables to replace.
	 * @return string Prompt with replaced variables.
	 */
	private function replace_variables( $prompt, $variables ) {
		$replacements = array();

		// Standard variables
		$standard_vars = array(
			'post_title',
			'post_excerpt',
			'post_content',
			'min_words',
			'max_words',
			'current_words',
			'length',
		);

		foreach ( $standard_vars as $var ) {
			if ( isset( $variables[ $var ] ) ) {
				$replacements[ '{' . $var . '}' ] = $variables[ $var ];
			}
		}

		// Perform replacements
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $prompt );
	}

	/**
	 * Log missing prompt error.
	 *
	 * @param string $slug Missing prompt slug.
	 */
	private function log_missing_prompt( $slug ) {
		$upload = wp_upload_dir();
		$log_file = trailingslashit( $upload['basedir'] ) . 'ai-featured-image.log';
		
		$entry = array(
			'ts' => gmdate( 'c' ),
			'message' => 'missing_prompt',
			'context' => array(
				'slug' => $slug,
				'admin_url' => admin_url( 'post-new.php?post_type=ai_prompt' ),
			),
		);

		$line = wp_json_encode( $entry ) . PHP_EOL;
		
		if ( ! is_dir( $upload['basedir'] ) ) {
			@wp_mkdir_p( $upload['basedir'] );
		}
		
		@file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Check if required prompts exist.
	 *
	 * @return array Array with missing prompt slugs.
	 */
	public function check_required_prompts() {
		$required = array(
			'system-post-generation',
			'system-correction',
			'post-generation',
			'correction-expand',
			'correction-shorten',
			'image-generation',
		);

		$missing = array();

		foreach ( $required as $slug ) {
			$posts = get_posts( array(
				'post_type' => 'ai_prompt',
				'meta_query' => array(
					array(
						'key' => '_prompt_slug',
						'value' => $slug,
					),
					array(
						'key' => '_is_active',
						'value' => '1',
					),
				),
				'posts_per_page' => 1,
			) );

			if ( empty( $posts ) ) {
				$missing[] = $slug;
			}
		}

		return $missing;
	}

	/**
	 * Clear all prompt caches.
	 */
	public function clear_cache() {
		$prompts = get_posts( array(
			'post_type' => 'ai_prompt',
			'posts_per_page' => -1,
		) );

		foreach ( $prompts as $prompt ) {
			$slug = get_post_meta( $prompt->ID, '_prompt_slug', true );
			if ( $slug ) {
				wp_cache_delete( 'ai_prompt_' . $slug, self::CACHE_GROUP );
				wp_cache_delete( 'ai_prompt_config_' . $slug, self::CACHE_GROUP );
			}
		}

		wp_cache_delete( 'all_active_prompts', self::CACHE_GROUP );
	}
}

