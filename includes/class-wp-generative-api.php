<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WP_Generative_API {
  public function __construct() {
    // Mantén tu inicialización/keys aquí
  }

  public function generate_p5_code( $dataset_url, $user_prompt ) {
    if ( empty( $dataset_url ) ) {
      return new \WP_Error( 'missing_url', 'Falta la URL del dataset.' );
    }
    // Construir prompt final
    $prompt = <<<PROMPT
Dataset URL: {$dataset_url}

Instrucciones: {$user_prompt}

Requisitos:
- Descarga y usa directamente el CSV de la URL (formato raw GitHub).
- Analiza tipos de columnas.
- Genera SOLO código p5.js (sin HTML) pero con un SKETCH COMPLETO:
  - define variables globales necesarias
  - preload() (si cargas CSV con loadTable), setup() y draw() obligatorios
  - si no puedes descargar el CSV, simula datos pero mantén preload/setup/draw.
  La salida debe estar delimitada por las líneas `-----BEGIN_P5JS-----` y `-----END_P5JS-----`.
- No serialices el código ni utilices placeholders; emplea los nombres reales de las columnas.
PROMPT;

    // Llamada a tu asistente / completions (ajusta a tu implementación v2)
    $response = $this->openai_call( $prompt );
    if ( is_wp_error( $response ) ) {
      return $response;
    }
    $content = isset($response['content']) ? $response['content'] : '';
    $content = trim((string) $content);
    if (!preg_match('/^-----BEGIN_P5JS-----\n([\s\S]+?)\n-----END_P5JS-----$/', $content, $m)) {
      return new \WP_Error('wpg_p5_missing', 'No se encontró el bloque de código p5.js en la respuesta.');
    }
    return trim($m[1]);
  }

  private function openai_call( $prompt ) {
    // Implementa aquí tu llamada real (Assistants API v2 o Responses).
    // Devuelve array('content' => '...codigo p5.js...') o WP_Error.
    return new \WP_Error( 'not_implemented', 'Implementa openai_call() con tu cliente.' );
  }
}
