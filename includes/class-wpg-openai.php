<?php
defined( 'ABSPATH' ) || exit;

class WPG_OpenAI {
    private $api_key;
    private $assistant_id;

    public function __construct( $api_key, $assistant_id ) {
        $this->api_key      = $api_key;
        $this->assistant_id = $assistant_id;
    }

    public function get_p5js_code( $prompt ) {
        if ( empty( $this->api_key ) || empty( $this->assistant_id ) ) {
            return new WP_Error( 'missing_credentials', 'API Key o Assistant ID no establecidos.' );
        }

        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'OpenAI-Beta'   => 'assistants=v2',
            ],
            'body'    => wp_json_encode( [
                'assistant_id'      => $this->assistant_id,
                'input'             => $prompt,
                'max_output_tokens' => 1024,
            ] ),
            'timeout' => 60,
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/responses', $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! isset( $body['output'][0]['content'][0]['text'] ) ) {
            return new WP_Error( 'no_code', 'La respuesta no contiene código p5.js' );
        }

        // Extrae solo el código JavaScript en caso de que la respuesta
        // incluya una página HTML completa. Algunos modelos devuelven
        // <script> o incluso llamadas a document.write() que borran la
        // página y generan el modo "Quirks" en el navegador.
        $code = $body['output'][0]['content'][0]['text'];
        $code = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $code );
        $code = preg_replace( '/<\/?(?:html|head|body)[^>]*>/i', '', $code );
        $code = preg_replace( '/<script[^>]*>(.*?)<\/script>/is', '$1', $code );
        $code = preg_replace( '/document\.write\s*\([^)]*\);?/i', '', $code );

        $code = trim( $code );

        // Comprueba que existan funciones básicas de p5.js para validar el sketch.
        $has_setup = preg_match( '/function\s+setup\s*\(/i', $code );
        $has_draw  = preg_match( '/function\s+draw\s*\(/i', $code );
        $has_canvas = preg_match( '/createCanvas\s*\(/i', $code );
        if ( ! ( $has_setup && $has_draw && $has_canvas ) ) {
            return new WP_Error( 'no_code', 'La respuesta no contiene código p5.js válido' );
        }

        return $code;
    }
}
