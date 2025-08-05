(function ($) {
    const form = $('#wpg-settings');
    const btnSend = $('#wpg-send');

    form.on('submit', function (e) {
        e.preventDefault();
        btnSend.prop('disabled', true);

        const data = {
            action: 'wpg_generate_code',
            _ajax_nonce: WPG_Ajax.nonce,
            api_key: $('#wpg_api_key').val(),
            assistant_id: $('#wpg_assistant_id').val(),
            prompt: $('#wpg_prompt').val(),
        };

        $.post(WPG_Ajax.ajax_url, data)
            .done(res => {
                if (res.success) {
                    const code = res.data.code;
                    renderSketch(code);
                } else {
                    alert(res.data.message);
                }
            })
            .fail(() => alert('Error en la solicitud.'))
            .always(() => btnSend.prop('disabled', false));
    });

    function renderSketch(code) {
        $('#wpg-preview').empty();
        $('#wpg-controls').empty();

        const regex = /(?:let|var|const)\s+([a-zA-Z_]\w*)\s*=\s*([^;]+)/g;
        let match;
        const vars = [];
        while ((match = regex.exec(code)) !== null) {
            vars.push({ name: match[1], value: match[2].trim() });
        }

        vars.forEach(v => {
            const wrapper = $('<div style="margin-bottom:1em;"></div>');
            const label = $('<label>').text(v.name + ': ');
            let input;

            if (!isNaN(parseFloat(v.value))) {
                input = $('<input type="range" min="0" max="' + (parseFloat(v.value) * 3) +
                          '" value="' + parseFloat(v.value) + '">');
            } else {
                input = $('<input type="text" value="' + v.value + '">');
            }

            input.on('input change', () => updateSketch(v.name, input.val()));
            wrapper.append(label).append(input);
            $('#wpg-controls').append(wrapper);
        });

        new p5(p => eval(code), document.getElementById('wpg-preview'));

        function updateSketch(varName, value) {
            const newCode = code.replace(
                new RegExp('(let|var|const)\\s+' + varName + '\\s*=\\s*[^;]+'),
                `$1 ${varName} = ${value}`
            );
            $('#wpg-preview').empty();
            new p5(p => eval(newCode), document.getElementById('wpg-preview'));
        }
    }
})(jQuery);
