/**
 * Sandbox for generating and previewing p5 sketches via OpenAI
 */

document.addEventListener('DOMContentLoaded', () => {
  const promptEl = document.getElementById('gv-sandbox-prompt');
  const codeEl = document.getElementById('gv-sandbox-code');
  const generateBtn = document.getElementById('gv-sandbox-generate');
  const runBtn = document.getElementById('gv-sandbox-run');
  const saveBtn = document.getElementById('gv-sandbox-save');
  const preview = document.getElementById('gv-sandbox-preview');
  const slugEl = document.getElementById('gv-sandbox-slug');
  const statusEl = document.getElementById('gv-sandbox-status');

  function runSketch(){
    preview.innerHTML = '';
    let code = codeEl.value;
    if (!code.trim()) return;
    const proxyBase = (gvSandbox && gvSandbox.proxyBase) || null;
    if (window.wpgen && wpgen.transformP5) {
      const out = wpgen.transformP5(code, { proxyBase: proxyBase, makeResponsive: true });
      if (out.warnings && out.warnings.length) console.warn('[wpgen][p5]', out.warnings);
      code = out.code;
    }
    const safe = code.replace(/<\/script>/g, '<\\/script>');
    const doc = `<!DOCTYPE html><html><head><script src="${gvSandbox.p5Url}"></script></head><body><script>${safe}</script></body></html>`;
    const iframe = document.createElement('iframe');
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.style.border = '0';
    preview.appendChild(iframe);
    iframe.srcdoc = doc;
  }

  if (generateBtn) {
    generateBtn.addEventListener('click', async () => {
      statusEl.textContent = 'Generando...';
      const body = new FormData();
      body.append('action', 'gv_generate_p5');
      body.append('prompt', promptEl.value);
      try {
        const res = await fetch(gvSandbox.ajaxUrl, { method: 'POST', body }).then(r=>r.json());
        if (res.success) {
          let code = res.data.code || '';
          const proxyBase = (gvSandbox && gvSandbox.proxyBase) || null;
          if (window.wpgen && wpgen.transformP5) {
            const out = wpgen.transformP5(code, { proxyBase: proxyBase, makeResponsive: true });
            if (out.warnings && out.warnings.length) console.warn('[wpgen][p5]', out.warnings);
            code = out.code;
          }
          codeEl.value = code;
          statusEl.textContent = 'Generado';
        } else {
          statusEl.textContent = 'Error';
        }
      } catch(err){
        statusEl.textContent = 'Error';
      }
      setTimeout(()=>statusEl.textContent='',2000);
    });
  }

  if (runBtn) runBtn.addEventListener('click', runSketch);

  if (saveBtn) {
    saveBtn.addEventListener('click', async () => {
      statusEl.textContent = 'Guardando...';
      const body = new FormData();
      body.append('action','gv_sandbox_save');
      body.append('slug', slugEl.value);
      body.append('code', codeEl.value);
      body.append('prompt', promptEl.value);
      try {
        const res = await fetch(gvSandbox.ajaxUrl, {method:'POST', body}).then(r=>r.json());
        statusEl.textContent = res.success ? 'Guardado' : 'Error';
      } catch(err){
        statusEl.textContent = 'Error';
      }
      setTimeout(()=>statusEl.textContent='',2000);
    });
  }
});
