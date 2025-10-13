<?php
/**
 * WP-CLI commands for AI Featured Image plugin.
 *
 * @package AI_Featured_Image
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Featured Image WP-CLI commands.
 */
class AI_Featured_Image_CLI {
	/**
	 * Test AI post generation with length correction.
	 *
	 * ## OPTIONS
	 *
	 * [--post_id=<post_id>]
	 * : The post ID to generate content for. If not provided, a new test post will be created.
	 *
	 * [--length=<length>]
	 * : Content length: short, medium, long, verylong
	 * ---
	 * default: medium
	 * options:
	 *   - short
	 *   - medium
	 *   - long
	 *   - verylong
	 * ---
	 *
	 * [--auto-correct]
	 * : Enable automatic length correction (default: true)
	 *
	 * [--max-corrections=<num>]
	 * : Maximum correction attempts (0-3)
	 * ---
	 * default: 2
	 * ---
	 *
	 * [--save]
	 * : Save the generated content to the post
	 *
	 * ## EXAMPLES
	 *
	 *     # Test with existing post
	 *     wp ai-post test --post_id=123 --length=medium
	 *
	 *     # Create new test post and generate content
	 *     wp ai-post test --length=long --auto-correct --save
	 *
	 *     # Test without auto-correction
	 *     wp ai-post test --post_id=123 --no-auto-correct
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function test( $args, $assoc_args ) {
		$post_id = isset( $assoc_args['post_id'] ) ? intval( $assoc_args['post_id'] ) : 0;
		$length = isset( $assoc_args['length'] ) ? $assoc_args['length'] : 'medium';
		$auto_correct = ! isset( $assoc_args['no-auto-correct'] );
		$max_corrections = isset( $assoc_args['max-corrections'] ) ? intval( $assoc_args['max-corrections'] ) : 2;
		$save = isset( $assoc_args['save'] );

		// Create test post if no post_id provided
		if ( ! $post_id ) {
			WP_CLI::line( '📝 Erstelle neuen Test-Post...' );
			
			$titles = array(
				'Künstliche Intelligenz im Jahr 2025',
				'Die Zukunft des maschinellen Lernens',
				'Blockchain-Technologie und ihre Anwendungen',
			);
			
			$post_id = wp_insert_post( array(
				'post_title' => $titles[ array_rand( $titles ) ] . ' - CLI Test ' . gmdate( 'Y-m-d H:i:s' ),
				'post_status' => 'draft',
				'post_type' => 'post',
			) );

			if ( is_wp_error( $post_id ) ) {
				WP_CLI::error( 'Fehler beim Erstellen des Posts: ' . $post_id->get_error_message() );
			}

			WP_CLI::success( "Test-Post erstellt: ID $post_id" );
		}

		// Verify post exists
		$post = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( "Post mit ID $post_id nicht gefunden." );
		}

		WP_CLI::line( '' );
		WP_CLI::line( '🎯 Test-Konfiguration:' );
		WP_CLI::line( "   Post-ID: $post_id" );
		WP_CLI::line( "   Titel: " . $post->post_title );
		WP_CLI::line( "   Länge: $length" );
		WP_CLI::line( "   Auto-Korrektur: " . ( $auto_correct ? 'Ja' : 'Nein' ) );
		WP_CLI::line( "   Max. Korrekturen: $max_corrections" );
		WP_CLI::line( '' );

		// Create REST request
		$request = new WP_REST_Request( 'POST', '/ai-featured-image/v1/generate-post' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'length', $length );
		$request->set_param( 'auto_correct', $auto_correct );
		$request->set_param( 'max_corrections', $max_corrections );

		WP_CLI::line( '🚀 Starte Generierung...' );
		$start_time = microtime( true );

		// Execute request
		$response = rest_do_request( $request );
		
		$duration = round( microtime( true ) - $start_time, 2 );

		if ( $response->is_error() ) {
			WP_CLI::error( 'API Fehler: ' . $response->as_error()->get_error_message() );
		}

		$data = $response->get_data();

		if ( ! isset( $data['data'] ) ) {
			WP_CLI::error( 'Ungültige API-Antwort.' );
		}

		$result = $data['data'];
		$word_count = $result['word_count'];
		$corrections = $result['corrections'];

		WP_CLI::line( '' );
		WP_CLI::success( "Generierung abgeschlossen in {$duration}s" );
		WP_CLI::line( '' );

		// Display word count info
		WP_CLI::line( '📊 Wortanzahl:' );
		WP_CLI::line( "   Initial: {$word_count['initial']}" );
		WP_CLI::line( "   Final: {$word_count['final']}" );
		WP_CLI::line( "   Ziel: {$word_count['target_min']}-{$word_count['target_max']}" );
		
		if ( $word_count['valid'] ) {
			WP_CLI::success( '   Status: ✓ Gültig' );
		} else {
			WP_CLI::warning( '   Status: ! Außerhalb Zielbereich' );
		}
		WP_CLI::line( '' );

		// Display correction info
		if ( $corrections['enabled'] ) {
			WP_CLI::line( '🔄 Korrekturen:' );
			WP_CLI::line( "   Durchgeführt: {$corrections['made']}" );
			WP_CLI::line( "   Maximum: {$corrections['max_allowed']}" );
			
			if ( ! empty( $corrections['history'] ) ) {
				WP_CLI::line( '   Verlauf:' );
				foreach ( $corrections['history'] as $i => $corr ) {
					$direction = $corr['direction'] === 'expand' ? 'Erweitert ↑' : 'Gekürzt ↓';
					WP_CLI::line( sprintf( 
						'     %d. %s: %d → %d Wörter',
						$i + 1,
						$direction,
						$corr['before_words'],
						$corr['after_words']
					) );
				}
			}
			WP_CLI::line( '' );
		}

		// Display metadata
		if ( ! empty( $result['category_name'] ) ) {
			WP_CLI::line( '📁 Kategorie: ' . $result['category_name'] );
		}
		
		if ( ! empty( $result['tags'] ) ) {
			WP_CLI::line( '🏷️  Tags: ' . implode( ', ', $result['tags'] ) );
		}
		WP_CLI::line( '' );

		// Save content if requested
		if ( $save ) {
			WP_CLI::line( '💾 Speichere Content...' );
			
			$updated = wp_update_post( array(
				'ID' => $post_id,
				'post_content' => $result['content_html'],
			) );

			if ( is_wp_error( $updated ) ) {
				WP_CLI::error( 'Fehler beim Speichern: ' . $updated->get_error_message() );
			}

			// Set category
			if ( ! empty( $result['category_id'] ) ) {
				wp_set_post_categories( $post_id, array( $result['category_id'] ) );
			}

			// Set tags
			if ( ! empty( $result['tags'] ) ) {
				wp_set_post_tags( $post_id, $result['tags'] );
			}

			WP_CLI::success( 'Content gespeichert!' );
			WP_CLI::line( '' );
		}

		// Display edit link
		$edit_link = get_edit_post_link( $post_id, 'raw' );
		WP_CLI::line( "🔗 Post bearbeiten: $edit_link" );
		WP_CLI::line( '' );

		// Display content preview
		WP_CLI::line( '📄 Content-Vorschau (erste 500 Zeichen):' );
		WP_CLI::line( '─────────────────────────────────────────────' );
		$preview = wp_strip_all_tags( $result['content_html'] );
		$preview = substr( $preview, 0, 500 ) . ( strlen( $preview ) > 500 ? '...' : '' );
		WP_CLI::line( $preview );
		WP_CLI::line( '─────────────────────────────────────────────' );
	}

	/**
	 * Show statistics about AI post generation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ai-post stats
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function stats( $args, $assoc_args ) {
		$upload = wp_upload_dir();
		$log_file = trailingslashit( $upload['basedir'] ) . 'ai-featured-image.log';

		if ( ! file_exists( $log_file ) ) {
			WP_CLI::warning( 'Keine Log-Datei gefunden. Es wurden noch keine Posts generiert.' );
			return;
		}

		$lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		
		if ( ! $lines ) {
			WP_CLI::warning( 'Log-Datei ist leer.' );
			return;
		}

		$stats = array(
			'total' => 0,
			'today' => 0,
			'by_length' => array( 'short' => 0, 'medium' => 0, 'long' => 0, 'verylong' => 0 ),
			'corrections_total' => 0,
			'valid_count' => 0,
		);

		$today = gmdate( 'Y-m-d' );

		foreach ( $lines as $line ) {
			$entry = json_decode( $line, true );
			if ( ! $entry || ! isset( $entry['message'] ) ) {
				continue;
			}

			if ( $entry['message'] === 'rest_api_post_complete' ) {
				$stats['total']++;
				
				if ( isset( $entry['ts'] ) && strpos( $entry['ts'], $today ) === 0 ) {
					$stats['today']++;
				}

				if ( isset( $entry['context']['corrections_made'] ) ) {
					$stats['corrections_total'] += intval( $entry['context']['corrections_made'] );
				}

				if ( isset( $entry['context']['valid'] ) && $entry['context']['valid'] ) {
					$stats['valid_count']++;
				}
			}

			if ( $entry['message'] === 'rest_api_post_request' && isset( $entry['context']['length'] ) ) {
				$length = $entry['context']['length'];
				if ( isset( $stats['by_length'][ $length ] ) ) {
					$stats['by_length'][ $length ]++;
				}
			}
		}

		WP_CLI::line( '' );
		WP_CLI::line( '📊 AI Post Generation Statistiken' );
		WP_CLI::line( '═══════════════════════════════════════' );
		WP_CLI::line( '' );
		WP_CLI::line( "Gesamt generiert: {$stats['total']}" );
		WP_CLI::line( "Heute generiert: {$stats['today']}" );
		WP_CLI::line( '' );
		
		if ( $stats['total'] > 0 ) {
			$success_rate = round( ( $stats['valid_count'] / $stats['total'] ) * 100 );
			$avg_corrections = round( $stats['corrections_total'] / $stats['total'], 1 );
			
			WP_CLI::line( "Erfolgsrate: {$success_rate}%" );
			WP_CLI::line( "Ø Korrekturen: {$avg_corrections}" );
			WP_CLI::line( '' );
			
			WP_CLI::line( 'Nach Länge:' );
			foreach ( $stats['by_length'] as $length => $count ) {
				if ( $count > 0 ) {
					WP_CLI::line( "  - {$length}: {$count}" );
				}
			}
		}
		
		WP_CLI::line( '' );
		WP_CLI::line( "Log-Datei: $log_file" );
		WP_CLI::line( '' );
	}

	/**
	 * Display full debug information for an AI-generated post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID to show debug information for.
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show debug info for post 123
	 *     wp ai-post debug 123
	 *
	 *     # Export as JSON
	 *     wp ai-post debug 123 --format=json
	 *
	 *     # Show full system prompt
	 *     wp ai-post debug 123 --format=json | jq '.initial_generation.request.system_prompt_full'
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function debug( $args, $assoc_args ) {
		$post_id = intval( $args[0] );
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$post = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( "Post with ID $post_id not found." );
		}

		$debug_log = get_post_meta( $post_id, '_ai_debug_log', true );
		$summary = get_post_meta( $post_id, '_ai_generation_summary', true );

		if ( ! $debug_log && ! $summary ) {
			WP_CLI::error( 'No debug log found for this post. It was not generated via AI or was created before the debug feature was added.' );
		}

		// JSON/YAML format - output raw data
		if ( $format === 'json' || $format === 'yaml' ) {
			$debug = json_decode( $debug_log, true );
			if ( $format === 'json' ) {
				WP_CLI::line( wp_json_encode( $debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			} else {
				WP_CLI::line( yaml_emit( $debug ) );
			}
			return;
		}

		// Table format - human readable
		WP_CLI::line( '' );
		WP_CLI::line( '═══════════════════════════════════════════════════════════════' );
		WP_CLI::line( '  AI GENERATION DEBUG LOG' );
		WP_CLI::line( '═══════════════════════════════════════════════════════════════' );
		WP_CLI::line( '' );

		// Summary
		if ( $summary ) {
			WP_CLI::line( '📊 SUMMARY:' );
			WP_CLI::line( '───────────────────────────────────────────────────────────────' );
			
			if ( isset( $summary['timestamp'] ) ) {
				WP_CLI::line( '  Timestamp:       ' . $summary['timestamp'] );
			}
			if ( isset( $summary['model'] ) ) {
				WP_CLI::line( '  Model:           ' . $summary['model'] );
			}
			if ( isset( $summary['length'] ) ) {
				WP_CLI::line( '  Length:          ' . $summary['length'] );
			}
			if ( isset( $summary['target_range'] ) ) {
				WP_CLI::line( '  Target Range:    ' . $summary['target_range'] . ' words' );
			}
			if ( isset( $summary['word_count'] ) ) {
				WP_CLI::line( '  Final Word Count: ' . $summary['word_count'] . ' words' );
			}
			if ( isset( $summary['corrections'] ) ) {
				WP_CLI::line( '  Corrections:     ' . $summary['corrections'] );
			}
			if ( isset( $summary['status'] ) ) {
				$status_icon = $summary['status'] === 'valid' ? '✓' : '⚠';
				WP_CLI::line( '  Status:          ' . $status_icon . ' ' . $summary['status'] );
			}
			
			WP_CLI::line( '' );
		}

		// Full debug log
		if ( $debug_log ) {
			$debug = json_decode( $debug_log, true );
			
			if ( $debug && isset( $debug['initial_generation'] ) ) {
				$initial = $debug['initial_generation'];
				
				WP_CLI::line( '📤 INITIAL GENERATION:' );
				WP_CLI::line( '───────────────────────────────────────────────────────────────' );
				
				if ( isset( $initial['request'] ) ) {
					WP_CLI::line( '  Request:' );
					WP_CLI::line( '    Model:           ' . ( $initial['request']['model'] ?? 'N/A' ) );
					WP_CLI::line( '    Temperature:     ' . ( $initial['request']['temperature'] ?? 'N/A' ) );
					WP_CLI::line( '    Max Tokens:      ' . ( $initial['request']['max_tokens'] ?? 'N/A' ) );
					WP_CLI::line( '    Response Format: ' . ( $initial['request']['response_format'] ?? 'N/A' ) );
					
					if ( isset( $initial['request']['user_prompt_variant'] ) ) {
						WP_CLI::line( '    Prompt Variant:  ' . $initial['request']['user_prompt_variant'] );
					}
					
					WP_CLI::line( '' );
					
					// System Prompt
					if ( isset( $initial['request']['system_prompt_full'] ) ) {
						WP_CLI::line( '  ┌─ SYSTEM PROMPT (FULL) ─────────────────────────────────────┐' );
						$lines = explode( "\n", wordwrap( $initial['request']['system_prompt_full'], 60 ) );
						foreach ( $lines as $line ) {
							WP_CLI::line( '  │ ' . str_pad( $line, 60 ) . ' │' );
						}
						WP_CLI::line( '  └────────────────────────────────────────────────────────────┘' );
						WP_CLI::line( '' );
					}
					
					// User Prompt
					if ( isset( $initial['request']['user_prompt_full'] ) ) {
						WP_CLI::line( '  ┌─ USER PROMPT (FULL) ───────────────────────────────────────┐' );
						$lines = explode( "\n", wordwrap( $initial['request']['user_prompt_full'], 60 ) );
						foreach ( $lines as $line ) {
							WP_CLI::line( '  │ ' . str_pad( $line, 60 ) . ' │' );
						}
						WP_CLI::line( '  └────────────────────────────────────────────────────────────┘' );
						WP_CLI::line( '' );
					}
				}
				
				if ( isset( $initial['response'] ) ) {
					WP_CLI::line( '  Response:' );
					WP_CLI::line( '    Model Used:      ' . ( $initial['response']['model'] ?? 'N/A' ) );
					
					if ( isset( $initial['response']['usage'] ) && is_array( $initial['response']['usage'] ) ) {
						WP_CLI::line( '    Token Usage:' );
						WP_CLI::line( '      Prompt:        ' . ( $initial['response']['usage']['prompt_tokens'] ?? 0 ) );
						WP_CLI::line( '      Completion:    ' . ( $initial['response']['usage']['completion_tokens'] ?? 0 ) );
						WP_CLI::line( '      Total:         ' . ( $initial['response']['usage']['total_tokens'] ?? 0 ) );
					}
					
					WP_CLI::line( '' );
				}
			}
			
			// Corrections
			if ( isset( $debug['corrections'] ) && is_array( $debug['corrections'] ) && count( $debug['corrections'] ) > 0 ) {
				WP_CLI::line( '🔄 CORRECTIONS (' . count( $debug['corrections'] ) . '):' );
				WP_CLI::line( '───────────────────────────────────────────────────────────────' );
				
				foreach ( $debug['corrections'] as $idx => $corr ) {
					$dir_icon = $corr['direction'] === 'expand' ? '↑' : '↓';
					$dir_text = $corr['direction'] === 'expand' ? 'EXPAND' : 'SHORTEN';
					
					WP_CLI::line( '  Correction ' . ( $idx + 1 ) . ': ' . $dir_icon . ' ' . $dir_text );
					WP_CLI::line( '    Model:           ' . ( $corr['request']['model'] ?? 'N/A' ) );
					WP_CLI::line( '    Current Words:   ' . ( $corr['request']['current_words'] ?? 'N/A' ) );
					WP_CLI::line( '    New Words:       ' . ( $corr['response']['new_word_count'] ?? 'N/A' ) );
					
					if ( isset( $corr['request']['user_prompt_slug'] ) ) {
						WP_CLI::line( '    Prompt:          ' . $corr['request']['user_prompt_slug'] );
					}
					
					if ( isset( $corr['response']['usage'] ) && is_array( $corr['response']['usage'] ) ) {
						WP_CLI::line( '    Tokens:          ' . ( $corr['response']['usage']['total_tokens'] ?? 0 ) );
					}
					
					WP_CLI::line( '' );
				}
			}
		}
		
		WP_CLI::line( '═══════════════════════════════════════════════════════════════' );
		WP_CLI::line( '' );
		
		// Hint for JSON export
		WP_CLI::line( 'Tip: Use --format=json to export full debug data for further analysis' );
		WP_CLI::line( 'Example: wp ai-post debug ' . $post_id . ' --format=json | jq .' );
		WP_CLI::line( '' );
	}
}

WP_CLI::add_command( 'ai-post', 'AI_Featured_Image_CLI' );

