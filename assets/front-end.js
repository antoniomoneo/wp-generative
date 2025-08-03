document.addEventListener('DOMContentLoaded', () => {
  const containers = document.querySelectorAll('.gv-container');
  containers.forEach(async el => {
    const url         = el.dataset.url;
    let paletteAttr   = el.dataset.palette;
    let palette       = [];
    try { palette = paletteAttr ? JSON.parse(paletteAttr) : []; } catch(e) {}
    if (!palette.length && window.gvSettings && Array.isArray(window.gvSettings.palette)) {
      palette = window.gvSettings.palette;
    }
    if (!palette.length) {
      const bodyColor = getComputedStyle(document.body).color || 'steelblue';
      palette = [bodyColor];
    }

    const data = await fetch(url).then(r => r.json());

    const svg = d3.select(el).append('svg')
        .attr('width', 400)
        .attr('height', 300);

    svg.selectAll('circle')
       .data(data)
       .enter()
       .append('circle')
       .attr('cx', (d,i) => i * 30 + 20)
       .attr('cy', 150)
       .attr('r', d => d.valor)
       .attr('fill', (d,i) => palette[i % palette.length]);
  });
});
