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
        return $body['output'][0]['content'][0]['text'];
    }
}
