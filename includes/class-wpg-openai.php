<?php
defined( 'ABSPATH' ) || exit;

class WPG_OpenAI {
    private $api_key;
    private $api_url;

    public function __construct( $api_key, $api_url = 'https://api.openai.com/v1/responses' ) {
        $this->api_key = $api_key;
        $this->api_url = $api_url;
    }

    public function get_p5js_code( $combined_prompt ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'missing_credentials', 'API Key no establecido.' );
        }

        $payload = [
            'model'            => 'gpt-4.1-mini',
            'input'            => [
                [
                    'role'    => 'user',
                    'content' => [
                        [ 'type' => 'input_text', 'text' => $combined_prompt ],
                    ],
                ],
            ],
            'max_output_tokens' => 1024,
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

        $body_raw = wp_remote_retrieve_body( $response );
        $body     = json_decode( $body_raw, true );

        if ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
            return new WP_Error( 'api_error', 'Error en la API', $body_raw );
        }

        $code = trim( $body['output_text'] ?? '' );
        if ( '' === $code && isset( $body['output'][0]['content'][0]['text'] ) ) {
            $code = trim( $body['output'][0]['content'][0]['text'] );
        }
        if ( '' === $code ) {
            return new WP_Error( 'no_code', 'La respuesta no contiene código p5.js', $body_raw );
        }

        // Comprueba que existan funciones básicas de p5.js para validar el sketch.
        $has_setup  = preg_match( '/function\s+setup\s*\(/i', $code );
        $has_draw   = preg_match( '/function\s+draw\s*\(/i', $code );
        $has_canvas = preg_match( '/createCanvas\s*\(/i', $code );
        if ( ! ( $has_setup && $has_draw && $has_canvas ) ) {
            return new WP_Error( 'no_code', 'La respuesta no contiene código p5.js válido', $body_raw );
        }

        return $code;
    }
}
