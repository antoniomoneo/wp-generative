(function(w){
  'use strict';
  w.wpgen = w.wpgen || {};
  /**
   * Transform raw p5.js code into a clean sketch.
   * @param {string} raw Raw code from AI.
   * @param {Object} [opts]
   * @param {Array} [opts.injectData] Array to inject as const data.
   * @param {string} [opts.proxyBase] Base URL for loadTable proxy.
   * @param {boolean} [opts.makeResponsive] Make canvas responsive.
   * @returns {{code:string, actions:string[], warnings:string[]}}
   */
  function transformP5(raw, opts){
    opts = opts || {};
    var code = (raw || '').toString();
    var actions = [];
    var warnings = [];

    // remove markdown fences
    var before = code;
    code = code.replace(/```(?:javascript|js|html)?\s*([\s\S]*?)```/gi, '$1');
    code = code.replace(/```/g, '');
    if(code !== before) actions.push('markdown');

    // strip script tags and generic HTML tags
    before = code;
    code = code.replace(/<\s*script[^>]*>[\s\S]*?<\s*\/script\s*>/gi, '');
    code = code.replace(/<[^>]+>/g, '');
    if(code !== before) actions.push('html');

    // remove p5 imports or script includes
    before = code;
    code = code.replace(/^[^\n]*import[^\n]*['"]p5['"][^\n]*$/gim, '');
    code = code.replace(/<script[^>]*p5[^>]*><\/script>/gi, '');
    if(code !== before) actions.push('p5-import');

    // inject data block
    if(Array.isArray(opts.injectData)){
      var dataChunk = 'const data = ' + JSON.stringify(opts.injectData) + ';';
      if(/(?:const|let|var)\s+data\s*=/.test(code)){
        code = code.replace(/(?:const|let|var)\s+data\s*=\s*\[[\s\S]*?\];?/m, dataChunk);
      } else {
        code = dataChunk + '\n' + code;
      }
      actions.push('inject-data');
    }

    // rewrite loadTable to proxy
    if(opts.proxyBase){
      var reLT = /loadTable\s*\(\s*(['"])(https?:\/\/[^'"\)]+)\1\s*,\s*(['"])csv\3\s*,\s*(['"])header\4\s*\)/gi;
      if(reLT.test(code)){
        code = code.replace(reLT, function(_m, q, url){
          return "loadTable('" + opts.proxyBase + '?format=csv&url=' + encodeURIComponent(url) + "','csv','header')";
        });
        actions.push('proxy-loadTable');
      }
    }

    // make canvas responsive
    if(opts.makeResponsive){
      var canvRe = /createCanvas\s*\(\s*\d+\s*,\s*\d+\s*\)/;
      if(canvRe.test(code)){
        code = code.replace(canvRe, 'createCanvas(windowWidth, windowHeight)');
        if(!/function\s+windowResized\s*\(/.test(code)){
          code += '\nfunction windowResized(){ resizeCanvas(windowWidth, windowHeight); }\n';
        }
        actions.push('responsive');
      }
    }

    if(!/function\s+setup\s*\(/.test(code)) warnings.push('Falta setup()');
    if(!/createCanvas\s*\(/.test(code)) warnings.push('Falta createCanvas()');

    return { code: code.trim(), actions: actions, warnings: warnings };
  }

  if(!w.wpgen.transformP5){
    w.wpgen.transformP5 = transformP5;
  }
})(window);
