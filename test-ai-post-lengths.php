<?php
/**
 * WP CLI Script to test AI post generation with all length options
 * 
 * Usage: docker compose run --rm wpcli wp eval-file test-ai-post-lengths.php
 */

if ( ! defined( 'WP_CLI' ) ) {
	echo "This script must be run via WP CLI\n";
	exit(1);
}

WP_CLI::line( '═══════════════════════════════════════════════════════' );
WP_CLI::line( '🧪 AI Post Length Testing Script' );
WP_CLI::line( '═══════════════════════════════════════════════════════' );

$lengths = array(
	'short' => array(
		'min' => 300,
		'max' => 500,
		'label' => 'Kurz (300-500 Worte)'
	),
	'medium' => array(
		'min' => 800,
		'max' => 1200,
		'label' => 'Mittel (800-1200 Worte)'
	),
	'long' => array(
		'min' => 1500,
		'max' => 2000,
		'label' => 'Lang (1500-2000 Worte)'
	),
	'verylong' => array(
		'min' => 2500,
		'max' => 3000,
		'label' => 'Sehr Lang (2500-3000 Worte)'
	)
);

$test_titles = array(
	'Künstliche Intelligenz in der modernen Medizin',
	'Machine Learning Algorithmen und ihre Anwendungen',
	'Deep Learning: Revolution in der Bildverarbeitung',
	'Natural Language Processing im Kundenservice'
);

function count_html_words( $html ) {
	$text = wp_strip_all_tags( $html );
	$text = preg_replace( '/\s+/', ' ', $text );
	$text = trim( $text );
	$words = explode( ' ', $text );
	return count( array_filter( $words, function($word) {
		return strlen( trim( $word ) ) > 0;
	}));
}

$results = array();
$total_tests = 0;
$passed_tests = 0;

foreach ( $lengths as $length_key => $length_config ) {
	WP_CLI::line( "\n" . str_repeat( '─', 55 ) );
	WP_CLI::line( sprintf( '📝 Testing: %s', $length_config['label'] ) );
	WP_CLI::line( str_repeat( '─', 55 ) );
	
	$title = $test_titles[ array_rand( $test_titles ) ] . ' [Test ' . $length_key . ']';
	
	// Create a test post
	$post_id = wp_insert_post( array(
		'post_title' => $title,
		'post_status' => 'draft',
		'post_type' => 'post',
	));
	
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::error( 'Failed to create test post: ' . $post_id->get_error_message() );
		continue;
	}
	
	WP_CLI::line( sprintf( '✓ Created post ID: %d', $post_id ) );
	WP_CLI::line( sprintf( '  Title: %s', $title ) );
	
	// Simulate the AJAX call
	$_POST['post_id'] = $post_id;
	$_POST['length'] = $length_key;
	$_POST['nonce'] = wp_create_nonce( 'ai_featured_image_nonce' );
	
	// Get the API connector instance
	require_once get_template_directory() . '/../plugins/ai-featured-image/includes/class-ai-featured-image-api-connector.php';
	
	if ( ! class_exists( 'AI_Featured_Image_API_Connector' ) ) {
		// Try alternative path
		require_once ABSPATH . 'wp-content/plugins/ai-featured-image/includes/class-ai-featured-image-api-connector.php';
	}
	
	WP_CLI::line( '  Generating AI content...' );
	WP_CLI::line( sprintf( '  Target: %d-%d words', $length_config['min'], $length_config['max'] ) );
	
	// Capture the output
	ob_start();
	
	try {
		// We need to simulate the AJAX call
		// Since we can't call the callback directly due to nonce checks,
		// we'll use the API directly
		
		$options = get_option( 'ai_featured_image_options' );
		$api_key = ! empty( $options['api_key'] ) ? $options['api_key'] : '';
		
		if ( empty( $api_key ) ) {
			WP_CLI::warning( '  ⚠ No API key configured - skipping actual generation' );
			wp_delete_post( $post_id, true );
			continue;
		}
		
		// Call the generation
		do_action( 'wp_ajax_generate_ai_post' );
		
		$response = ob_get_clean();
		$data = json_decode( $response, true );
		
		if ( isset( $data['success'] ) && $data['success'] && isset( $data['data']['content_html'] ) ) {
			$word_count = count_html_words( $data['data']['content_html'] );
			
			// Update the post with generated content
			wp_update_post( array(
				'ID' => $post_id,
				'post_content' => $data['data']['content_html']
			));
			
			// Check if word count is in range
			$min_met = $word_count >= $length_config['min'];
			$max_ok = $word_count <= ( $length_config['max'] + 500 ); // Allow 500 words over
			$passed = $min_met && $max_ok;
			
			$total_tests++;
			if ( $passed ) {
				$passed_tests++;
			}
			
			// Color output
			if ( $passed ) {
				WP_CLI::success( sprintf( 'Word count: %d ✓', $word_count ) );
			} else {
				if ( ! $min_met ) {
					WP_CLI::warning( sprintf( 'Word count: %d (UNTER Minimum %d) ✗', $word_count, $length_config['min'] ) );
				} else {
					WP_CLI::warning( sprintf( 'Word count: %d (ÜBER Maximum %d) ✗', $word_count, $length_config['max'] ) );
				}
			}
			
			WP_CLI::line( sprintf( '  Post URL: %s', get_edit_post_link( $post_id ) ) );
			WP_CLI::line( sprintf( '  Tags: %d', isset( $data['data']['tags'] ) ? count( $data['data']['tags'] ) : 0 ) );
			WP_CLI::line( sprintf( '  Category: %s', isset( $data['data']['category_name'] ) ? $data['data']['category_name'] : 'N/A' ) );
			
			$results[ $length_key ] = array(
				'post_id' => $post_id,
				'word_count' => $word_count,
				'target_min' => $length_config['min'],
				'target_max' => $length_config['max'],
				'passed' => $passed,
				'title' => $title
			);
			
		} else {
			WP_CLI::error( '  Failed to generate content' );
			if ( isset( $data['data']['message'] ) ) {
				WP_CLI::line( '  Error: ' . $data['data']['message'] );
			}
			ob_end_clean();
		}
		
	} catch ( Exception $e ) {
		ob_end_clean();
		WP_CLI::error( '  Exception: ' . $e->getMessage() );
	}
	
	// Wait a bit between requests to avoid rate limiting
	if ( $length_key !== 'verylong' ) {
		WP_CLI::line( '  Waiting 5 seconds before next test...' );
		sleep( 5 );
	}
}

// Summary
WP_CLI::line( "\n" . str_repeat( '═', 55 ) );
WP_CLI::line( '📊 TEST SUMMARY' );
WP_CLI::line( str_repeat( '═', 55 ) );

foreach ( $results as $length_key => $result ) {
	$status_icon = $result['passed'] ? '✓' : '✗';
	$status_text = $result['passed'] ? 'PASSED' : 'FAILED';
	$percentage = round( ( $result['word_count'] / $result['target_min'] ) * 100 );
	
	WP_CLI::line( sprintf(
		'%s %s: %d words (%d%% of minimum) - %s',
		$status_icon,
		strtoupper( $length_key ),
		$result['word_count'],
		$percentage,
		$status_text
	));
	WP_CLI::line( sprintf(
		'   Target: %d-%d words | Post ID: %d',
		$result['target_min'],
		$result['target_max'],
		$result['post_id']
	));
}

WP_CLI::line( "\n" . str_repeat( '─', 55 ) );
WP_CLI::line( sprintf( 'Total: %d/%d tests passed (%.1f%%)', $passed_tests, $total_tests, ( $passed_tests / $total_tests ) * 100 ) );
WP_CLI::line( str_repeat( '═', 55 ) );

if ( $passed_tests === $total_tests ) {
	WP_CLI::success( 'All tests passed! 🎉' );
	exit(0);
} else {
	WP_CLI::warning( sprintf( '%d tests failed!', $total_tests - $passed_tests ) );
	exit(1);
}


