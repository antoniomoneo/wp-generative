<?php
// Test manual del extractor: /?td_test_extractor=1 (solo admin)
add_action('init', function(){
  if (!isset($_GET['td_test_extractor']) || !current_user_can('manage_options')) return;

  $assistant_sin_bloque = [
    'content' => [
      [
        'type' => 'text',
        'text' => ['value' => 'let data=[];function setup(){createCanvas(100,100);}function draw(){background(220);}']
      ]
    ]
  ];

  $assistant_con_bloque = [
    'content' => [
      [
        'type' => 'text',
        'text' => ['value' => "-----BEGIN_P5JS-----\nlet data=[];function setup(){createCanvas(100,100);}function draw(){background(220);}\n-----END_P5JS-----"]
      ]
    ]
  ];

  if (!function_exists('td_get_assistant_text') || !function_exists('td_extract_p5_code')) {
    echo 'Faltan funciones td_get_assistant_text/td_extract_p5_code'; exit;
  }

  header('Content-Type: text/html; charset=utf-8');
  echo '<h1>TD Test Extractor</h1>';

  foreach (['SIN bloque' => $assistant_sin_bloque, 'CON bloque' => $assistant_con_bloque] as $label => $msg) {
    $raw = td_get_assistant_text($msg);
    $code = td_extract_p5_code($raw);
    echo "<h2>Test $label</h2>";
    echo $code ? "<pre>".esc_html($code)."</pre>" : "<strong>NO detectado</strong>";
  }
  exit;
});
