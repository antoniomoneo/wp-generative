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
  let p5Instance = null;

  function runSketch(){
    if (p5Instance) { p5Instance.remove(); p5Instance = null; }
    preview.innerHTML = '';
    const code = codeEl.value;
    if (!code.trim()) return;
    try {
      p5Instance = new p5(new Function('p', code), preview);
    } catch(e){ console.error(e); }
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
          codeEl.value = res.data.code;
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
