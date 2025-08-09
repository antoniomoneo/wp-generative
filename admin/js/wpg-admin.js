(function ($) {
    // === WP Generative: Actualizar código con TODO el dataset ===
    async function ensurePapaParseLoaded() {
        if (window.Papa) return;
        await new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js';
            s.onload = resolve;
            s.onerror = () => reject(new Error('No se pudo cargar PapaParse'));
            document.head.appendChild(s);
        });
    }

    function detectYearColumn(rows) {
        if (!rows?.length) return null;
        const first = rows[0];
        const keys = Object.keys(first);
        const lower = keys.map((k) => k.toLowerCase());
        const candidates = ['año', 'ano', 'year', 'fecha', 'date'];
        for (const c of candidates) {
            const i = lower.indexOf(c);
            if (i !== -1) return keys[i];
        }
        for (const k of keys) {
            for (let r = 0; r < Math.min(rows.length, 50); r++) {
                const n = Number(rows[r][k]);
                if (Number.isFinite(n) && n >= 1800 && n <= 2100) return k;
            }
        }
        return null;
    }

    function buildJsLiteral(rows, mode = 'rows', yearCol = null) {
        if (mode === 'years') {
            const col = yearCol || detectYearColumn(rows) || 'year';
            const set = new Set();
            for (const r of rows) {
                const y = r[col];
                const n = Number(y);
                if (Number.isFinite(n)) set.add(n);
            }
            const arr = Array.from(set).sort((a, b) => a - b);
            return `const years = [${arr.join(',')}]` + ';';
        }
        const cols = rows.length ? Object.keys(rows[0]) : [];
        const sanitized = rows.map((r) => {
            const o = {};
            for (const c of cols) o[c] = r[c];
            return o;
        });
        return `const data = ${JSON.stringify(sanitized)}` + ';';
    }

    function replaceDataOrYearsChunk(sourceCode, newChunk) {
        const reData = /(let|const|var)\s+data\s*=\s*\[[\s\S]*?\];/m;
        if (reData.test(sourceCode)) return sourceCode.replace(reData, newChunk);
        const reYears = /(let|const|var)\s+years\s*=\s*\[[\s\S]*?\];/m;
        if (reYears.test(sourceCode)) return sourceCode.replace(reYears, newChunk);
        return `${newChunk}\n${sourceCode}`;
    }

    async function fetchFullCsv(url) {
        await ensurePapaParseLoaded();
        return new Promise((resolve, reject) => {
            Papa.parse(url, {
                download: true,
                header: true,
                dynamicTyping: true,
                skipEmptyLines: true,
                worker: true,
                complete: (res) => resolve(res.data),
                error: (err) => reject(err),
            });
        });
    }

    document.getElementById('wpgen-btn-update-code')?.addEventListener('click', async () => {
        const codeEl = document.getElementById('wpgen-code');
        const urlEl =
            document.getElementById('wpgen-dataset-url') || document.getElementById('wpg_dataset');
        const hintEl = document.getElementById('wpgen-update-hint');

        const source = codeEl?.value || '';
        const datasetUrl = urlEl?.value?.trim();

        if (!source) {
            alert('No hay código para actualizar.');
            return;
        }
        if (!datasetUrl) {
            alert('Falta la URL del dataset.');
            return;
        }

        try {
            if (hintEl) hintEl.textContent = 'Descargando y parseando dataset…';
            const rows = await fetchFullCsv(datasetUrl);
            if (!rows?.length) throw new Error('El CSV no tiene filas.');

            let newChunk = buildJsLiteral(rows, 'rows');

            const hasData = /(let|const|var)\s+data\s*=/.test(source);
            const hasYears = /(let|const|var)\s+years\s*=/.test(source);
            if (!hasData && hasYears) {
                const yearCol = detectYearColumn(rows);
                newChunk = buildJsLiteral(rows, 'years', yearCol);
            }

            const updated = replaceDataOrYearsChunk(source, newChunk);
            codeEl.value = updated;
            if (hintEl) hintEl.textContent = 'Código actualizado con el dataset completo.';
        } catch (e) {
            console.error(e);
            alert('Error: ' + (e?.message || e));
            if (hintEl) hintEl.textContent = 'Hubo un problema al actualizar el código.';
        }
    });

    const btnGenerate = $('#wpg-generate');
    const btnRun = $('#wpg-run');
    const btnSave = $('#wpg-save');
    const textareaCode = $('#wpgen-code');
    const textareaRequest = $('#wpg_request');
    const textareaResponse = $('#wpg_response');
    let lastCode = '';
    const datasetList = $('#wpg_dataset_list');
    const promptField = $('#wpg_prompt');
    const datasetField = $('#wpg_dataset');
    const defaultPrompt = 'crea el código p5.js para una visualización generativa del dataset en la URL.';
    promptField.val(defaultPrompt);

    datasetField.on('change', function () {
        const url = $(this).val();
        if (!url) {
            promptField.val(defaultPrompt);
            return;
        }
        fetch(url)
            .then(res => {
                const ctype = res.headers.get('content-type') || '';
                if (ctype.includes('application/json')) {
                    return res.json().then(data => JSON.stringify(data, null, 2));
                }
                return res.text();
            })
            .then(text => {
                const maxBytes = 60000;
                if (text.length > maxBytes) {
                    text = text.slice(0, maxBytes) + '\n... [truncated]\n';
                }
                const lines = text.split(/\r?\n/);
                const maxLines = 30;
                if (lines.length > maxLines) {
                    text = lines.slice(0, maxLines).join('\n') + '\n... [truncated]\n';
                }
                promptField.val(`${defaultPrompt}\n\n${text}`);
            })
            .catch(() => {
                promptField.val(defaultPrompt);
            });
    });

    if (datasetList.length) {
        fetch('https://api.github.com/repos/antoniomoneo/Datasets/git/trees/main?recursive=1')
            .then(res => res.json())
            .then(data => {
                if (!data.tree) {
                    return;
                }
                data.tree
                    .filter(item => item.type === 'blob' && /\.(csv|json)$/i.test(item.path))
                    .forEach(item => {
                        const rawUrl = `https://raw.githubusercontent.com/antoniomoneo/Datasets/main/${item.path}`;
                        datasetList.append(`<option value="${rawUrl}">${item.path}</option>`);
                    });
            })
            .catch(() => {});
    }
    btnGenerate.on('click', function (e) {
        e.preventDefault();
        btnGenerate.prop('disabled', true);
        textareaResponse.val('');
        textareaRequest.val('');

        const data = {
            action: 'wpg_generate_code',
            _ajax_nonce: WPG_Ajax.nonce,
            prompt: $('#wpg_prompt').val(),
            dataset_url: $('#wpg_dataset').val(),
        };

        textareaRequest.val(
            JSON.stringify({ url: WPG_Ajax.ajax_url, data: data }, null, 2)
        );

        $.post(WPG_Ajax.ajax_url, data)
            .done(res => {
                if (res.success) {
                    textareaResponse.val(JSON.stringify(res, null, 2));
                    lastCode = res.data.code;
                    textareaCode.val(lastCode);
                    renderSketch(lastCode);
                } else {
                    textareaResponse.val(res.data.api_response || JSON.stringify(res, null, 2));
                    let msg = res.data.message || 'Error';
                    if (res.data.api_response) {
                        msg += '\n\n' + res.data.api_response;
                    }
                    alert(msg);
                }
            })
            .fail(() => {
                textareaResponse.val('Error en la solicitud.');
                alert('Error en la solicitud.');
            })
            .always(() => btnGenerate.prop('disabled', false));
    });

    btnRun.on('click', function (e) {
        e.preventDefault();
        const code = textareaCode.val();
        if (code.trim() === '') {
            alert('No hay código para ejecutar');
            return;
        }
        renderSketch(code);
    });

    btnSave.on('click', function (e) {
        e.preventDefault();
        const slug = $('#wpg_slug').val();
        if (!slug || !lastCode) {
            alert('Faltan datos para guardar');
            return;
        }
        btnSave.prop('disabled', true);
        $.post(WPG_Ajax.ajax_url, {
            action: 'wpg_save_visualization',
            _ajax_nonce: WPG_Ajax.nonce,
            slug: slug,
            code: lastCode,
            prompt: $('#wpg_prompt').val(),
        }).done(res => {
            if (res.success) {
                $('#wpg-save-status').text('Guardado');
            } else {
                alert(res.data.message || 'Error al guardar');
            }
        }).fail(() => alert('Error en la solicitud.')).always(() => btnSave.prop('disabled', false));
    });

    function renderSketch(code) {
        lastCode = code;
        textareaCode.val(code);
        $('#wpg-preview').empty();
        $('#wpg-controls').empty();

        const iframe = $('<iframe style="width:100%;height:100%;border:0;"></iframe>');
        $('#wpg-preview').append(iframe);

        const regex = /(?:let|var|const)\s+([a-zA-Z_]\w*)\s*=\s*([^;]+)/g;
        let match;
        const vars = [];
        while ((match = regex.exec(code)) !== null) {
            const rawValue = match[2].trim();
            let type = 'text';
            if (!isNaN(parseFloat(rawValue))) {
                type = 'number';
            } else if (/^\[(?:\s*['"][^'"]+['"]\s*,?)+\]$/.test(rawValue)) {
                type = 'select';
            }
            vars.push({ name: match[1], value: rawValue, type: type });
        }

        vars.forEach(v => {
            const wrapper = $('<div style="margin-bottom:1em;"></div>');
            const label = $('<label>').text(v.name + ': ');
            let input;

            if (v.type === 'number') {
                input = $('<input type="range" min="0" max="' + (parseFloat(v.value) * 3) +
                          '" value="' + parseFloat(v.value) + '">');
            } else if (v.type === 'select') {
                let options = [];
                try {
                    options = eval(v.value);
                } catch (e) {}
                input = $('<select></select>');
                options.forEach(opt => {
                    input.append('<option value="' + opt + '">' + opt + '</option>');
                });
            } else {
                input = $('<input type="text" value="' + v.value.replace(/["']/g, '') + '">');
            }

            input.on('input change', () => updateSketch(v.name, input.val(), v.type));
            wrapper.append(label).append(input);
            $('#wpg-controls').append(wrapper);
        });

        renderIframe(code);

        function updateSketch(varName, value, type) {
            if (type !== 'number') {
                value = `'${value}'`;
            }
            code = code.replace(
                new RegExp('(let|var|const)\\s+' + varName + '\\s*=\\s*[^;]+'),
                `$1 ${varName} = ${value}`
            );
            lastCode = code;
            textareaCode.val(code);
            renderIframe(code);
        }

        function renderIframe(c) {
            const safe = c.replace(/<\/script>/g, '<\\/script>');
            const doc = `<!DOCTYPE html><html><head><script src="${WPG_Ajax.p5_url}"></script></head><body><script>${safe}</script></body></html>`;
            iframe[0].srcdoc = doc;
        }
    }
})(jQuery);

