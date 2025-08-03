<?php
/**
 * Plugin Name: Generative Visualizations
 * Description: Crea y gestiona visualizaciones generativas con D3.js o P5.js.
 * Version:     0.1.0
 * Author:      KGMT Knowledge Services
 */

if ( ! defined( 'ABSPATH' ) ) exit;

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
        'label'        => 'Visualizaciones',
        'labels'       => $labels,
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => 'gv-settings',
        'supports'     => ['title', 'thumbnail'],
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

    $slug    = get_post_meta( $post->ID, '_gv_slug', true );
    $data    = get_post_meta( $post->ID, '_gv_data_url', true );
    $palette = get_post_meta( $post->ID, '_gv_palette', true );

    ?>
    <p>
        <label>Slug:</label>
        <input type="text" name="gv_slug" value="<?php echo esc_attr( $slug ); ?>" />
    </p>
    <p>
        <label>URL de datos (JSON/CSV):</label>
        <input type="url" name="gv_data_url" value="<?php echo esc_url( $data ); ?>" />
    </p>
    <p>
        <label>Paleta de colores (JSON o lista):</label>
        <input type="text" name="gv_palette" value="<?php echo esc_attr( $palette ); ?>" />
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
}
add_action( 'save_post', 'gv_save_metabox' );

function gv_shortcode( $atts ) {
    $atts = shortcode_atts([ 'slug' => '' ], $atts, 'gv' );
    $post = get_posts([
        'post_type'  => 'visualization',
        'meta_key'   => '_gv_slug',
        'meta_value' => sanitize_title( $atts['slug'] ),
        'numberposts'=> 1
    ]);

    if ( ! $post ) return '';

    $id       = $post[0]->ID;
    $data_url = get_post_meta( $id, '_gv_data_url', true );
    $palette  = get_post_meta( $id, '_gv_palette', true );

    ob_start(); ?>
    <div class="gv-container" data-id="<?php echo esc_attr( $id ); ?>"
         data-url="<?php echo esc_url( $data_url ); ?>"
         data-palette="<?php echo esc_attr( $palette ); ?>"></div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'gv', 'gv_shortcode' );

function gv_enqueue_scripts() {
    if ( ! is_admin() ) {
        wp_enqueue_script( 'd3', 'https://d3js.org/d3.v7.min.js', [], null, true );
        wp_enqueue_script( 'gv-front', plugin_dir_url(__FILE__) . 'assets/front-end.js', ['d3'], '0.1.0', true );
        wp_enqueue_style( 'gv-style', plugin_dir_url(__FILE__) . 'assets/style.css', [], '0.1.0' );
    }
}
add_action( 'wp_enqueue_scripts', 'gv_enqueue_scripts' );

function gv_register_menu_page() {
    add_menu_page(
        'Visualizaciones Generativas',
        'Generative',
        'manage_options',
        'gv-settings',
        'gv_render_settings_page',
        'dashicons-chart-area'
    );
}

function gv_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Visualizaciones Generativas</h1>
        <p>Aquí puedes administrar el plugin.</p>
        <p><strong>Desarrollador:</strong> KGMT Knowledge Services</p>
    </div>
    <?php
}
add_action( 'admin_menu', 'gv_register_menu_page' );
