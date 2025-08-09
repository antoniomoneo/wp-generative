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
    $prompt = "Dataset URL: {$dataset_url}\n\n".
              "Instrucciones: {$user_prompt}\n\n".
              "Requisitos:\n".
              "- Descarga y usa directamente el CSV de la URL (formato raw GitHub).\n".
              "- Analiza tipos de columnas.\n".
              "- Genera SOLO código p5.js (sin HTML) pero con un SKETCH COMPLETO:\n".
              "  - define variables globales necesarias\n".
              "  - `preload()` (si cargas CSV con `loadTable`), `setup()` y `draw()` obligatorios\n".
              "  - si no puedes descargar el CSV, simula datos pero mantén `preload/setup/draw`.\n".
              "  No devuelvas comentarios explicativos ni bloques ```; solo el código.\n";

    // Llamada a tu asistente / completions (ajusta a tu implementación v2)
    $response = $this->openai_call( $prompt );
    if ( is_wp_error( $response ) ) {
      return $response;
    }
    $content = isset($response['content']) ? $response['content'] : '';
    // Limpiar backticks si vinieran
    $content = preg_replace('/^\s*```[a-z]*\s*/i', '', $content);
    $content = preg_replace('/\s*```\s*$/i', '', $content);
    return trim( (string) $content );
  }

  private function openai_call( $prompt ) {
    // Implementa aquí tu llamada real (Assistants API v2 o Responses).
    // Devuelve array('content' => '...codigo p5.js...') o WP_Error.
    return new \WP_Error( 'not_implemented', 'Implementa openai_call() con tu cliente.' );
  }
}
