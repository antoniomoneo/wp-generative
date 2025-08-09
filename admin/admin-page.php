<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap">
  <h1>WP Generative — p5.js</h1>
  <form method="post">
    <?php if (isset($_POST['tdg_openai_api_key'])) {
      update_option('tdg_openai_api_key', sanitize_text_field($_POST['tdg_openai_api_key']));
      echo '<div class="updated"><p>API key guardada.</p></div>';
    } ?>
    <p><label>OpenAI API Key<br>
      <input name="tdg_openai_api_key" type="password" value="<?php echo esc_attr(get_option('tdg_openai_api_key','')); ?>" style="width:100%"></label></p>
    <p><button class="button">Guardar</button></p>
  </form>
  <hr>
  <p><label>Dataset URL (raw .csv)<br>
    <input id="td_dataset_url" type="url" placeholder="https://raw.githubusercontent.com/.../dataset.csv" style="width:100%"></label></p>
  <p><label>Instrucciones<br>
    <textarea id="td_user_prompt" rows="6" style="width:100%" placeholder="Describe la visualización deseada..."></textarea></label></p>
  <p><label>Assistant ID<br>
    <input id="td_assistant_id" type="text" placeholder="asst_XXXXXXXX" style="width:100%"></label></p>
  <p><button id="td_run_btn" class="button button-primary">Generar p5.js</button></p>
  <h3>Respuesta API</h3>
  <textarea id="td_api_response" rows="10" readonly style="width:100%"></textarea>
  <h3>p5.js (resultado)</h3>
  <textarea id="td_p5_code" rows="18" style="width:100%"></textarea>
</div>
