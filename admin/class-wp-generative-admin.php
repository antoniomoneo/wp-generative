<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WP_Generative_Admin {
  public function __construct() {
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
    add_action( 'admin_init', array( $this, 'admin_init' ) );
    add_action( 'wp_ajax_td_generate_code', array( $this, 'td_generate_code' ) );
  }

  public function enqueue_assets( $hook ) {
    if ( $hook !== 'toplevel_page_wp-generative' ) { return; }
    wp_enqueue_script( 'wp-generative-admin', plugin_dir_url( __FILE__ ) . 'js/wp-generative-admin.js', array( 'jquery', 'wp-codemirror' ), '1.0', true );
    wp_enqueue_style( 'wp-generative-admin', plugin_dir_url( __FILE__ ) . 'css/wp-generative-admin.css', array(), '1.0' );

    // Habilitar CodeMirror con modo JS
    $settings = wp_enqueue_code_editor( array( 'type' => 'text/javascript' ) );
    if ( $settings ) {
      wp_localize_script( 'wp-generative-admin', 'tdCodeEditorSettings', $settings );
    }

    wp_localize_script( 'wp-generative-admin', 'tdGenerative', array(
      'ajaxUrl' => admin_url( 'admin-ajax.php' ),
      'nonce'   => wp_create_nonce( 'td_generate_nonce' ),
    ) );
  }

  public function register_admin_page() {
    add_menu_page( 'Generador p5.js', 'Generador p5.js', 'manage_options', 'wp-generative', array( $this, 'render_admin' ) );
  }

  public function render_admin() {
    include plugin_dir_path( __FILE__ ) . 'partials/wp-generative-admin-display.php';
  }

  // Guardar opción simple de dataset por comodidad (también se usa desde input)
  public function admin_init() {
    register_setting( 'general', 'td_dataset_url', array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ) );
  }

  // AJAX handler
  public function td_generate_code() {
    check_ajax_referer( 'td_generate_nonce', 'nonce' );
    $dataset_url = isset($_POST['datasetUrl']) ? esc_url_raw( $_POST['datasetUrl'] ) : '';
    $user_prompt = isset($_POST['userPrompt']) ? sanitize_text_field( $_POST['userPrompt'] ) : '';
    if ( empty( $dataset_url ) ) {
      wp_send_json_error( array( 'message' => 'Falta la URL del dataset.' ), 400 );
    }
    if ( empty( $user_prompt ) ) {
      wp_send_json_error( array( 'message' => 'Escribe una descripción para la visualización.' ), 400 );
    }
    require_once plugin_dir_path( __FILE__ ) . '../includes/class-wp-generative-api.php';
    $api = new WP_Generative_API();
    $result = $api->generate_p5_code( $dataset_url, $user_prompt );
    if ( is_wp_error( $result ) ) {
      wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
    }
    wp_send_json_success( array( 'code' => $result ) );
  }
}
