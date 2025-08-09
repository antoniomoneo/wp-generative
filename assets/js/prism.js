(function(window){
  function escapeHtml(str){
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  function highlight(code){
    var escaped = escapeHtml(code);
    // Comentarios
    escaped = escaped.replace(/(\/\/.*?$)/gm, '<span class="token comment">$1</span>');
    // Cadenas
    escaped = escaped.replace(/("[^"]*"|'[^']*'|`[^`]*`)/g, '<span class="token string">$1</span>');
    // Números
    escaped = escaped.replace(/\b(\d+(?:\.\d+)?)\b/g, '<span class="token number">$1</span>');
    // Palabras clave
    escaped = escaped.replace(/\b(const|let|var|function|return|if|else|for|while|do|break|continue|switch|case|default|new|try|catch|finally|throw|class|extends|super|import|from|export|as)\b/g, '<span class="token keyword">$1</span>');
    return escaped;
  }
  function highlightElement(el){
    if(!el) return;
    var code = el.textContent || '';
    el.innerHTML = highlight(code);
  }
  function highlightAll(){
    var els = document.querySelectorAll('code[class*="language-"]');
    for(var i=0;i<els.length;i++){ highlightElement(els[i]); }
  }
  window.Prism = {
    highlight: highlight,
    highlightElement: highlightElement,
    highlightAll: highlightAll
  };
})(window);
