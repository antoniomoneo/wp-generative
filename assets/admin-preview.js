/**
 * Admin preview for Generative Visualizations
 */

document.addEventListener('DOMContentLoaded', () => {
  const preview = document.getElementById('gv-preview');
  if (!preview) return;

  const render = async () => {
    const urlField = document.querySelector('input[name="gv_data_url"]');
    const paletteField = document.querySelector('select[name="gv_palette"]');
    const typeField = document.querySelector('select[name="gv_viz_type"]');
    const libraryField = document.querySelector('select[name="gv_library"]');
    const url = urlField.value;
    let paletteAttr = paletteField.value;
    let palette = [];
    try { palette = paletteAttr ? JSON.parse(paletteAttr) : []; } catch(e) {}
    if (!palette.length && window.gvSettings && Array.isArray(window.gvSettings.palette)) {
      palette = window.gvSettings.palette;
    }
    if (!palette.length) {
      const bodyColor = getComputedStyle(document.body).color || 'steelblue';
      palette = [bodyColor];
    }
    preview.innerHTML = '';
    if (!url) {
      preview.innerHTML = '<p>Introduce una URL de datos para ver la vista previa.</p>';
      return;
    }
    try {
      let data;
      if (url.toLowerCase().endsWith('.csv')) {
        data = await d3.csv(url, d3.autoType);
      } else {
        data = await fetch(url).then(r => r.json());
      }
      const type = typeField.value || 'skeleton';
      const library = libraryField.value || 'd3';

      if (library === 'p5') {
        await ensureP5();
        switch(type) {
          case 'orbitalRings':
            drawOrbitalRings(preview, data, palette);
            break;
          case 'flowField':
            drawFlowField(preview, data, palette);
            break;
          default:
            drawP5Preview(preview, data, palette);
        }
      } else {
        switch(type) {
          case 'circles':
            drawCircles(preview, data, palette);
            break;
          case 'bars':
            drawBars(preview, data, palette);
            break;
          case 'orbitalRings':
            drawOrbitalRings(preview, data, palette);
            break;
          case 'flowField':
            drawFlowField(preview, data, palette);
            break;
          default:
            drawSkeletonPreview(preview, data);
        }
      }
    } catch(err) {
      preview.innerHTML = '<p>No se pudo cargar la vista previa.</p>';
      console.error(err);
    }
  };

  const urlField = document.querySelector('input[name="gv_data_url"]');
  const paletteField = document.querySelector('select[name="gv_palette"]');
  const typeField = document.querySelector('select[name="gv_viz_type"]');
  const libraryField = document.querySelector('select[name="gv_library"]');
  const slugField = document.querySelector('input[name="gv_slug"]');
  const shortcodeEl = document.getElementById('gv-shortcode');
  const updateShortcode = () => {
    if (shortcodeEl) {
      shortcodeEl.textContent = `[gv slug="${slugField.value.trim()}"]`;
    }
  };
  [urlField, typeField, libraryField].forEach(f=>f.addEventListener('input', render));
  paletteField.addEventListener('change', render);
  slugField.addEventListener('input', updateShortcode);
  updateShortcode();
  render();
  const saveBtn = document.getElementById('gv-save-media');
  const regenBtn = document.getElementById('gv-regenerate');
  const status = document.getElementById('gv-status');
  if (regenBtn) regenBtn.addEventListener('click', render);
  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      status.textContent = 'Guardando...';
      const img = await captureImage();
      if(!img){ status.textContent=''; return; }
      const body = new FormData();
      body.append('action','gv_save_image');
      body.append('image', img);
      fetch(window.ajaxurl, {method:'POST', body}).then(r=>r.json()).then(res=>{
        status.textContent = res.success ? 'Guardado' : 'Error';
        setTimeout(()=>status.textContent='',2000);
      }).catch(()=>{status.textContent='Error'; setTimeout(()=>status.textContent='',2000);});
    });
  }
});

function drawCircles(el, data, palette) {
  const svg = d3.select(el).append('svg').attr('width', 300).attr('height', 200);
  svg.selectAll('circle')
     .data(data)
     .enter()
     .append('circle')
     .attr('cx', (d,i) => i * 20 + 10)
     .attr('cy', 100)
     .attr('r', d => d.valor || d.mean || 5)
     .attr('fill', (d,i) => palette[i % palette.length]);
}

function drawBars(el, data, palette) {
  const svg = d3.select(el).append('svg').attr('width', 300).attr('height', 200);
  const x = d3.scaleBand().domain(data.map((d,i)=>i)).range([0,300]).padding(0.1);
  const y = d3.scaleLinear().domain([0, d3.max(data, d=>d.valor || d.mean || 0)]).nice().range([200,0]);
  svg.selectAll('rect').data(data).enter().append('rect')
    .attr('x',(d,i)=>x(i))
    .attr('y',d=>y(d.valor||d.mean||0))
    .attr('width',x.bandwidth())
    .attr('height',d=>200 - y(d.valor||d.mean||0))
    .attr('fill',(d,i)=>palette[i%palette.length]);
}

function drawOrbitalRings(el, data, palette) {
  const container = document.createElement('div');
  el.appendChild(container);

  new p5(p => {
    let maxVal;

    p.setup = function() {
      p.createCanvas(300, 300);
      p.background('#1a1a1a');
      p.noFill();
      p.strokeWeight(1.5);

      const values = data.map(d => Math.abs(d.valor || d.mean || d.Anomaly || 0));
      maxVal = p.max(values);

      const centerX = p.width / 2;
      const centerY = p.height / 2;
      const maxRadius = p.min(centerX, centerY) * 0.9;

      data.forEach((d, i) => {
        const value = Math.abs(d.valor || d.mean || d.Anomaly || 0);
        const radius = p.map(i, 0, data.length, 10, maxRadius);
        const color = p.color(palette[i % palette.length]);
        p.stroke(color);
        const perturbation = p.map(value, 0, maxVal, 0, 30);

        p.beginShape();
        for (let angle = 0; angle < 360; angle += 4) {
          const noiseFactor = p.noise(i * 10, angle * 0.1);
          const r = radius + p.map(noiseFactor, 0, 1, -perturbation, perturbation);
          const x = centerX + r * p.cos(p.radians(angle));
          const y = centerY + r * p.sin(p.radians(angle));
          p.vertex(x, y);
        }
        p.endShape(p.CLOSE);
      });
    };
  }, container);
}

function drawFlowField(el, data, palette) {
  const container = document.createElement('div');
  el.appendChild(container);

  new p5(p => {
    let particles = [];
    const numParticles = 500;
    const noiseScale = 0.01;

    p.setup = function() {
      p.createCanvas(300, 200);
      p.background('#f5f5f5');

      for (let i = 0; i < numParticles; i++) {
        particles.push({
          pos: p.createVector(p.random(p.width), p.random(p.height)),
          vel: p.createVector(0, 0),
          acc: p.createVector(0, 0),
          maxSpeed: p.random(1, 3),
          color: p.color(palette[i % palette.length]),
        });
      }
      p.noLoop();
      p.draw();
    };

    p.draw = function() {
      for (let i = 0; i < 200; i++) {
        particles.forEach((particle, index) => {
          const dataInfluence = p.map(data[index % data.length].movAvg || 0, -1, 1, -0.5, 0.5);
          const angle = p.noise(particle.pos.x * noiseScale, particle.pos.y * noiseScale) * p.TWO_PI * 2 + dataInfluence;

          particle.acc.add(p.createVector(p.cos(angle), p.sin(angle)));

          particle.vel.add(particle.acc);
          particle.vel.limit(particle.maxSpeed);
          particle.pos.add(particle.vel);
          particle.acc.mult(0);

          particle.color.setAlpha(10);
          p.stroke(particle.color);
          p.point(particle.pos.x, particle.pos.y);

          if (particle.pos.x > p.width) particle.pos.x = 0;
          if (particle.pos.x < 0) particle.pos.x = p.width;
          if (particle.pos.y > p.height) particle.pos.y = 0;
          if (particle.pos.y < 0) particle.pos.y = p.height;
        });
      }
    };
  }, container);
}

function drawSkeletonPreview(el, data) {
  d3.select(el).style('background-color', '#1a1a1a').style('padding', '20px');
  const width = 300, height = 200;
  const svg = d3.select(el).append('svg').attr('width', width).attr('height', height);
  const margin = {top:20,right:20,bottom:20,left:20};
  const g = svg.append('g').attr('transform', `translate(${margin.left},${margin.top})`);
  const innerW = width - margin.left - margin.right;
  const innerH = height - margin.top - margin.bottom;
  const xScale = d3.scalePoint().domain(data.map(d=>d.year)).range([0, innerW]).padding(0.5);
  const meanExtent = d3.extent(data, d=>Math.abs(d.mean));
  const ribLengthScale = d3.scaleLinear().domain(meanExtent).range([5,40]);
  const movAvgExtent = d3.extent(data, d=>d.movAvg);
  const colorScale = d3.scaleSequential(d3.interpolateMagma).domain(movAvgExtent);

  g.append('line')
    .attr('class','spine')
    .attr('stroke', '#666')
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
    yearGroup.selectAll('.rib').attr('stroke', d => colorScale(d.movAvg));
    const breath = (Math.sin(elapsed*animationSpeed)+1)/2*0.25+0.85;
    yearGroup.select('.rib-top').attr('d',d=>{
      const length = ribLengthScale(Math.abs(d.mean))*breath;
      return ribGenerator([[0,0],[length,-length*0.8],[0,-length*1.5]]);
    });
    yearGroup.select('.rib-bottom').attr('d',d=>{
      const length = ribLengthScale(Math.abs(d.mean))*breath;
      return ribGenerator([[0,0],[length,length*0.8],[0,length*1.5]]);
    });
  });
}

function drawP5Preview(el, data, palette){
  const container = document.createElement('div');
  el.appendChild(container);
  new p5(p=>{
    p.setup = function(){
      p.createCanvas(300,200);
      p.noLoop();
    };
    p.draw = function(){
      p.background(255);
      p.stroke(palette[0] || '#000');
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

async function captureImage(){
  const svg = document.querySelector('#gv-preview svg');
  if(svg){
    const canvas = document.createElement('canvas');
    canvas.width = svg.viewBox.baseVal.width || svg.width.baseVal.value;
    canvas.height = svg.viewBox.baseVal.height || svg.height.baseVal.value;
    const ctx = canvas.getContext('2d');
    const data = new XMLSerializer().serializeToString(svg);
    const img = new Image();
    return new Promise(resolve=>{
      img.onload = function(){
        ctx.drawImage(img,0,0);
        resolve(canvas.toDataURL('image/png'));
      };
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(data);
    });
  }
  const canvas = document.querySelector('#gv-preview canvas');
  if(canvas){
    return canvas.toDataURL('image/png');
  }
  return null;
}

async function ensureP5(){
  if (typeof p5 !== 'undefined') return;
  if (window.gvSettings && window.gvSettings.p5Url) {
    await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = window.gvSettings.p5Url;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }
}

