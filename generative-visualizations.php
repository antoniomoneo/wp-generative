<?php
/**
 * Plugin Name: Generative Visualizations
 * Description: Crea y gestiona visualizaciones generativas con D3.js o P5.js.
 * Version:     0.1.1
 * Requires at least: 5.0
 * Author:      KGMT Knowledge Services
 */

if ( defined( 'GV_PLUGIN_VERSION' ) ) {
    return;
}
define( 'GV_PLUGIN_VERSION', '0.1.1' );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        'show_ui'            => true,
        'show_in_menu'       => 'upload.php',
        'publicly_queryable' => false,
        'exclude_from_search'=> true,
        'rewrite'            => false,
        'supports'           => ['title', 'thumbnail'],
    ];

    register_post_type( 'visualization', $args );
}
add_action( 'init', 'gv_register_cpt' );

function gv_add_metaboxes() {
    add_meta_box( 'gv_data', 'Datos y opciones', 'gv_render_metabox', 'visualization', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'gv_add_metaboxes' );

function gv_render_metabox( $post ) {
    wp_nonce_field( 'gv_save_metabox', 'gv_metabox_nonce' );

    $slug     = get_post_meta( $post->ID, '_gv_slug', true );
    $data     = get_post_meta( $post->ID, '_gv_data_url', true );
    $palette  = get_post_meta( $post->ID, '_gv_palette', true );
    $type     = get_post_meta( $post->ID, '_gv_viz_type', true );

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

    ob_start(); ?>
    <div class="gv-container" data-id="<?php echo esc_attr( $id ); ?>"
         data-url="<?php echo esc_url( $data_url ); ?>"
         data-type="<?php echo esc_attr( $type ); ?>"
         data-palette="<?php echo esc_attr( $palette ); ?>"></div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'gv', 'gv_shortcode' );

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
        wp_enqueue_script( 'gifjs', 'https://cdnjs.cloudflare.com/ajax/libs/gif.js/0.2.0/gif.js', [], null, true );
        wp_enqueue_script( 'gv-front', plugin_dir_url(__FILE__) . 'assets/front-end.js', [ 'd3', 'd3-scale-chromatic', 'gifjs' ], GV_PLUGIN_VERSION, true );
        wp_localize_script( 'gv-front', 'gvSettings', [ 'palette' => gv_get_theme_palette() ] );
        wp_enqueue_style( 'gv-style', plugin_dir_url(__FILE__) . 'assets/style.css', [], GV_PLUGIN_VERSION );
    }
}
add_action( 'wp_enqueue_scripts', 'gv_enqueue_scripts' );

function gv_enqueue_admin_scripts( $hook ) {
    if ( ! function_exists( 'get_current_screen' ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || 'visualization' !== $screen->post_type ) {
        return;
    }
    wp_enqueue_script( 'd3', 'https://d3js.org/d3.v7.min.js', [], null, true );
    wp_enqueue_script( 'd3-scale-chromatic', 'https://d3js.org/d3-scale-chromatic.v3.min.js', [ 'd3' ], null, true );
    wp_enqueue_script( 'gv-admin', plugin_dir_url(__FILE__) . 'assets/admin-preview.js', [ 'd3', 'd3-scale-chromatic' ], GV_PLUGIN_VERSION, true );
    wp_localize_script( 'gv-admin', 'gvSettings', [ 'palette' => gv_get_theme_palette() ] );
}
add_action( 'admin_enqueue_scripts', 'gv_enqueue_admin_scripts' );

