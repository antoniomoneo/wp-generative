(function($){
  let cm = null;
  function initEditor(){
    const textarea = document.getElementById('td_code_editor');
    if (!textarea) return;
    const settings = window.tdCodeEditorSettings || {};
    settings.codemirror = $.extend({}, settings.codemirror, {
      mode: 'javascript',
      lineNumbers: true,
      matchBrackets: true,
      styleActiveLine: true,
      indentUnit: 2,
      tabSize: 2,
    });
    cm = wp.codeEditor.initialize( textarea, settings ).codemirror;

    // Overlay para resaltar variables declaradas (let|const|var)
    const varOverlay = {
      token: function(stream){
        if (stream.sol()) { /* reset per line */ }
        if (stream.match(/\b(let|const|var)\s+([A-Za-z_$][\w$]*)/, true)) {
          return "td-var";
        }
        while (!stream.eol()) {
          stream.next();
          if (/\s/.test(stream.peek())) break;
        }
        return null;
      }
    };
    cm.addOverlay(varOverlay);
  }

  function stripCodeFences(text){
    if (!text) return '';
    // Remove ``` or ```js fences
    return text.replace(/^\s*```[a-z]*\s*/i, '').replace(/\s*```\s*$/i, '');
  }

  function hasSetup(code){ return /function\s+setup\s*\(|setup\s*=\s*\(/.test(code); }
  function hasDraw(code){  return /function\s+draw\s*\(|draw\s*=\s*\(/.test(code); }

  function buildPreviewHTML(code){
    // Inserta p5 y el sketch como <script> para el iframe
    return [
      '<!doctype html><html><head><meta charset="utf-8">',
      '<meta name="viewport" content="width=device-width,initial-scale=1">',
      '<script src="https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js"></script>',
      '</head><body>',
      '<main id="app"></main>',
      '<script>',
      code,
      '</script>',
      '</body></html>'
    ].join('\n');
  }

  $(document).ready(function(){
    initEditor();

    const $dataset = $('#td_dataset_url');
    const $prompt  = $('#td_user_prompt');
    const $btn     = $('#td_generate');
    const $copy    = $('#td_copy_code');
    const $status  = $('.td-status');

    function validate(){
      const ok = $dataset.val().trim().length > 0;
      $btn.prop('disabled', !ok);
      return ok;
    }
    $dataset.on('input change', validate);
    validate();

    $btn.on('click', function(e){
      e.preventDefault();
      $status.text('');
      if (!validate()){
        $status.text('Falta la URL del dataset.').addClass('error');
        return;
      }
      const datasetUrl = $dataset.val().trim();
      const userPrompt = $prompt.val().trim();
      if (!userPrompt){
        $status.text('Escribe una descripción para la visualización.');
        return;
      }
      $btn.prop('disabled', true).text('Generando…');
      $status.removeClass('error').text('Llamando al asistente…');

      $.ajax({
        method: 'POST',
        url: tdGenerative.ajaxUrl,
        data: {
          action: 'td_generate_code',
          nonce: tdGenerative.nonce,
          datasetUrl: datasetUrl,
          userPrompt: userPrompt
        }
      }).done(function(resp){
        if (!resp || !resp.success){
          $status.addClass('error').text((resp && resp.data && resp.data.message) ? resp.data.message : 'Error desconocido.');
          return;
        }
        let code = stripCodeFences(resp.data.code || '');
        if (!hasSetup(code) || !hasDraw(code)){
          $status.addClass('error').text('La respuesta NO contiene un sketch completo (faltan setup() o draw()). Ajusta las instrucciones del asistente o vuelve a generar.');
          return;
        }
        if (cm){
          cm.setValue(code);
          cm.focus();
        } else {
          $('#td_code_editor').val(code);
        }
        $status.removeClass('error').text('Código insertado en el editor.');
      }).fail(function(xhr){
        const msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Fallo en la llamada.';
        $status.addClass('error').text(msg);
      }).always(function(){
        $btn.prop('disabled', false).text('Generar');
      });
    });

    $copy.on('click', function(e){
      e.preventDefault();
      let val = '';
      if (cm) { val = cm.getValue(); } else { val = $('#td_code_editor').val(); }
      navigator.clipboard.writeText(val).then(function(){
        $status.removeClass('error').text('Código copiado al portapapeles.');
      }, function(){
        $status.addClass('error').text('No se pudo copiar.');
      });
    });

    // Vista previa
    $('#td_preview').on('click', function(e){
      e.preventDefault();
      const iframe = document.getElementById('td_preview_iframe');
      const code = cm ? cm.getValue() : ($('#td_code_editor').val() || '');
      if (!hasSetup(code) || !hasDraw(code)){
        $status.addClass('error').text('No se puede previsualizar: faltan setup() o draw().');
        return;
      }
      const html = buildPreviewHTML(code);
      // Cargar en iframe via srcdoc (fallback a Blob si hiciera falta)
      if ('srcdoc' in iframe){
        iframe.srcdoc = html;
      } else {
        const blob = new Blob([html], {type:'text/html'});
        iframe.src = URL.createObjectURL(blob);
      }
      $status.removeClass('error').text('Vista previa actualizada.');
    });
  });
})(jQuery);
