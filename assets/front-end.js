document.addEventListener('DOMContentLoaded', () => {
  const containers = document.querySelectorAll('.gv-container');
  containers.forEach(async el => {
    const url     = el.dataset.url;
    const palette = el.dataset.palette ? JSON.parse(el.dataset.palette) : [];

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
       .attr('fill', (d,i) => palette[i % palette.length] || 'steelblue');
  });
});
