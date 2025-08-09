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
        const code = stripCodeFences(resp.data.code || '');
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
  });
})(jQuery);
