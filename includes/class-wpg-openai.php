<?php
defined( 'ABSPATH' ) || exit;

class WPG_OpenAI {
    private $api_key;
    private $api_url;

    public function __construct( $api_key, $api_url = 'https://api.tu-plugin.com/v1/visualizations' ) {
        $this->api_key = $api_key;
        $this->api_url = $api_url;
    }

    public function get_p5js_code( $prompt, $table_sample = [] ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'missing_credentials', 'API Key no establecido.' );
        }

        $payload = [
            'tableSample' => $table_sample,
            'userPrompt'  => $prompt,
            'options'     => [
                'lang'    => 'es',
                'library' => 'p5.js',
            ],
        ];

        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 60,
        ];

        $response = wp_remote_post( $this->api_url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! isset( $body['sketch'] ) ) {
            return new WP_Error( 'no_code', 'La respuesta no contiene código p5.js' );
        }

        $code = trim( $body['sketch'] );

        // Comprueba que existan funciones básicas de p5.js para validar el sketch.
        $has_setup  = preg_match( '/function\s+setup\s*\(/i', $code );
        $has_draw   = preg_match( '/function\s+draw\s*\(/i', $code );
        $has_canvas = preg_match( '/createCanvas\s*\(/i', $code );
        if ( ! ( $has_setup && $has_draw && $has_canvas ) ) {
            return new WP_Error( 'no_code', 'La respuesta no contiene código p5.js válido' );
        }

        return $code;
    }
}
