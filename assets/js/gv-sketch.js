let respuestaInput;
let respuestas = [];

function setup() {
  const canvas = createCanvas(400, 200);
  canvas.parent('p5-canvas-container');

  // Crear cuadro de texto
  respuestaInput = createInput();
  respuestaInput.parent('p5-canvas-container');
  respuestaInput.position(10, 40);

  // Botón para guardar la respuesta
  const botonGuardar = createButton('Guardar respuesta');
  botonGuardar.parent('p5-canvas-container');
  botonGuardar.position(respuestaInput.x + respuestaInput.width + 10, 40);
  botonGuardar.mousePressed(guardarRespuesta);

  textSize(16);
}

function draw() {
  background(220);
  text('Escribe la respuesta y haz clic en "Guardar respuesta":', 10, 30);

  // Mostrar las respuestas guardadas
  for (let i = 0; i < respuestas.length; i++) {
    text(respuestas[i], 10, 80 + i * 20);
  }
}

function guardarRespuesta() {
  const texto = respuestaInput.value().trim();
  if (texto !== '') {
    respuestas.push(texto);
    respuestaInput.value('');
  }
}

