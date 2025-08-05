/**
 * Front-end logic for Generative Visualizations
 * Supports multiple visualization types including a skeleton animation.
 */

document.addEventListener('DOMContentLoaded', () => {
  const containers = document.querySelectorAll('.gv-container');
  containers.forEach(async el => {
    const dataUrl = el.dataset.url;
    let paletteAttr = el.dataset.palette;
    let palette = [];
    try { palette = paletteAttr ? JSON.parse(paletteAttr) : []; } catch(e) {}
    if (!palette.length && window.gvSettings && Array.isArray(window.gvSettings.palette)) {
      palette = window.gvSettings.palette;
    }
    if (!palette.length) {
      const bodyColor = getComputedStyle(document.body).color || 'steelblue';
      palette = [bodyColor];
    }
    const type = el.dataset.type || 'skeleton';
    const library = el.dataset.library || 'd3';

    let data;
    if (dataUrl.toLowerCase().endsWith('.csv')) {
      data = await d3.csv(dataUrl, d3.autoType);
    } else {
      data = await fetch(dataUrl).then(r => r.json());
    }

    if (typeof window.drawVisualization === 'function') {
      window.drawVisualization(el, data, palette);
      return;
    }

    if (library === 'p5') {
      drawP5(el, data, palette);
      return;
    }

    switch(type) {
      case 'circles':
        drawCircles(el, data, palette);
        break;
      case 'bars':
        drawBars(el, data, palette);
        break;
      default:
        drawSkeleton(el, data);
    }
  });
});

function drawCircles(el, data, palette) {
  const svg = d3.select(el).append('svg').attr('width', 400).attr('height', 300);
  svg.selectAll('circle')
     .data(data)
     .enter()
     .append('circle')
     .attr('cx', (d,i) => i * 30 + 20)
     .attr('cy', 150)
     .attr('r', d => d.valor || d.mean || 5)
     .attr('fill', (d,i) => palette[i % palette.length]);
}

function drawBars(el, data, palette) {
  const svg = d3.select(el).append('svg').attr('width', 400).attr('height', 300);
  const x = d3.scaleBand().domain(data.map((d,i)=>i)).range([0,400]).padding(0.1);
  const y = d3.scaleLinear().domain([0, d3.max(data, d=>d.valor || d.mean || 0)]).nice().range([300,0]);
  svg.selectAll('rect').data(data).enter().append('rect')
    .attr('x',(d,i)=>x(i))
    .attr('y',d=>y(d.valor||d.mean||0))
    .attr('width',x.bandwidth())
    .attr('height',d=>300 - y(d.valor||d.mean||0))
    .attr('fill',(d,i)=>palette[i%palette.length]);
}

// Skeleton visualization with animation and GIF capture
function drawSkeleton(el, data) {
  const width = 900, height = 450;
  const svg = d3.select(el).append('svg')
    .attr('width', width)
    .attr('height', height);

  const margin = {top:40,right:40,bottom:40,left:40};
  const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
  const innerW = width - margin.left - margin.right;
  const innerH = height - margin.top - margin.bottom;

  const xScale = d3.scalePoint().domain(data.map(d=>d.year)).range([0, innerW]).padding(0.5);
  const meanExtent = d3.extent(data, d=>Math.abs(d.mean));
  const ribLengthScale = d3.scaleLinear().domain(meanExtent).range([10,80]);
  const movAvgExtent = d3.extent(data, d=>d.movAvg);
  const colorScale = d3.scaleSequential(d3.interpolateMagma).domain(movAvgExtent);

  g.append('line')
    .attr('class','spine')
    .attr('x1',0).attr('y1',innerH/2)
    .attr('x2',innerW).attr('y2',innerH/2);

  const yearGroup = g.selectAll('.year-group').data(data).enter().append('g')
    .attr('class','year-group')
    .attr('transform',d=>`translate(${xScale(d.year)}, ${innerH/2})`);

  const ribGenerator = d3.line().curve(d3.curveBasis);
  yearGroup.append('path').attr('class','rib rib-top');
  yearGroup.append('path').attr('class','rib rib-bottom');
  yearGroup.selectAll('.rib').attr('stroke', d => colorScale(d.movAvg));

  const animationSpeed = 0.001;
  d3.timer(function(elapsed){
    const breath = (Math.sin(elapsed*animationSpeed)+1)/2*0.25+0.85;
    yearGroup.select('.rib-top').attr('d',d=>{
      const length = ribLengthScale(Math.abs(d.mean))*breath;
      return ribGenerator([[0,0],[length,-length*0.8],[0,-length*1.5]]);
    });
    yearGroup.select('.rib-bottom').attr('d',d=>{
      const length = ribLengthScale(Math.abs(d.mean))*breath;
      return ribGenerator([[0,0],[length,length*0.8],[0,length*1.5]]);
    });
    if(isRecording && (elapsed - lastCapture >= frameDelay)){
      lastCapture = elapsed;
      captureFrame(svg.node());
    }
  });

  drawLegend(el, movAvgExtent, colorScale);
  addControls(el, svg.node());

  let isRecording = false;
  let lastCapture = 0;
  const frameDelay = 100;
  let gifRecorder, captureCanvas, captureCtx;

  function addControls(container, svgNode){
    const controls = document.createElement('div');
    controls.className = 'gv-controls';
    controls.innerHTML = '<button class="gv-embed">Embeber Gráfico</button> <button class="gv-gif">Descargar GIF</button> <span class="gv-status"></span>';
    container.appendChild(controls);
    const embedBtn = controls.querySelector('.gv-embed');
    const gifBtn = controls.querySelector('.gv-gif');
    const status = controls.querySelector('.gv-status');

    embedBtn.addEventListener('click', () => {
      const code = `<iframe src="${window.location.href}" width="940" height="650" style="border:none;"></iframe>`;
      window.prompt('Copia y pega este código para embeber:', code);
    });

    gifBtn.addEventListener('click', () => {
      if(isRecording) return;
      isRecording = true;
      status.textContent = 'Generando GIF...';
      captureCanvas = document.createElement('canvas');
      captureCanvas.width = width;
      captureCanvas.height = height;
      captureCtx = captureCanvas.getContext('2d');
      gifRecorder = new GIF({ workers:2, workerScript:'https://cdnjs.cloudflare.com/ajax/libs/gif.js/0.2.0/gif.worker.js' });
      gifRecorder.on('finished', blob => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'skeleton.gif'; a.click();
        URL.revokeObjectURL(url);
        status.textContent = '';
        isRecording = false;
      });
      let frames = 0;
      const maxFrames = 30;
      const interval = setInterval(()=>{
        captureFrame(svgNode);
        frames++;
        if(frames>=maxFrames){
          clearInterval(interval);
          gifRecorder.render();
        }
      }, frameDelay);
    });
  }

  function captureFrame(svgNode){
    const style = document.createElement('style');
    style.textContent = `.rib { fill: none; stroke-width: 2.5; stroke-linecap: round; } .spine { stroke: #666; stroke-width: 3; }`;
    const cloned = svgNode.cloneNode(true);
    cloned.insertBefore(style, cloned.firstChild);
    const svgString = new XMLSerializer().serializeToString(cloned);
    const img = new Image();
    img.onload = function(){
      captureCtx.clearRect(0,0,captureCanvas.width,captureCanvas.height);
      captureCtx.drawImage(img,0,0);
      gifRecorder.addFrame(captureCtx, {copy:true, delay:frameDelay});
    };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgString);
  }
}

function drawP5(el, data, palette){
  const container = document.createElement('div');
  el.appendChild(container);
  new p5(p=>{
    p.setup = function(){
      p.createCanvas(400,300);
      p.noLoop();
    };
    p.draw = function(){
      p.background(0);
      p.stroke(palette[0] || '#fff');
      p.noFill();
      p.beginShape();
      const step = p.width/(data.length-1);
      data.forEach((d,i)=>{
        const v = d.valor || d.mean || d.Anomaly || 0;
        const x = i*step;
        const y = p.height/2 - v*20 + p.random(-5,5);
        p.vertex(x,y);
      });
      p.endShape();
    };
  }, container);
}

function drawLegend(container, domain, scale){
  const legendWidth = 300, legendHeight = 10;
  const legend = d3.select(container).append('div').attr('class','gv-legend');
  const legendSvg = legend.append('svg')
      .attr('width', legendWidth + 40)
      .attr('height', legendHeight + 40)
      .append('g').attr('transform','translate(20,10)');
  const defs = legendSvg.append('defs');
  const lg = defs.append('linearGradient').attr('id','gv-gradient');
  const colorScaleForLegend = d3.scaleLinear().domain([0,1]).range(domain);
  lg.selectAll('stop').data(d3.range(10).map(i=>i/9)).enter().append('stop')
    .attr('offset',d=>`${d*100}%`)
    .attr('stop-color',d=>scale(colorScaleForLegend(d)));
  legendSvg.append('rect').attr('width',legendWidth).attr('height',legendHeight).style('fill','url(#gv-gradient)');
  const legendScale = d3.scaleLinear().domain(domain).range([0,legendWidth]);
  const legendAxis = d3.axisBottom(legendScale).ticks(5).tickFormat(d=>d.toFixed(2)+"°C");
  legendSvg.append('g').attr('transform',`translate(0,${legendHeight})`).call(legendAxis);
}

