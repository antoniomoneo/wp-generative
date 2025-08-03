document.addEventListener('DOMContentLoaded', () => {
  const preview = document.getElementById('gv-preview');
  if (!preview) return;

  const render = async () => {
    const urlField = document.querySelector('input[name="gv_data_url"]');
    const paletteField = document.querySelector('input[name="gv_palette"]');
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
    const svg = d3.select(preview).append('svg').attr('width', 400).attr('height', 300);
    svg.selectAll('circle')
       .data(data)
       .enter()
       .append('circle')
       .attr('cx', (d,i) => i * 30 + 20)
       .attr('cy', 150)
       .attr('r', d => d.valor)
       .attr('fill', (d,i) => palette[i % palette.length]);
  };

  const urlField = document.querySelector('input[name="gv_data_url"]');
  const paletteField = document.querySelector('input[name="gv_palette"]');
  urlField.addEventListener('input', render);
  paletteField.addEventListener('input', render);
  render();
});
