// Este archivo recibe la respuesta del asistente (p5.js) y la inserta en la UI
function onAssistantCodeReceived(codigoGenerado){
  // Ahora: usamos el visor con Prism (numeración + resaltado)
  if (window.wpgenShowCode) {
    window.wpgenShowCode(codigoGenerado);
  } else {
    // Fallback por si el visor no cargó aún
    const fallback = document.getElementById('wpgen-p5-code');
    if (fallback) {
      fallback.textContent = codigoGenerado;
    }
  }
}

// Si hay un flujo que ya llamaba a onAssistantCodeReceived, no toques.
// Si no existe, expórtala global:
window.onAssistantCodeReceived = window.onAssistantCodeReceived || onAssistantCodeReceived;

