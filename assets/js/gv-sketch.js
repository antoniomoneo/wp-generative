function setup() {
  let canvas = createCanvas(400, 400);
  canvas.parent('p5-canvas-container');
}

function draw() {
  background(220);
  ellipse(width / 2, height / 2, 100, 100);
}
