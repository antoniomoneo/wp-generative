<?php
/**
 * OpenAI Assistants API (threads + runs) client + validador p5.js
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GV_OpenAI_Client {
       protected $api_key;
       protected $assistant_id; // Debe configurarse en Ajustes del plugin.

       public function __construct( $args = array() ) {
               $creds = wpg_get_openai_credentials();
               $this->api_key      = isset( $args['api_key'] ) ? $args['api_key'] : $creds['api_key'];
               $this->assistant_id = isset( $args['assistant_id'] ) ? $args['assistant_id'] : $creds['assistant_id'];
       }

       /**
        * Construye el prompt combinado y obtiene p5.js con reintentos y validación.
        *
        * @param string $user_prompt
        * @param array  $atts Debe incluir dataset_url si procede.
        * @return string Código p5.js o error.
        */
       public function generate_p5( $user_prompt, $atts = array() ) {
               $dataset_url = '';
               if ( isset( $atts['dataset_url'] ) && is_string( $atts['dataset_url'] ) ) {
                       $dataset_url = esc_url_raw( $atts['dataset_url'] );
               }
               if ( empty( $dataset_url ) ) {
                       $dataset_url = esc_url_raw( (string) get_option( 'gv_default_dataset_url', '' ) );
               }

               $dataset_text     = $dataset_url ? GV_Dataset_Helper::get_sample( $dataset_url ) : 'DATASET NOT AVAILABLE';
               $combined_prompt  = <<<PROMPT
DATASET:
{$dataset_text}

USER REQUEST:
{$user_prompt}

Responde SOLO entre las marcas -----BEGIN_P5JS----- y -----END_P5JS-----, sin Markdown ni HTML, con setup() y draw(). No serialices el código ni utilices placeholders; emplea los nombres reales de las columnas.
PROMPT;

               // Crear thread una vez y reusar en reintentos.
               $thread_id = $this->create_thread();
               if ( ! $thread_id ) {
                       return '/* ERROR: No se pudo crear el thread de OpenAI. */';
               }

               // Primer mensaje del usuario.
               $ok_msg = $this->add_user_message( $thread_id, $combined_prompt );
               if ( ! $ok_msg ) {
                       return '/* ERROR: No se pudo añadir el mensaje al thread. */';
               }

               $max_attempts = 3;
               $last_err     = '';
               for ( $i = 1; $i <= $max_attempts; $i++ ) {
                       $raw  = $this->run_and_collect( $thread_id, $i );
                       $code = $this->extract_p5_code( $raw );
                       if ( $this->is_valid_p5( $code ) ) {
                               return $code;
                       }
                       $last_err = 'Intento ' . $i . ' inválido. Falta delimitador o setup()/draw().';
                       error_log( '[wp-generative] ' . $last_err );
                       // Mensaje correctivo y nuevo run.
                       $retry_msg = "Tu respuesta NO cumplió el formato. Reenvía SOLO p5.js entre -----BEGIN_P5JS----- y -----END_P5JS-----, sin Markdown, con setup() y draw().";
                       $this->add_user_message( $thread_id, $retry_msg );
               }
               return "/* ERROR: No se obtuvo código p5.js válido tras reintentos. Último error: {$last_err} */";
       }

       /* ================= Assistants API helpers ================= */

       protected function headers_json() {
               return array(
                       'Authorization' => 'Bearer ' . $this->api_key,
                       'Content-Type'  => 'application/json',
               );
       }

       protected function create_thread() {
               $res = wp_remote_post( 'https://api.openai.com/v1/threads', array(
                       'timeout' => 30,
                       'headers' => $this->headers_json(),
                       'body'    => wp_json_encode( array() ),
               ) );
               if ( is_wp_error( $res ) ) {
                       error_log( '[wp-generative] create_thread error: ' . $res->get_error_message() );
                       return '';
               }
               $code = (int) wp_remote_retrieve_response_code( $res );
               $data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
               if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['id'] ) ) {
                       error_log( '[wp-generative] create_thread bad response code: ' . $code );
                       return '';
               }
               return (string) $data['id'];
       }

       protected function add_user_message( $thread_id, $content ) {
               $url = 'https://api.openai.com/v1/threads/' . rawurlencode( $thread_id ) . '/messages';
               $res = wp_remote_post( $url, array(
                       'timeout' => 30,
                       'headers' => $this->headers_json(),
                       'body'    => wp_json_encode( array(
                               'role'    => 'user',
                               'content' => (string) $content,
                       ) ),
               ) );
               if ( is_wp_error( $res ) ) {
                       error_log( '[wp-generative] add_user_message error: ' . $res->get_error_message() );
                       return false;
               }
               $code = (int) wp_remote_retrieve_response_code( $res );
               if ( $code < 200 || $code >= 300 ) {
                       error_log( '[wp-generative] add_user_message bad code: ' . $code );
                       return false;
               }
               return true;
       }

       protected function run_and_collect( $thread_id, $attempt ) {
                       // Crear run con instrucciones forzando delimitadores.
               $url_run = 'https://api.openai.com/v1/threads/' . rawurlencode( $thread_id ) . '/runs';
               $body = array(
                       'assistant_id' => $this->assistant_id,
                       // Refuerza instrucciones para este run:
                       'instructions' => "Responde SIEMPRE SOLO con código p5.js entre -----BEGIN_P5JS----- y -----END_P5JS-----, sin Markdown ni HTML. Incluye setup() y draw().",
               );
               $res = wp_remote_post( $url_run, array(
                       'timeout' => 30,
                       'headers' => $this->headers_json(),
                       'body'    => wp_json_encode( $body ),
               ) );
               if ( is_wp_error( $res ) ) {
                       error_log( '[wp-generative] create_run error: ' . $res->get_error_message() );
                       return '';
               }
               $code = (int) wp_remote_retrieve_response_code( $res );
               $data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
               if ( $code < 200 || $code >= 300 || empty( $data['id'] ) ) {
                       error_log( '[wp-generative] create_run bad response code: ' . $code );
                       return '';
               }
               $run_id = (string) $data['id'];

               // Poll hasta completed / failed con backoff simple.
               $status = isset( $data['status'] ) ? (string) $data['status'] : 'queued';
               $url_poll = 'https://api.openai.com/v1/threads/' . rawurlencode( $thread_id ) . '/runs/' . rawurlencode( $run_id );

               $tries = 0; $max_tries = 40; // ~40*1s = ~40s
               while ( $tries < $max_tries ) {
                       if ( in_array( $status, array( 'completed', 'failed', 'cancelled', 'expired' ), true ) ) {
                               break;
                       }
                       sleep(1);
                       $tries++;
                       $poll = wp_remote_get( $url_poll, array(
                               'timeout' => 30,
                               'headers' => $this->headers_json(),
                       ) );
                       if ( is_wp_error( $poll ) ) {
                               error_log( '[wp-generative] poll_run error: ' . $poll->get_error_message() );
                               return '';
                       }
                       $dcode = (int) wp_remote_retrieve_response_code( $poll );
                       $pdata = json_decode( (string) wp_remote_retrieve_body( $poll ), true );
                       if ( $dcode < 200 || $dcode >= 300 || ! is_array( $pdata ) ) {
                               error_log( '[wp-generative] poll_run bad code: ' . $dcode );
                               return '';
                       }
                       $status = isset( $pdata['status'] ) ? (string) $pdata['status'] : $status;
               }

               if ( 'completed' !== $status ) {
                       error_log( '[wp-generative] run did not complete. status=' . $status );
                       return '';
               }

               // Obtener el último mensaje del asistente (desc).
               $url_msgs = 'https://api.openai.com/v1/threads/' . rawurlencode( $thread_id ) . '/messages?limit=1&order=desc';
               $msgres = wp_remote_get( $url_msgs, array(
                       'timeout' => 30,
                       'headers' => $this->headers_json(),
               ) );
               if ( is_wp_error( $msgres ) ) {
                       error_log( '[wp-generative] fetch messages error: ' . $msgres->get_error_message() );
                       return '';
               }
               $mcode = (int) wp_remote_retrieve_response_code( $msgres );
               $mdata = json_decode( (string) wp_remote_retrieve_body( $msgres ), true );
               if ( $mcode < 200 || $mcode >= 300 || empty( $mdata['data'][0] ) ) {
                       error_log( '[wp-generative] fetch messages bad code: ' . $mcode );
                       return '';
               }
               $content_text = $this->flatten_message_content( $mdata['data'][0] );
               return $content_text;
       }

       protected function flatten_message_content( $message_item ) {
               // message_item['content'] es un array de bloques (text, image_file, etc.).
               if ( empty( $message_item['content'] ) || ! is_array( $message_item['content'] ) ) {
                       return '';
               }
               $text = '';
               foreach ( $message_item['content'] as $part ) {
                       if ( isset( $part['type'] ) && 'text' === $part['type'] && isset( $part['text']['value'] ) ) {
                               $text .= (string) $part['text']['value'];
                       }
               }
               return $text;
       }

       /* ================= Validación y extracción ================= */

       protected function extract_p5_code( $raw ) {
               if ( ! is_string( $raw ) || '' === $raw ) {
                       return '';
               }
               if ( preg_match( '/^-----BEGIN_P5JS-----\n([\s\S]+?)\n-----END_P5JS-----$/', trim($raw), $m ) ) {
                       $code = trim( $m[1] );
               } else {
                       $code = trim( $raw );
               }
               $code = str_replace( array("\r\n", "\r", '\\n'), "\n", $code );
               return $code;
       }

       protected function is_valid_p5( $code ) {
               if ( ! is_string( $code ) || '' === $code ) {
                       return false;
               }
               if ( false === strpos( $code, 'function setup' ) ) return false;
               if ( false === strpos( $code, 'function draw' ) ) return false;
               return true;
       }
}

