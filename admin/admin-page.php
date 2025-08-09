<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
  <h1>WP Generative — p5.js</h1>
  <form method="post">
    <?php
    if ( isset( $_POST['wpg_api_key'] ) ) {
      update_option( 'wpg_openai_api_key', sanitize_text_field( $_POST['wpg_api_key'] ) );
      echo '<div class="notice notice-success"><p>API key guardada.</p></div>';
    }

    // Submit: generate p5.js
    if ( isset( $_POST['wpg_generate'] ) ) {
      $url  = esc_url_raw( $_POST['wpg_dataset_url'] ?? '' );
      $desc = sanitize_text_field( $_POST['wpg_prompt'] ?? '' );
      $code = wpg_call_openai_p5( $url, $desc );
      if ( is_wp_error( $code ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $code->get_error_message() ) . '</p></div>';
      } else {
        update_option( 'wpg_last_p5_code', $code );
        echo '<div class="notice notice-success"><p>Código p5.js generado.</p></div>';
      }
    }
    ?>
    <p><label>OpenAI API Key<br>
      <input name="wpg_api_key" type="password" value="<?php echo esc_attr( get_option( 'wpg_openai_api_key', '' ) ); ?>" style="width:100%"></label></p>
    <p><label>Dataset URL (raw .csv)<br>
      <input name="wpg_dataset_url" type="url" value="<?php echo esc_attr( $_POST['wpg_dataset_url'] ?? '' ); ?>" style="width:100%"></label></p>
    <p><label>Descripción de la visualización<br>
      <textarea name="wpg_prompt" rows="6" style="width:100%"><?php echo esc_textarea( $_POST['wpg_prompt'] ?? '' ); ?></textarea></label></p>
    <p><button name="wpg_generate" class="button button-primary">Generar p5.js</button></p>
    <h3>p5.js (resultado)</h3>
    <textarea name="wpg_output" id="wpg_output" class="large-text code" rows="18"><?php
      echo esc_textarea( get_option( 'wpg_last_p5_code', '' ) );
    ?></textarea>
    <p class="description">Este es el código p5.js listo para incrustar.</p>
  </form>
  <!-- Visor de código con numeración y resaltado -->
  <textarea id="wpgen-p5-seed" style="display:none;"></textarea>
  <div class="wpgen-codewrap">
    <pre class="line-numbers"><code id="wpgen-p5-code" class="language-javascript"></code></pre>
  </div>
</div>

