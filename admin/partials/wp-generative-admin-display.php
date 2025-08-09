<div class="wrap">
  <h1>Generador p5.js</h1>
  <div class="td-field">
    <label for="td_dataset_url"><strong>Dataset URL (raw GitHub CSV)</strong></label>
    <input type="url" id="td_dataset_url" name="td_dataset_url" class="regular-text" placeholder="https://raw.githubusercontent.com/usuario/repo/main/dataset.csv" value="<?php echo esc_attr( get_option('td_dataset_url', '') ); ?>" />
    <p class="description">Pega aquí la URL raw del CSV (GitHub u otra fuente directa).</p>
  </div>

  <div class="td-field">
    <label for="td_user_prompt"><strong>Descripción / Prompt</strong></label>
    <textarea id="td_user_prompt" name="td_user_prompt" rows="5" class="large-text" placeholder="Describe la visualización que quieres generar..."></textarea>
  </div>
 
  <label for="td_code_editor"><strong>Código p5.js</strong></label>
  <textarea id="td_code_editor" name="td_code_editor" rows="20" class="large-text" placeholder="Aquí aparecerá el código p5.js"></textarea>
 
  <p class="submit">
    <button id="td_generate" class="button button-primary" disabled>Generar</button>
    <button id="td_copy_code" class="button">Copiar código</button>
  </p>
  <div class="td-status" aria-live="polite"></div>
</div>
