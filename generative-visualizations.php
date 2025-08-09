<?php
/**
 * Plugin Name: Generative Visualizations
 * Description: Crea y gestiona visualizaciones generativas con D3.js o P5.js.
 * Version:     0.2.2
 * Requires at least: 5.0
 * Author:      KGMT Knowledge Services
 */

if ( defined( 'GV_PLUGIN_VERSION' ) ) {
    return;
}
define( 'GV_PLUGIN_VERSION', '0.2.2' );

require_once plugin_dir_path( __FILE__ ) . 'includes/credentials.php';

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// NUEVAS FUNCIONES PARA EXTRAER TEXTO Y CÓDIGO p5
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
  wp_mkdir_p($dir);
  $path = $dir.'/'.$handle.'.js';
  file_put_contents($path, $code);
  $url = trailingslashit($up['baseurl']).'td-sketches/'.$handle.'.js';
  wp_enqueue_script($handle, $url, ['p5'], null, true);
}

function gv_register_cpt() {
    $labels = [
        'name'               => 'Visualizaciones',
        'singular_name'      => 'Visualización',
        'add_new'            => 'Añadir nueva',
        'add_new_item'       => 'Añadir nueva visualización',
        'edit_item'          => 'Editar visualización',
        'new_item'           => 'Nueva visualización',
        'view_item'          => 'Ver visualización',
        'search_items'       => 'Buscar visualizaciones',
        'not_found'          => 'No se encontraron visualizaciones',
        'not_found_in_trash' => 'No hay visualizaciones en la papelera',
    ];

    $args = [
        'label'              => 'Visualizaciones',
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => false,
        'show_in_menu'       => 'upload.php',
        'publicly_queryable' => false,
        'exclude_from_search'=> true,
        'rewrite'            => false,
        'supports'           => ['title', 'thumbnail'],
    ];

    register_post_type( 'visualization', $args );

    // Allow categories for media attachments so saved images can be categorized.
    register_taxonomy_for_object_type( 'category', 'attachment' );
}
add_action( 'init', 'gv_register_cpt' );

function gv_add_metaboxes() {
    add_meta_box( 'gv_data', 'Datos y opciones', 'gv_render_metabox', 'visualization', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'gv_add_metaboxes' );

// Sandbox page for generating custom p5 sketches via OpenAI
function gv_add_sandbox_page() {
    add_submenu_page( 'upload.php', 'Sandbox GV', 'Sandbox GV', 'upload_files', 'gv-sandbox', 'gv_render_sandbox_page' );
}
// Eliminamos la página de sandbox del menú para evitar nuevos ítems
// add_action( 'admin_menu', 'gv_add_sandbox_page' );

function gv_render_sandbox_page() { ?>
    <div class="wrap">
        <h1>Sandbox Generativa</h1>
        <p>Versión: <?php echo esc_html( GV_PLUGIN_VERSION ); ?></p>
        <p><textarea id="gv-sandbox-prompt" rows="3" style="width:100%;" placeholder="Describe la visualización..."></textarea></p>
        <p><button id="gv-sandbox-generate" class="button">Generar</button></p>
        <p><textarea id="gv-sandbox-code" rows="10" style="width:100%;" placeholder="// Código p5.js"></textarea></p>
        <p><button id="gv-sandbox-run" class="button">Vista previa</button></p>
        <div id="gv-sandbox-preview" style="border:1px solid #ccc;min-height:200px;"></div>
        <h2>Guardar en la librería</h2>
        <p><label>Slug: <input type="text" id="gv-sandbox-slug" /></label></p>
        <p><button id="gv-sandbox-save" class="button button-primary">Guardar</button> <span id="gv-sandbox-status"></span></p>
    </div>
<?php }

function gv_render_metabox( $post ) {
    wp_nonce_field( 'gv_save_metabox', 'gv_metabox_nonce' );

    $slug     = get_post_meta( $post->ID, '_gv_slug', true );
    $data     = get_post_meta( $post->ID, '_gv_data_url', true );
    $palette  = get_post_meta( $post->ID, '_gv_palette', true );
    $type     = get_post_meta( $post->ID, '_gv_viz_type', true );
    $library  = get_post_meta( $post->ID, '_gv_library', true );

    ?>
    <p>
        <label>Slug:</label>
        <input type="text" name="gv_slug" id="gv-slug-field" value="<?php echo esc_attr( $slug ); ?>" />
    </p>
    <p>
        <label>Código corto:</label>
        <code id="gv-shortcode">[gv slug="<?php echo esc_attr( $slug ); ?>"]</code>
    </p>
    <p>
        <label>URL de datos (JSON/CSV):</label>
        <input type="url" name="gv_data_url" value="<?php echo esc_url( $data ); ?>" />
    </p>
    <p>
        <label>Tipo de visualización:</label>
        <select name="gv_viz_type">
            <option value="skeleton" <?php selected( $type, 'skeleton' ); ?>>Skeleton</option>
            <option value="circles" <?php selected( $type, 'circles' ); ?>>Círculos</option>
            <option value="bars" <?php selected( $type, 'bars' ); ?>>Barras</option>
            <option value="orbitalRings" <?php selected( $type, 'orbitalRings' ); ?>>Anillos Orbitales (P5)</option>
            <option value="flowField" <?php selected( $type, 'flowField' ); ?>>Campo de Flujo (P5)</option>
        </select>
    </p>
    <p>
        <label>Biblioteca:</label>
        <select name="gv_library">
            <option value="d3" <?php selected( $library, 'd3' ); ?>>D3.js</option>
            <option value="p5" <?php selected( $library, 'p5' ); ?>>P5.js</option>
        </select>
    </p>
    <?php $palettes = gv_get_available_palettes(); ?>
    <p>
        <label>Paleta de colores:</label>
        <select name="gv_palette">
            <?php foreach ( $palettes as $label => $colors ) :
                $value = wp_json_encode( $colors ); ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $palette, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>Vista previa:</p>
    <div id="gv-preview"></div>
    <p>
        <button type="button" id="gv-regenerate">Regenerar</button>
        <button type="button" id="gv-save-media">Guardar en Media</button>
        <span id="gv-status"></span>
    </p>
    <?php
}

function gv_save_metabox( $post_id ) {
    if ( ! isset( $_POST['gv_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['gv_metabox_nonce'], 'gv_save_metabox' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    update_post_meta( $post_id, '_gv_slug', sanitize_title( $_POST['gv_slug'] ?? '' ) );
    update_post_meta( $post_id, '_gv_data_url', esc_url_raw( $_POST['gv_data_url'] ?? '' ) );
    update_post_meta( $post_id, '_gv_palette', sanitize_text_field( $_POST['gv_palette'] ?? '' ) );
    update_post_meta( $post_id, '_gv_viz_type', sanitize_text_field( $_POST['gv_viz_type'] ?? 'skeleton' ) );
    update_post_meta( $post_id, '_gv_library', sanitize_text_field( $_POST['gv_library'] ?? 'd3' ) );
}
add_action( 'save_post', 'gv_save_metabox' );

function gv_auto_publish_visualization( $post_id, $post, $update ) {
    if ( 'visualization' !== $post->post_type ) {
        return;
    }
    if ( 'publish' !== $post->post_status ) {
        remove_action( 'save_post', 'gv_auto_publish_visualization', 10 );
        wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'publish',
        ]);
        add_action( 'save_post', 'gv_auto_publish_visualization', 10, 3 );
    }
}
add_action( 'save_post', 'gv_auto_publish_visualization', 10, 3 );

function gv_shortcode( $atts ) {
    $atts = shortcode_atts([ 'slug' => '' ], $atts, 'gv' );
    $post = get_posts([
        'post_type'  => 'visualization',
        'meta_key'   => '_gv_slug',
        'meta_value' => sanitize_title( $atts['slug'] ),
        'numberposts'=> 1
    ]);

    if ( ! $post ) return '';

    $id        = $post[0]->ID;
    $data_url = get_post_meta( $id, '_gv_data_url', true );
    $palette  = get_post_meta( $id, '_gv_palette', true );
    $type     = get_post_meta( $id, '_gv_viz_type', true );
    $library  = get_post_meta( $id, '_gv_library', true );
    $code     = get_post_meta( $id, '_gv_code', true );

    ob_start(); ?>
    <div class="gv-container" data-id="<?php echo esc_attr( $id ); ?>"
         data-url="<?php echo esc_url( $data_url ); ?>"
         data-type="<?php echo esc_attr( $type ); ?>"
         data-library="<?php echo esc_attr( $library ); ?>"
         data-palette="<?php echo esc_attr( $palette ); ?>"
         <?php if ( $code ) : ?>data-code="<?php echo esc_attr( base64_encode( $code ) ); ?>"<?php endif; ?>></div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'gv', 'gv_shortcode' );

function gv_render_canvas() {
    return '<div id="p5-canvas-container"></div>';
}
add_shortcode( 'gv_canvas', 'gv_render_canvas' );

function gv_get_theme_palette() {
    $palette = [];
    $theme   = get_theme_support( 'editor-color-palette' );
    if ( $theme ) {
        foreach ( $theme[0] as $color ) {
            $palette[] = $color['color'];
        }
    }
    return $palette;
}

function gv_get_available_palettes() {
    $palettes = [ 'Paleta del tema' => gv_get_theme_palette() ];
    $palettes['Category10'] = [ '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b', '#e377c2', '#7f7f7f', '#bcbd22', '#17becf' ];
    return $palettes;
}

function gv_enqueue_scripts() {
    if ( ! is_admin() ) {
        wp_enqueue_script( 'd3', 'https://d3js.org/d3.v7.min.js', [], null, true );
        wp_enqueue_script( 'd3-scale-chromatic', 'https://d3js.org/d3-scale-chromatic.v3.min.js', [ 'd3' ], null, true );
        wp_enqueue_script( 'p5', plugin_dir_url( __FILE__ ) . 'assets/js/p5.min.js', [], '1.9.0', true );
        wp_enqueue_script( 'gifjs', 'https://cdnjs.cloudflare.com/ajax/libs/gif.js/0.2.0/gif.js', [], null, true );
        wp_enqueue_script( 'gv-front', plugin_dir_url(__FILE__) . 'assets/front-end.js', [ 'd3', 'd3-scale-chromatic', 'gifjs', 'p5' ], GV_PLUGIN_VERSION, true );
        wp_enqueue_script( 'gv-sketch', plugin_dir_url( __FILE__ ) . 'assets/js/gv-sketch.js', [ 'p5' ], GV_PLUGIN_VERSION, true );
        wp_localize_script( 'gv-front', 'gvSettings', [
            'palette' => gv_get_theme_palette(),
            'p5Url'   => plugin_dir_url( __FILE__ ) . 'assets/js/p5.min.js',
        ] );
        wp_enqueue_style( 'gv-style', plugin_dir_url(__FILE__) . 'assets/style.css', [], GV_PLUGIN_VERSION );
    }
}
add_action( 'wp_enqueue_scripts', 'gv_enqueue_scripts' );

function gv_enqueue_admin_scripts( $hook ) {
    if ( isset( $_GET['page'] ) && 'gv-sandbox' === $_GET['page'] ) {
        wp_enqueue_script( 'p5', plugin_dir_url( __FILE__ ) . 'assets/js/p5.min.js', [], '1.9.0', true );
        wp_enqueue_script( 'gv-sandbox', plugin_dir_url( __FILE__ ) . 'assets/sandbox.js', [ 'p5' ], GV_PLUGIN_VERSION, true );
        wp_localize_script( 'gv-sandbox', 'gvSandbox', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'p5Url'   => plugin_dir_url( __FILE__ ) . 'assets/js/p5.min.js',
        ] );
        wp_enqueue_style( 'gv-style', plugin_dir_url( __FILE__ ) . 'assets/style.css', [], GV_PLUGIN_VERSION );
        return;
    }
    if ( ! function_exists( 'get_current_screen' ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || 'visualization' !== $screen->post_type ) {
        return;
    }
    wp_enqueue_script( 'd3', 'https://d3js.org/d3.v7.min.js', [], null, true );
    wp_enqueue_script( 'd3-scale-chromatic', 'https://d3js.org/d3-scale-chromatic.v3.min.js', [ 'd3' ], null, true );
    wp_enqueue_script( 'p5', plugin_dir_url( __FILE__ ) . 'assets/js/p5.min.js', [], '1.9.0', true );
    wp_enqueue_script( 'gv-admin', plugin_dir_url(__FILE__) . 'assets/admin-preview.js', [ 'd3', 'd3-scale-chromatic', 'p5' ], GV_PLUGIN_VERSION, true );
    wp_localize_script( 'gv-admin', 'gvSettings', [
        'palette' => gv_get_theme_palette(),
        'p5Url'   => plugin_dir_url( __FILE__ ) . 'assets/js/p5.min.js',
    ] );
}
add_action( 'admin_enqueue_scripts', 'gv_enqueue_admin_scripts' );

function gv_save_image_ajax() {
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( 'permission' );
    }
    $image = $_POST['image'] ?? '';
    if ( ! $image ) {
        wp_send_json_error( 'no_image' );
    }
    $parts = explode( ',', $image );
    $data  = base64_decode( end( $parts ) );
    $filename = 'visualizacion-' . time() . '.png';
    $upload = wp_upload_bits( $filename, null, $data );
    if ( $upload['error'] ) {
        wp_send_json_error( 'upload_error' );
    }
    $filetype = wp_check_filetype( $filename, null );
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name( $filename ),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $attach_id = wp_insert_attachment( $attachment, $upload['file'] );
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    if ( ! term_exists( 'visualizaciones', 'category' ) ) {
        wp_insert_term( 'visualizaciones', 'category' );
    }
    wp_set_object_terms( $attach_id, 'visualizaciones', 'category', true );
    wp_send_json_success( [ 'id' => $attach_id ] );
}
add_action( 'wp_ajax_gv_save_image', 'gv_save_image_ajax' );

function gv_generate_p5_ajax() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'permission' );
    }
    $prompt = sanitize_text_field( $_POST['prompt'] ?? '' );
    if ( ! $prompt ) {
        wp_send_json_error( 'no_prompt' );
    }
    $creds   = wpg_get_openai_credentials();
    $api_key = $creds['api_key'];
    if ( ! $api_key ) {
        wp_send_json_error( 'no_api_key' );
    }
    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode([
            'model'    => 'gpt-4o-mini',
            'messages' => [
                [ 'role' => 'system', 'content' => 'Eres un asistente que genera código p5.js. Devuelve solo el código.' ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
            'temperature' => 0.7,
        ]),
        'timeout' => 45,
    ] );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'api_error' );
    }
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $assistant_message = $data['choices'][0]['message'] ?? [];
    $raw_text = td_get_assistant_text( $assistant_message );
    $code = td_extract_p5_code( $raw_text );

    if ( ! $code ) {
        error_log( '[TD] No se encontró bloque p5.js. Texto recibido: ' . substr( $raw_text, 0, 500 ) );
        wp_send_json( [ 'success' => false, 'data' => [ 'message' => 'La respuesta no contiene código p5.js detectable' ] ] );
    }

    td_enqueue_p5_and_sketch( $code );
    wp_send_json( [ 'success' => true ] );
}
add_action( 'wp_ajax_gv_generate_p5', 'gv_generate_p5_ajax' );

function gv_sandbox_save_ajax() {
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( 'permission' );
    }
    $code   = wp_unslash( $_POST['code'] ?? '' );
    $slug   = sanitize_title( $_POST['slug'] ?? '' );
    $prompt = sanitize_text_field( $_POST['prompt'] ?? '' );
    if ( ! $code || ! $slug ) {
        wp_send_json_error( 'missing' );
    }
    $post_id = wp_insert_post([
        'post_type'   => 'visualization',
        'post_status' => 'publish',
        'post_title'  => $slug,
    ]);
    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( 'insert_error' );
    }
    update_post_meta( $post_id, '_gv_slug', $slug );
    update_post_meta( $post_id, '_gv_library', 'p5' );
    update_post_meta( $post_id, '_gv_viz_type', 'custom' );
    update_post_meta( $post_id, '_gv_code', $code );
    if ( $prompt ) {
        update_post_meta( $post_id, '_gv_prompt', $prompt );
    }
    wp_send_json_success( [ 'id' => $post_id ] );
}
add_action( 'wp_ajax_gv_sandbox_save', 'gv_sandbox_save_ajax' );

// Shortcode to trigger generation. Adds dataset_url attr and renders returned p5.js inside a <script>.
if ( ! function_exists( 'gv_render_p5_shortcode' ) ) {
       /**
        * Usage: [gv prompt="..." dataset_url="https://.../data.csv"]
        */
       function gv_render_p5_shortcode( $atts ) {
               $atts = shortcode_atts( array(
                       'prompt'      => '',
                       'dataset_url' => '',
               ), $atts, 'gv' );

               $prompt = wp_strip_all_tags( (string) $atts['prompt'] );

               if ( '' === $prompt ) {
                       return '<p>Falta el prompt.</p>';
               }

               $client = new GV_OpenAI_Client();
               $code   = $client->generate_p5( $prompt, $atts );

               // Print raw inside <script> without wpautop/kses interfering.
               // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
               return "<div class=\"gv-container\"></div>\n<script>\n{$code}\n</script>";
       }
       add_shortcode( 'gv', 'gv_render_p5_shortcode' );
}

