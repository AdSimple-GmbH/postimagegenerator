<?php
// Minimal PHPUnit bootstrap for plugin unit tests.
// Assumes WordPress test suite is available via WP_TESTS_DIR.

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    fwrite( STDERR, "Could not find WordPress test suite in ".$_tests_dir."\n" );
    exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
    // Load the plugin.
    require dirname( __DIR__ ) . '/ai-featured-image.php';
} );

require $_tests_dir . '/includes/bootstrap.php';


