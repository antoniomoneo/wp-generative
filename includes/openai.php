<?php
// OpenAI client for wp-generative (updated)

defined( 'ABSPATH' ) || exit;

function wpg_extract_p5_code( $text ) {
  // 1) remove markdown fences if present
  $text = preg_replace('/^\s*```(?:javascript|js|p5)?\s*/i', '', $text);
  $text = preg_replace('/\s*```\s*$/', '', $text);
  // 2) normalize line endings and trim
  $text = preg_replace("/\r\n|\r/", "\n", $text);
  $text = trim( $text );
  return $text;
}

function wpg_is_valid_p5( $code ) {
  return ( strpos( $code, 'function setup' ) !== false
        && strpos( $code, 'function draw' ) !== false );
}

function wpg_call_openai_p5( $dataset_url, $user_prompt ) {
  $creds = wpg_get_openai_credentials();
  $system_instructions =
    "Eres un generador experto de visualizaciones interactivas usando p5.js. " .
    "Recibirás: (1) una URL de un CSV (raw de GitHub) y (2) una descripción de la visualización.\n" .
    "Obligatorio:\n" .
    "- Descarga el CSV con loadTable(url, 'csv', 'header') en preload().\n" .
    "- Detecta tipos de columnas y crea la visualización solicitada.\n" .
    "- Devuelve SOLO código JavaScript p5.js válido (sin HTML, sin comentarios extensos, sin explicaciones).\n" .
    "- `setup()` y `draw()` obligatorios; `preload()` si cargas el CSV con loadTable().\n" .
    "- Si la URL falla, simula datos con arrays para que el sketch funcione.\n" .
    "- No uses backticks ni fences Markdown en la salida.";

  $messages = [
    [ 'role' => 'system', 'content' => $system_instructions ],
    [ 'role' => 'user', 'content' =>
        'CSV_URL: ' . $dataset_url . "\n" .
        'DESCRIPCION: ' . $user_prompt . "\n" .
        'Entrega SOLO código p5.js.'
    ],
  ];

  $body = [
    'model' => 'gpt-4.1',
    'input' => $messages,
    'temperature' => 0.7,
    'top_p' => 1,
    'response_format' => [ 'type' => 'text' ],
    'max_output_tokens' => 4096,
  ];

  $args = [
    'headers' => [
      'Authorization' => 'Bearer ' . $creds['api_key'],
      'Content-Type'  => 'application/json',
    ],
    'body'    => wp_json_encode( $body ),
    'timeout' => 60,
  ];

  $res = wp_remote_post( 'https://api.openai.com/v1/responses', $args );
  if ( is_wp_error( $res ) ) {
    return new WP_Error( 'wpg_openai_error', $res->get_error_message() );
  }
  $json = json_decode( wp_remote_retrieve_body( $res ), true );

  $raw = isset( $json['output'][0]['content'][0]['text'] ) ? $json['output'][0]['content'][0]['text'] : '';
  $code = wpg_extract_p5_code( $raw );
  if ( ! $code || ! wpg_is_valid_p5( $code ) ) {
    return new WP_Error( 'wpg_p5_invalid', 'La respuesta no contiene código p5.js válido' );
  }
  return $code;
}

// Wrapper for existing shortcode integration.
function wpgen_get_p5js_from_openai( array $args ) {
  $dataset_url = esc_url_raw( $args['data_url'] ?? '' );
  if ( empty( $dataset_url ) ) {
    $dataset_url = esc_url_raw( get_option( 'gv_default_dataset_url', '' ) );
  }
  $user_prompt = $args['user_prompt'] ?? '';

  return wpg_call_openai_p5( $dataset_url, $user_prompt );
}

