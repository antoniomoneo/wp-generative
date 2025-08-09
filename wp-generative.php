<?php
/**
 * Plugin Name:       WP Generative p5.js Assistant
 * Description:       Envía prompts a un asistente de OpenAI y genera código p5.js con controles dinámicos.
 * Version:           1.3.2
 * Author:            KGMT Knowledge Services
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WPG_PLUGIN_VERSION' ) ) {
    define( 'WPG_PLUGIN_VERSION', '1.3.2' );
}

// Autoload / includes.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-gv-dataset.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-gv-openai.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-dataset-setting.php';
require_once __DIR__ . '/includes/class-wpg-openai.php';
require_once __DIR__ . '/includes/class-wpg-visualization.php';
require_once __DIR__ . '/admin/class-wpg-admin.php';

// Inicializa la administración del plugin sin depender del hook plugins_loaded
WPG_Admin::get_instance();


// ===== Utilidades para extraer texto y código =====
function td_get_assistant_text(array $assistant_message): string {
  $out = '';
  if (!empty($assistant_message['content']) && is_array($assistant_message['content'])) {
    foreach ($assistant_message['content'] as $chunk) {
      if (isset($chunk['text']['value']) && is_string($chunk['text']['value'])) {
        $out .= $chunk['text']['value'];
      } elseif (isset($chunk['text']) && is_string($chunk['text'])) {
        $out .= $chunk['text'];
      }
    }
  }
  if ($out === '' && isset($assistant_message['text']) && is_string($assistant_message['text'])) {
    $out = $assistant_message['text'];
  }
  return trim($out);
}

function td_extract_p5_code(string $text): ?string {
  if ($text === '') return null;
  if (preg_match('/```(?:js|javascript|p5)?\s*([\s\S]*?)```/i', $text, $m)) {
    $code = trim($m[1]);
  } else {
    $looks_like_sketch = stripos($text, 'function setup') !== false && stripos($text, 'function draw') !== false;
    $code = $looks_like_sketch ? trim($text) : null;
  }
  if (!$code) return null;
  $code = preg_replace("/^\xEF\xBB\xBF/", '', $code);
  $code = str_replace("\r\n", "\n", $code);
  return $code !== '' ? $code : null;
}

function td_enqueue_p5_and_sketch(string $code): void {
  wp_enqueue_script('p5', 'https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js', [], null, true);
  if (!headers_sent()) {
    wp_add_inline_script('p5', $code);
    return;
  }
  $handle = 'td-sketch-'.wp_generate_uuid4();
  $up = wp_upload_dir();
  $dir = trailingslashit($up['basedir']).'td-sketches';
  if (!is_dir($dir)) { wp_mkdir_p($dir); }
  $path = $dir.'/'.$handle.'.js';
  file_put_contents($path, $code);
  $url = trailingslashit($up['baseurl']).'td-sketches/'.$handle.'.js';
  wp_enqueue_script($handle, $url, ['p5'], null, true);
}

// ===== FIN utilidades =====

/**
 * Shortcode: [p5js_visual data_url="" user_prompt="" data_format="auto|csv|json" width="800" height="500" cache="30"]
 */
add_shortcode('p5js_visual', function ($atts) {
    $atts = shortcode_atts([
        'data_url'    => '',
        'dataset_url' => '',
        'user_prompt' => '',
        'data_format' => 'auto',
        'width'       => 800,
        'height'      => 500,
        'cache'       => 30,
    ], $atts, 'p5js_visual');

    // Allow dataset_url as alias of data_url and fallback to default option.
    $data_url = $atts['dataset_url'] ? $atts['dataset_url'] : $atts['data_url'];
    if (empty($data_url)) {
        $data_url = get_option('gv_default_dataset_url', '');
    }
    $data_url    = esc_url_raw($data_url);
    $user_prompt = sanitize_text_field($atts['user_prompt']);
    $data_format = in_array($atts['data_format'], ['auto','csv','json'], true) ? $atts['data_format'] : 'auto';
    $width       = intval($atts['width']);
    $height      = intval($atts['height']);
    $cache_min   = max(0, intval($atts['cache']));

    if (empty($data_url) || empty($user_prompt)) {
        return '<p>' . esc_html__('Faltan parámetros obligatorios (data_url, user_prompt).', 'wp-generative') . '</p>';
    }

    do_action('wpgen_enqueue_p5js');

    $cache_key = 'wpgen_p5_' . md5(implode('|', [$data_url,$data_format,$user_prompt,$width,$height]));
    if ($cache_min > 0) {
        $cached = get_transient($cache_key);
        if ($cached) {
            return wpgen_render_p5_block($cached);
        }
    }

    $args = [
        'data_url'    => $data_url,
        'data_format' => $data_format,
        'user_prompt' => $user_prompt,
        'width'       => $width,
        'height'      => $height,
    ];
    $js = wpgen_get_p5js_from_openai($args);
    if (is_wp_error($js)) {
        return '<p>' . esc_html__('Error generando visualización: ', 'wp-generative') . esc_html($js->get_error_message()) . '</p>';
    }

    if ($cache_min > 0) {
        set_transient($cache_key, $js, $cache_min * MINUTE_IN_SECONDS);
    }

    return wpgen_render_p5_block($js);
});

function wpgen_render_p5_block($js_code) {
    $id = 'p5-holder-' . wp_generate_uuid4();
    $html  = '<div class="wpgen-p5-holder" id="' . esc_attr($id) . '"></div>';
    $html .= "<!-- Código p5.js generado por OpenAI (wp-generative) -->\n";
    $html .= "<script>(function(){\n" . $js_code . "\n})();</script>";
    return $html;
}

require_once plugin_dir_path(__FILE__) . 'includes/enqueue.php';
require_once plugin_dir_path(__FILE__) . 'includes/openai.php';
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
}
if (is_admin() && file_exists(plugin_dir_path(__FILE__).'includes/test-extractor.php')) {
    require_once plugin_dir_path(__FILE__).'includes/test-extractor.php';
}

require_once plugin_dir_path(__FILE__) . 'inc/api.php';

add_action('admin_menu', function(){
  add_menu_page(
    'WP Generative', 'WP Generative', 'manage_options',
    'wp-generative', 'tdg_render_admin_page', 'dashicons-art', 58
  );
});

function tdg_render_admin_page() {
  include plugin_dir_path(__FILE__) . 'admin/admin-page.php';
}

add_action('admin_enqueue_scripts', function($hook){
  if ($hook === 'toplevel_page_wp-generative') {
    wp_enqueue_script('tdg-admin', plugin_dir_url(__FILE__) . 'admin/admin.js', [], '1.0', true);
  }
});

