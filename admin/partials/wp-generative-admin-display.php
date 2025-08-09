<!-- Editor + Vista previa: SOLO para Generador -->
<div class="wrap">
  <h1>Generador p5.js</h1>
  <div class="td-field">
    <label for="td_dataset_url"><strong>Dataset URL (raw GitHub CSV)</strong></label>
    <input type="url" id="td_dataset_url" class="regular-text" value="<?php echo esc_attr( get_option('td_dataset_url', '') ); ?>" placeholder="https://raw.githubusercontent.com/usuario/repo/main/dataset.csv" />
    <p class="description">Pega aquí la URL raw del CSV.</p>
  </div>
  <div class="td-field">
    <label for="td_user_prompt"><strong>Descripción / Prompt</strong></label>
    <textarea id="td_user_prompt" rows="5" class="large-text" placeholder="Describe la visualización..."></textarea>
  </div>
  <label for="td_code_editor"><strong>Código p5.js</strong></label>
  <textarea id="td_code_editor" rows="20" class="large-text" placeholder="Aquí aparecerá el código p5.js"></textarea>
  <p class="submit">
    <button id="td_generate" class="button button-primary" disabled>Generar</button>
    <button id="td_preview" class="button">Vista previa</button>
    <button id="td_copy_code" class="button">Copiar código</button>
  </p>
  <div class="td-status" aria-live="polite"></div>
  <div class="td-preview-wrap">
    <iframe id="td_preview_iframe" title="Vista previa p5.js" sandbox="allow-scripts allow-same-origin" style="width:100%;height:420px;border:1px solid #ddd;"></iframe>
  </div>
</div>
