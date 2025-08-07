(function ($) {
    const form = $('#wpg-sandbox-form');
    const btnGenerate = $('#wpg-generate');
    const btnSave = $('#wpg-save');
    let lastCode = '';

    form.on('submit', function (e) {
        e.preventDefault();
        btnGenerate.prop('disabled', true);

        const data = {
            action: 'wpg_generate_code',
            _ajax_nonce: WPG_Ajax.nonce,
            prompt: $('#wpg_prompt').val(),
            dataset_url: $('#wpg_dataset').val(),
        };

        $.post(WPG_Ajax.ajax_url, data)
            .done(res => {
                if (res.success) {
                    lastCode = res.data.code;
                    renderSketch(lastCode);
                } else {
                    alert(res.data.message);
                }
            })
            .fail(() => alert('Error en la solicitud.'))
            .always(() => btnGenerate.prop('disabled', false));
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
        $('#wpg-preview').empty();
        $('#wpg-controls').empty();

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

        new p5(p => eval(code), document.getElementById('wpg-preview'));

        function updateSketch(varName, value, type) {
            if (type !== 'number') {
                value = `'${value}'`;
            }
            const newCode = code.replace(
                new RegExp('(let|var|const)\\s+' + varName + '\\s*=\\s*[^;]+'),
                `$1 ${varName} = ${value}`
            );
            $('#wpg-preview').empty();
            lastCode = newCode;
            code = newCode;
            new p5(p => eval(newCode), document.getElementById('wpg-preview'));
        }
    }
})(jQuery);

