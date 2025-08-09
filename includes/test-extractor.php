<?php
add_action('init', function(){
  if (!isset($_GET['td_test_extractor']) || !current_user_can('manage_options')) return;

  // Simula respuesta SIN fences
  $assistant_no_fences = [
    'content' => [
      [
        'type' => 'text',
        'text' => ['value' => 'let data=[];function setup(){createCanvas(100,100);}function draw(){background(220);}']
      ]
    ]
  ];

  // Simula respuesta CON fences
  $assistant_with_fences = [
    'content' => [
      [
        'type' => 'text',
        'text' => ['value' => "```js\nlet data=[];function setup(){createCanvas(100,100);}function draw(){background(220);}\n```"]
      ]
    ]
  ];

  if (!function_exists('td_get_assistant_text') || !function_exists('td_extract_p5_code')) {
    echo 'Faltan funciones td_get_assistant_text/td_extract_p5_code'; exit;
  }

  header('Content-Type: text/html; charset=utf-8');
  echo '<h1>TD Test Extractor</h1>';

  foreach ([
    'SIN fences' => $assistant_no_fences,
    'CON fences' => $assistant_with_fences
  ] as $label => $msg) {
    $raw = td_get_assistant_text($msg);
    $code = td_extract_p5_code($raw);
    echo "<h2>Test $label</h2>";
    echo $code ? "<pre>".esc_html($code)."</pre>" : "<strong>NO detectado</strong>";
  }
  exit;
});
