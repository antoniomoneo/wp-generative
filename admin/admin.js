(function(){
  async function callAssistant() {
    const dataset_url  = document.getElementById('td_dataset_url').value.trim();
    const user_prompt  = document.getElementById('td_user_prompt').value.trim();

    const res = await fetch('/wp-json/wp-generative/v1/ask', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ dataset_url, user_prompt })
    });
    const data = await res.json();
    const apiBox = document.getElementById('td_api_response');
    const codeBox = document.getElementById('td_p5_code');

    if (!res.ok || !data || data.success === false) {
      apiBox.value = JSON.stringify(data, null, 2);
      codeBox.value = '';
      alert('Error en la llamada a OpenAI/Plugin');
      return;
    }

    const rawText = (data.text || '').trim();
    apiBox.value = rawText;

    let extracted = rawText.replace(/^```[a-z]*\n?/i, '').replace(/```$/, '').trim();
    if (!/function\s+setup\s*\(/.test(extracted) && /function\s+setup\s*\(/.test(rawText)) {
      const m = rawText.match(/(\/\/.*\n|.)*?function\s+setup\s*\([\s\S]*$/);
      if (m) extracted = m[0].trim();
    }
    const isP5 = /function\s+setup\s*\(/.test(extracted) || /new\s+p5\s*\(/i.test(extracted);
    if (!isP5 || extracted.length < 20) {
      codeBox.value = '';
      alert('La respuesta no contiene código p5.js válido (no se encontró function setup).');
      return;
    }
    codeBox.value = extracted;
  }
  document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('td_run_btn');
    if (btn) btn.addEventListener('click', function(e){ e.preventDefault(); callAssistant(); });
  });
})();
