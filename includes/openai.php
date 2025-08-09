<?php
defined('ABSPATH') || exit;

function wpgen_get_p5js_from_openai(array $args) {
    $api_key = trim(get_option('wpgen_openai_api_key', ''));
    if (empty($api_key)) {
        return new WP_Error('wpgen_no_key', 'Configura tu OpenAI API Key en Ajustes.');
    }
    $model = apply_filters('wpgen_openai_model', trim(get_option('wpgen_openai_model', 'gpt-4.1')));
    $timeout = intval(get_option('wpgen_openai_timeout', 60));
    if ($timeout <= 0) $timeout = 60;

    $data_url = esc_url_raw( $args['data_url'] ?? '' );
    if ( empty( $data_url ) ) {
        $data_url = esc_url_raw( get_option( 'gv_default_dataset_url', '' ) );
    }
    $dataset_text = GV_Dataset_Helper::get_sample( $data_url );
    $user_prompt  = $args['user_prompt'] ?? '';
    $width        = intval( $args['width']  ?? 800 );
    $height       = intval( $args['height'] ?? 500 );
    $data_format  = $args['data_format'] ?? 'auto';

    $user_prompt .= "\n\nPARAMS:\n" . wp_json_encode( [
        'data_url'    => $data_url,
        'data_format' => $data_format,
        'width'       => $width,
        'height'      => $height,
    ] );
    $combined_prompt = "DATASET:\n{$dataset_text}\n\nUSER REQUEST:\n{$user_prompt}";

    $payload = [
        'model' => $model,
        'input' => [[
            'role'    => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => $combined_prompt,
            ]],
        ]],
    ];

    $res = wp_remote_post('https://api.openai.com/v1/responses', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => $timeout,
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if ($code !== 200 || !is_array($body)) {
        return new WP_Error('wpgen_bad_response', 'Respuesta no válida de OpenAI.');
    }

    $assistant_message = $body['data'][0] ?? ($body['last_message'] ?? null);
    if (!$assistant_message) {
        return new WP_Error('wpgen_no_message', 'No se recibió mensaje del asistente');
    }

    $raw_text = td_get_assistant_text($assistant_message);
    $code = td_extract_p5_code($raw_text);

    if (!$code) {
        error_log('[TD] No se encontró bloque p5.js. Primeros 500 chars: ' . substr($raw_text, 0, 500));
        return new WP_Error('wpgen_no_p5', 'La respuesta no contiene código p5.js detectable');
    }

    return $code;
}
