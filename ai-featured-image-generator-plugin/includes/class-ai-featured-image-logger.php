<?php
/**
 * Lightweight file logger for the plugin.
 * Writes JSON lines to uploads/ai-featured-image.log
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AI_Featured_Image_Logger {
    /**
     * Write a log line.
     *
     * @param string $message
     * @param array  $context
     */
    public static function log( $message, array $context = array() ): void {
        // Toggle via constant/filter if needed
        $enabled = defined( 'AI_FEATURED_IMAGE_DEBUG' ) ? AI_FEATURED_IMAGE_DEBUG : true;
        $enabled = apply_filters( 'ai_featured_image_debug_enabled', $enabled );
        if ( ! $enabled ) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'ai-featured-image.log';

        $entry = array(
            'ts'      => gmdate( 'c' ),
            'message' => $message,
            'context' => $context,
        );

        $line = wp_json_encode( $entry );
        // Fallback to PHP error_log if file writing fails
        if ( ! @file_put_contents( $log_file, $line . PHP_EOL, FILE_APPEND | LOCK_EX ) ) {
            error_log( '[ai-featured-image] ' . $line );
        }
    }
}
