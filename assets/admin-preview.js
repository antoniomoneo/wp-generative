/**
 * Admin preview for Generative Visualizations
 */

document.addEventListener('DOMContentLoaded', () => {
  const preview = document.getElementById('gv-preview');
  if (!preview) return;

  const render = async () => {
    const urlField = document.querySelector('input[name="gv_data_url"]');
    const paletteField = document.querySelector('input[name="gv_palette"]');
    const typeField = document.querySelector('select[name="gv_viz_type"]');
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
    if (!url) return;
    const data = await fetch(url).then(r => r.json());
    const type = typeField.value || 'skeleton';

    switch(type) {
      case 'circles':
        drawCircles(preview, data, palette);
        break;
      case 'bars':
        drawBars(preview, data, palette);
        break;
      default:
        drawSkeletonPreview(preview, data);
    }
  };

  const urlField = document.querySelector('input[name="gv_data_url"]');
  const paletteField = document.querySelector('input[name="gv_palette"]');
  const typeField = document.querySelector('select[name="gv_viz_type"]');
  [urlField, paletteField, typeField].forEach(f=>f.addEventListener('input', render));
  render();
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

function drawSkeletonPreview(el, data) {
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
  });
}

