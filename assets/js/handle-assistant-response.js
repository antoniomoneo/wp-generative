// Este archivo recibe la respuesta del asistente (p5.js) y la inserta en la UI
function onAssistantCodeReceived(codigoGenerado){
  var proxyBase = (typeof WPG_Ajax !== 'undefined' && WPG_Ajax.proxyBase) || null;
  var out = (window.wpgen && wpgen.transformP5)
    ? wpgen.transformP5(codigoGenerado, { proxyBase: proxyBase, makeResponsive: true })
    : { code: codigoGenerado };
  if (out.warnings && out.warnings.length) console.warn('[wpgen][p5]', out.warnings);
  // Ahora: usamos el visor con Prism (numeración + resaltado)
  if (window.wpgenShowCode) {
    window.wpgenShowCode(out.code);
  } else {
    // Fallback por si el visor no cargó aún
    const fallback = document.getElementById('wpgen-p5-code');
    if (fallback) {
      fallback.textContent = out.code;
    }
  }
}

// Si hay un flujo que ya llamaba a onAssistantCodeReceived, no toques.
// Si no existe, expórtala global:
window.onAssistantCodeReceived = window.onAssistantCodeReceived || onAssistantCodeReceived;

