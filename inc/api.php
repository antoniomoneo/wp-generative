<?php
if ( ! defined('ABSPATH') ) exit;

add_action('rest_api_init', function () {
  register_rest_route('wp-generative/v1', '/ask', [
    'methods'  => 'POST',
    'callback' => 'tdg_handle_openai_request',
    'permission_callback' => function () {
      return current_user_can('manage_options');
    }
  ]);
});

function tdg_handle_openai_request(\WP_REST_Request $req) {
  $assistant_id = sanitize_text_field($req->get_param('assistant_id'));
  $dataset_url  = esc_url_raw($req->get_param('dataset_url'));
  $user_prompt  = sanitize_textarea_field($req->get_param('user_prompt'));

  if (!$assistant_id || !$user_prompt) {
    return new \WP_Error('bad_request', 'Falta assistant_id o prompt.', ['status' => 400]);
  }

  $api_key = get_option('wpg_openai_api_key');
  if (!$api_key) {
    return new \WP_Error('no_api_key', 'Configura tu OpenAI API key.', ['status' => 500]);
  }

  $input = "DATASET: {$dataset_url}\nINSTRUCCIONES: {$user_prompt}\n\nDevuelve SOLO código p5.js sin HTML y con setup() y draw() (preload() opcional).";

  $body = [
    'assistant_id' => $assistant_id,
    'input'        => $input,
    'modalities'   => ['text']
  ];

  $resp = wp_remote_post('https://api.openai.com/v1/responses', [
    'headers' => [
      'Content-Type'  => 'application/json',
      'Authorization' => 'Bearer ' . $api_key
    ],
    'body'    => wp_json_encode($body),
    'timeout' => 60,
  ]);

  if (is_wp_error($resp)) {
    return new \WP_Error('openai_error', $resp->get_error_message(), ['status' => 500]);
  }

  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code < 200 || $code >= 300) {
    return new \WP_Error('openai_http', 'Error OpenAI', ['status' => $code, 'details' => $json]);
  }

  $output_text = '';
  if (isset($json['output_text'])) {
    $output_text = $json['output_text'];
  } elseif (isset($json['output']) && is_array($json['output'])) {
    foreach ($json['output'] as $item) {
      if (isset($item['content'][0]['text'])) {
        $output_text .= $item['content'][0]['text'];
      }
    }
  } else {
    $output_text = wp_remote_retrieve_body($resp);
  }

  return [
    'success' => true,
    'raw'     => $json,
    'text'    => trim($output_text),
  ];
}
