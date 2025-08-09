(function(){
  // Crea el contenedor si no existe (útil en páginas de admin donde pegamos el código)
  function ensureViewer() {
    var existing = document.getElementById('wpgen-p5-code');
    if (existing) return existing;
    var wrap = document.querySelector('.wpgen-codewrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'wpgen-codewrap';
      document.body.appendChild(wrap);
    }
    var pre = document.createElement('pre');
    pre.className = 'line-numbers';
    var code = document.createElement('code');
    code.className = 'language-javascript';
    code.id = 'wpgen-p5-code';
    pre.appendChild(code);
    wrap.appendChild(pre);
    return code;
  }

  // Función global para pintar el código
  window.wpgenShowCode = function(codeStr){
    var codeEl = ensureViewer();
    // Evita que Prism interprete HTML: usa textContent
    codeEl.textContent = (codeStr || '').toString();
    if (window.Prism && Prism.highlightElement) {
      Prism.highlightElement(codeEl);
    } else if (window.Prism && Prism.highlightAll) {
      Prism.highlightAll();
    }
  };

  // Si hay un textarea oculto con el código previo, lo volcamos (opcional)
  document.addEventListener('DOMContentLoaded', function(){
    var seed = document.getElementById('wpgen-p5-seed');
    if (seed && seed.value) {
      window.wpgenShowCode(seed.value);
    }
  });
})();

