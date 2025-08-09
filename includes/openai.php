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
    $dataset_text = GV_Dataset_Helper::get_sample( $data_url );
    $user_prompt  = $args['user_prompt'] ?? '';
    $width        = intval( $args['width']  ?? 800 );
    $height       = intval( $args['height'] ?? 500 );
    $data_format  = $args['data_format'] ?? 'auto';

    $input_text = "DATASET:\n{$dataset_text}\n\nUSER REQUEST:\n{$user_prompt}\n\nPARAMS:\n" . wp_json_encode( [
        'data_url'    => $data_url,
        'data_format' => $data_format,
        'width'       => $width,
        'height'      => $height,
    ] );

    $payload = [
        'model' => $model,
        'input' => [[
            'role'    => 'user',
            'content' => [[
                'type' => 'text',
                'text' => $input_text,
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

    $js = $body['output'][0]['content'][0]['text'] ?? '';
    if (!$js && isset($body['content'][0]['text'])) {
        $js = $body['content'][0]['text'];
    }
    if (!$js && isset($body['choices'][0]['message']['content'])) {
        $js = $body['choices'][0]['message']['content'];
    }

    if (!is_string($js) || $js === '') {
        return new WP_Error('wpgen_empty_js', 'OpenAI no devolvió código p5.js.');
    }
    return $js;
}
