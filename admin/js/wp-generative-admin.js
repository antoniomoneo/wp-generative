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
      tabSize: 2
    });
    cm = wp.codeEditor.initialize( textarea, settings ).codemirror;
    // Overlay para variables let/const/var
    const varOverlay = { token: function(stream){
      if (stream.match(/\b(let|const|var)\s+[A-Za-z_$][\w$]*/, true)) return "td-var";
      while (!stream.eol()) { stream.next(); if (/\s/.test(stream.peek())) break; }
      return null;
    } };
    cm.addOverlay(varOverlay);
  }
  function stripCodeFences(text){
    if (!text) return '';
    return text.replace(/^\s*```[a-z]*\s*/i, '').replace(/\s*```\s*$/i, '');
  }
  function hasSetup(code){ return /function\s+setup\s*\(|\bsetup\s*=\s*\(/.test(code); }
  function hasDraw(code){  return /function\s+draw\s*\(|\bdraw\s*=\s*\(/.test(code); }
  function buildPreviewHTML(code){
    return [
      '<!doctype html><html><head><meta charset="utf-8">',
      '<meta name="viewport" content="width=device-width,initial-scale=1">',
      '<script src="https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js"></script>',
      '</head><body>',
      '<script>', code, '</script>',
      '</body></html>'
    ].join('\n');
  }
  $(function(){
    initEditor();
    const $dataset = $('#td_dataset_url');
    const $prompt  = $('#td_user_prompt');
    const $btn     = $('#td_generate');
    const $copy    = $('#td_copy_code');
    const $status  = $('.td-status');
    const $prevBtn = $('#td_preview');
    function validate(){
      const ok = $dataset.length ? $dataset.val().trim().length > 0 : true;
      $btn.prop('disabled', !ok);
      return ok;
    }
    $dataset.on('input change', validate); validate();

    $btn.on('click', function(e){
      e.preventDefault();
      $status.text('');
      if (!validate()){ $status.addClass('error').text('Falta la URL del dataset.'); return; }
      const datasetUrl = $dataset.val().trim();
      const userPrompt = $prompt.val().trim();
      if (!userPrompt){ $status.text('Escribe una descripción para la visualización.'); return; }
      $btn.prop('disabled', true).text('Generando…'); $status.removeClass('error').text('Llamando al asistente…');
      $.post(tdGenerative.ajaxUrl, {
        action: 'td_generate_code', nonce: tdGenerative.nonce, datasetUrl, userPrompt
      }).done(function(resp){
        if (!resp || !resp.success){ $status.addClass('error').text(resp?.data?.message || 'Error desconocido.'); return; }
        let code = stripCodeFences(resp.data.code || '');
        if (cm){ cm.setValue(code); cm.focus(); } else { $('#td_code_editor').val(code); }
        if (!hasSetup(code) || !hasDraw(code)){
          $status.addClass('error').text('La respuesta NO es un sketch completo (faltan setup() o draw()).');
        } else {
          $status.removeClass('error').text('Código insertado en el editor.');
        }
      }).fail(function(xhr){
        const msg = xhr?.responseJSON?.data?.message || 'Fallo en la llamada.';
        $status.addClass('error').text(msg);
      }).always(function(){ $btn.prop('disabled', false).text('Generar'); });
    });

    $prevBtn.on('click', function(e){
      e.preventDefault();
      const iframe = document.getElementById('td_preview_iframe');
      const code = cm ? cm.getValue() : ($('#td_code_editor').val() || '');
      if (!hasSetup(code) || !hasDraw(code)){ $status.addClass('error').text('No se puede previsualizar: faltan setup() o draw().'); return; }
      const html = buildPreviewHTML(code);
      if ('srcdoc' in iframe) iframe.srcdoc = html; else iframe.src = URL.createObjectURL(new Blob([html], {type:'text/html'}));
      $status.removeClass('error').text('Vista previa actualizada.');
    });

    $copy.on('click', function(e){
      e.preventDefault();
      const val = cm ? cm.getValue() : ($('#td_code_editor').val() || '');
      navigator.clipboard.writeText(val).then(()=> $status.removeClass('error').text('Código copiado.'), ()=> $status.addClass('error').text('No se pudo copiar.'));
    });
  });
})(jQuery);
