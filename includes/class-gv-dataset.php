<?php
/**
 * Dataset helper: fetches and samples CSV/JSON to include in LLM payloads.
 *
 * @package wp-generative
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GV_Dataset_Helper {
    const DEFAULT_ROWS  = 30;
    const DEFAULT_BYTES = 60000; // 60KB safety cap
    const CACHE_TTL     = 600;   // 10 minutes

    /**
     * Fetch a dataset URL and return a safe textual sample.
     *
     * @param string $url Dataset URL.
     * @return string Sampled text or "DATASET NOT AVAILABLE".
     */
    public static function get_sample( $url ) {
        $url = esc_url_raw( $url );
        if ( empty( $url ) ) {
            return 'DATASET NOT AVAILABLE';
        }

        $cache_key = 'gv_ds_' . md5( $url );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $args = array(
            'timeout'             => 8,
            'redirection'         => 2,
            'limit_response_size' => 524288, // 512KB cap.
            'headers'             => array(
                'Accept' => 'text/csv,application/json;q=0.9,*/*;q=0.1',
            ),
        );

        $res = wp_remote_get( $url, $args );
        if ( is_wp_error( $res ) ) {
            return 'DATASET NOT AVAILABLE';
        }

        $code = (int) wp_remote_retrieve_response_code( $res );
        if ( $code < 200 || $code >= 300 ) {
            return 'DATASET NOT AVAILABLE';
        }

        $body = wp_remote_retrieve_body( $res );
        if ( '' === $body ) {
            return 'DATASET NOT AVAILABLE';
        }

        $ctype = wp_remote_retrieve_header( $res, 'content-type' );
        $text  = self::to_text_sample( $body, $ctype );

        $max_bytes = (int) apply_filters( 'gv_dataset_sample_limit_bytes', self::DEFAULT_BYTES );
        if ( strlen( $text ) > $max_bytes ) {
            $text = substr( $text, 0, $max_bytes ) . "\n... [truncated]\n";
        }

        $max_rows = (int) apply_filters( 'gv_dataset_sample_limit_rows', self::DEFAULT_ROWS );
        $lines    = preg_split( "/\r\n|\n|\r/", $text );
        if ( count( $lines ) > $max_rows ) {
            $lines = array_slice( $lines, 0, $max_rows );
            $text  = implode( "\n", $lines ) . "\n... [truncated]\n";
        }

        if ( function_exists( 'mb_convert_encoding' ) ) {
            $text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
        }

        set_transient( $cache_key, $text, self::CACHE_TTL );
        return $text;
    }

    /**
     * Convert body into a textual sample. For JSON, pretty-print; for CSV leave as-is.
     *
     * @param string $body   Body content.
     * @param string $ctype  Content type header.
     * @return string
     */
    protected static function to_text_sample( $body, $ctype ) {
        $ctype = is_string( $ctype ) ? strtolower( $ctype ) : '';
        if ( false !== strpos( $ctype, 'application/json' ) ) {
            $data = json_decode( $body, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $pretty = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
                return is_string( $pretty ) ? $pretty : 'DATASET NOT AVAILABLE';
            }
        }

        return $body; // assume CSV/plain text.
    }
}
