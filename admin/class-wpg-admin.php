<?php
defined( 'ABSPATH' ) || exit;

class WPG_Admin {
    private static $instance = null;

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_wpg_generate_code', [ $this, 'ajax_generate_code' ] );
        add_action( 'wp_ajax_wpg_save_visualization', [ $this, 'ajax_save_visualization' ] );
    }

    public static function get_instance() {
        return self::$instance ?: self::$instance = new self();
    }

    private function get_api_key() {
        $api_key = get_option( 'wpg_api_key', '' );
        if ( $api_key ) {
            return $api_key;
        }
        if ( defined( 'OPENAI_API_KEY' ) ) {
            return OPENAI_API_KEY;
        }
        $env_key = getenv( 'OPENAI_API_KEY' );
        return $env_key ? $env_key : '';
    }

    private function get_assistant_id() {
        $assistant = get_option( 'wpg_assistant_id', '' );
        if ( $assistant ) {
            return $assistant;
        }
        if ( defined( 'OPENAI_ASSISTANT_ID' ) ) {
            return OPENAI_ASSISTANT_ID;
        }
        $env_assistant = getenv( 'OPENAI_ASSISTANT_ID' );
        return $env_assistant ? $env_assistant : '';
    }

    public function register_menu() {
        $main_slug = 'wpg-settings';

        add_menu_page(
            __( 'Gen Viz', 'wpg' ),
            __( 'Gen Viz', 'wpg' ),
            'manage_options',
            $main_slug,
            [ $this, 'render_settings_page' ],
            'dashicons-admin-site',
            25
        );

        add_submenu_page(
            $main_slug,
            __( 'Settings', 'wpg' ),
            __( 'Settings', 'wpg' ),
            'manage_options',
            $main_slug,
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            $main_slug,
            __( 'API Settings', 'wpg' ),
            __( 'API Settings', 'wpg' ),
            'manage_options',
            'wpg-api-settings',
            [ $this, 'render_api_settings_page' ]
        );

        add_submenu_page(
            $main_slug,
            __( 'Sandbox', 'wpg' ),
            __( 'Sandbox', 'wpg' ),
            'manage_options',
            'wpg-sandbox',
            [ $this, 'render_sandbox_page' ]
        );

        add_submenu_page(
            $main_slug,
            __( 'Librería', 'wpg' ),
            __( 'Librería', 'wpg' ),
            'manage_options',
            'wpg-library',
            [ $this, 'render_library_page' ]
        );

        remove_submenu_page( $main_slug, $main_slug );
    }

    public function enqueue_assets( $hook ) {
        $is_sandbox_page = ( isset( $_GET['page'] ) && 'wpg-sandbox' === $_GET['page'] );
        if ( 'wpg-settings_page_wpg-sandbox' !== $hook && ! $is_sandbox_page ) {
            return;
        }
        wp_enqueue_script(
            'p5',
            plugin_dir_url( __FILE__ ) . '../assets/js/p5.min.js',
            [],
            '1.9.0',
            true
        );
        wp_enqueue_script(
            'wpg-admin-js',
            plugin_dir_url( __FILE__ ) . 'js/wpg-admin.js',
            [ 'jquery', 'p5' ],
            '1.3.0',
            true
        );
        wp_localize_script( 'wpg-admin-js', 'WPG_Ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpg_nonce' ),
            'p5_url'   => plugin_dir_url( __FILE__ ) . '../assets/js/p5.min.js',
        ] );
    }

    public function render_api_settings_page() {
        $saved            = false;
        $api_key_editable = ! defined( 'OPENAI_API_KEY' ) && ! getenv( 'OPENAI_API_KEY' );
        if ( isset( $_POST['wpg_api_submit'] ) && check_admin_referer( 'wpg_save_api' ) ) {
            if ( $api_key_editable ) {
                update_option( 'wpg_api_key', sanitize_text_field( $_POST['wpg_api_key'] ?? '' ) );
            }
            update_option( 'wpg_assistant_id', sanitize_text_field( $_POST['wpg_assistant_id'] ?? '' ) );
            $saved = true;
        }
        $api_key      = $this->get_api_key();
        $assistant_id = $this->get_assistant_id();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'API Settings', 'wpg' ); ?></h1>
            <?php if ( $saved ) : ?>
                <div class="updated notice"><p><?php esc_html_e( 'Opciones guardadas.', 'wpg' ); ?></p></div>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field( 'wpg_save_api' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="wpg_api_key">API Key</label></th>
                        <td>
                            <?php if ( $api_key_editable ) : ?>
                                <input type="password" id="wpg_api_key" name="wpg_api_key" value="<?php echo esc_attr( $api_key ); ?>" size="40" />
                            <?php else : ?>
                                <input type="text" id="wpg_api_key" value="********" size="40" readonly />
                                <p class="description"><?php esc_html_e( 'Definida por el entorno.', 'wpg' ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpg_assistant_id">Assistant ID</label></th>
                        <td><input type="text" id="wpg_assistant_id" name="wpg_assistant_id" value="<?php echo esc_attr( $assistant_id ); ?>" size="40" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Guardar', 'wpg' ), 'primary', 'wpg_api_submit' ); ?>
            </form>
        </div>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Settings', 'wpg' ); ?></h1>
            <p><?php esc_html_e( 'No hay ajustes disponibles.', 'wpg' ); ?></p>
        </div>
        <?php
    }

    public function render_sandbox_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Sandbox', 'wpg' ); ?></h1>
            <form id="wpg-sandbox-form">
                <p><label for="wpg_prompt"><?php esc_html_e( 'Prompt', 'wpg' ); ?></label></p>
                <p><textarea id="wpg_prompt" rows="4" cols="50"></textarea></p>
                <p><label for="wpg_dataset"><?php esc_html_e( 'Dataset URL', 'wpg' ); ?></label></p>
                <p><input type="text" id="wpg_dataset" size="50" /></p>
                <?php submit_button( __( 'Generar', 'wpg' ), 'primary', 'wpg-generate' ); ?>
            </form>
            <div id="wpg-editor" style="display:flex;gap:1em;margin-top:2em;">
                <textarea id="wpg_code" style="width:50%;height:400px;"></textarea>
                <div id="wpg-preview" style="flex:1;height:400px;border:1px solid #ccc;"></div>
            </div>
            <p><button id="wpg-run" class="button"><?php esc_html_e( 'Vista previa', 'wpg' ); ?></button></p>
            <div id="wpg-controls" style="margin-top:1em;"></div>
            <h2><?php esc_html_e( 'Guardar visualización', 'wpg' ); ?></h2>
            <p><label for="wpg_slug">Slug</label> <input type="text" id="wpg_slug" /></p>
            <p><button id="wpg-save" class="button button-primary"><?php esc_html_e( 'Guardar', 'wpg' ); ?></button> <span id="wpg-save-status"></span></p>
        </div>
        <?php
    }

    public function render_library_page() {
        $posts = get_posts( [ 'post_type' => 'wpg_viz', 'numberposts' => -1 ] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Librería', 'wpg' ); ?></h1>
            <table class="widefat">
                <thead><tr><th><?php esc_html_e( 'Título', 'wpg' ); ?></th><th><?php esc_html_e( 'Slug', 'wpg' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $posts as $p ) : ?>
                    <tr><td><?php echo esc_html( $p->post_title ); ?></td><td><?php echo esc_html( $p->post_name ); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function ajax_generate_code() {
        check_ajax_referer( 'wpg_nonce' );

        $api_key     = sanitize_text_field( $_POST['api_key'] ?? $this->get_api_key() );
        $assistantId = sanitize_text_field( $_POST['assistant_id'] ?? $this->get_assistant_id() );
        $prompt      = sanitize_textarea_field( $_POST['prompt'] ?? '' );
        $dataset_url = esc_url_raw( $_POST['dataset_url'] ?? '' );

        if ( ! $api_key || ! $assistantId ) {
            wp_send_json_error( [ 'message' => __( 'Faltan credenciales.', 'wpg' ) ] );
        }

        if ( $dataset_url ) {
            $dataset_response = wp_remote_get( $dataset_url );
            if ( is_wp_error( $dataset_response ) ) {
                wp_send_json_error( [ 'message' => __( 'No se pudo obtener el dataset.', 'wpg' ) ] );
            }
            $body  = wp_remote_retrieve_body( $dataset_response );
            $lines = preg_split( "/\r\n|\n|\r/", trim( $body ) );
            $sample_lines = array_slice( $lines, 0, 21 ); // cabeceras + 20 registros
            $prompt      .= "\n\nDataset sample:\n" . implode( "\n", $sample_lines );
        }

        $openai = new WPG_OpenAI( $api_key, $assistantId );
        $code   = $openai->get_p5js_code( $prompt );

        if ( is_wp_error( $code ) ) {
            wp_send_json_error( [ 'message' => $code->get_error_message() ] );
        }
        wp_send_json_success( [ 'code' => $code ] );
    }

    public function ajax_save_visualization() {
        check_ajax_referer( 'wpg_nonce' );

        $slug   = sanitize_title( $_POST['slug'] ?? '' );
        $code   = wp_unslash( $_POST['code'] ?? '' );
        $prompt = sanitize_text_field( $_POST['prompt'] ?? '' );
        if ( ! $slug || ! $code ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'wpg' ) ] );
        }

        $post_id = wp_insert_post( [
            'post_type'   => 'wpg_viz',
            'post_status' => 'publish',
            'post_title'  => $slug,
            'post_name'   => $slug,
        ], true );
        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
        }
        update_post_meta( $post_id, '_wpg_code', $code );
        if ( $prompt ) {
            update_post_meta( $post_id, '_wpg_prompt', $prompt );
        }
        wp_send_json_success( [ 'id' => $post_id ] );
    }
}

