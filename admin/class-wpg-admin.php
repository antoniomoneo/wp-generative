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

    // API key and assistant ID are retrieved via wpg_get_openai_credentials().

    private function get_base_instructions() {
        return "Eres un generador experto de visualizaciones de datos usando p5.js. Recibirás dos insumos: (1) una muestra tabular de aproximadamente 20 registros, incluyendo todas las columnas relevantes, en formato JSON válido; y (2) una descripción en lenguaje natural de lo que el usuario quiere visualizar. Analiza los datos para identificar tipos de columnas (numéricas, categóricas, fechas en ISO 8601 o DD/MM/AAAA, etc.) y genera un sketch p5.js que represente la información según las instrucciones. El código debe ser funcional, usar setup() y draw(), y puede simular la carga de datos si es necesario. No escribas explicaciones fuera del código. Usa interactividad básica (por ejemplo, zoom o tooltips) cuando sea apropiado. Si la muestra no contiene columnas relevantes o está mal formateada, responde con un mensaje de error indicando las columnas faltantes. Devuelve solo código p5.js.\nfunction setup() { createCanvas(400, 400); }\nfunction draw() { background(220); }";
    }

    private function parse_table_sample( $body ) {
        $table = [];
        $json  = json_decode( $body, true );
        if ( is_array( $json ) ) {
            if ( isset( $json[0] ) ) {
                $table = array_slice( $json, 0, 20 );
            } elseif ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
                $table = array_slice( $json['data'], 0, 20 );
            }
        }

        if ( empty( $table ) ) {
            $lines   = preg_split( "/\r\n|\n|\r/", trim( $body ) );
            $lines   = array_slice( $lines, 0, 21 );
            $headers = str_getcsv( array_shift( $lines ) );
            foreach ( $lines as $line ) {
                $row = str_getcsv( $line );
                if ( count( $row ) === count( $headers ) ) {
                    $table[] = array_combine( $headers, $row );
                }
            }
        }

        return $table;
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
        $page          = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $allowed_hooks = [ 'wpg-settings_page_wpg-sandbox' ];
        $allowed_pages = [ 'wpg-sandbox' ];
        if ( ! in_array( $hook, $allowed_hooks, true ) && ! in_array( $page, $allowed_pages, true ) ) {
            return;
        }

        $editor_settings = wp_enqueue_code_editor( [ 'type' => 'text/javascript' ] );
        if ( $editor_settings ) {
            wp_enqueue_script( 'wp-theme-plugin-editor' );
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
            [ 'jquery', 'p5', 'wp-theme-plugin-editor' ],
            '1.3.0',
            true
        );
        wp_enqueue_style(
            'wpg-admin-css',
            plugin_dir_url( __FILE__ ) . 'css/wp-generative-admin.css',
            [],
            WPG_PLUGIN_VERSION
        );
        if ( $editor_settings ) {
            wp_localize_script( 'wpg-admin-js', 'wpgEditorSettings', $editor_settings );
        }
        wp_localize_script( 'wpg-admin-js', 'WPG_Ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpg_nonce' ),
            'p5_url'   => plugin_dir_url( __FILE__ ) . '../assets/js/p5.min.js',
        ] );
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
            <p><?php printf( esc_html__( 'Versión: %s', 'wpg' ), esc_html( WPG_PLUGIN_VERSION ) ); ?></p>
            <form id="wpg-sandbox-form">
                <p><label for="wpg_prompt"><?php esc_html_e( 'Prompt', 'wpg' ); ?></label></p>
                <p><textarea id="wpg_prompt" rows="4"><?php esc_html_e( 'crea el código p5.js para una visualización generativa del dataset en la URL.', 'wpg' ); ?></textarea></p>
                <p><label for="wpg_dataset"><?php esc_html_e( 'Dataset URL', 'wpg' ); ?></label></p>
                <p>
                    <input type="text" id="wpg_dataset" list="wpg_dataset_list" />
                    <datalist id="wpg_dataset_list"></datalist>
                </p>
                <p><button type="button" id="wpg-generate" class="button button-primary"><?php esc_html_e( 'Generar', 'wpg' ); ?></button></p>
            </form>
            <p><label for="wpg_request"><?php esc_html_e( 'Llamada al API', 'wpg' ); ?></label></p>
              <p><textarea id="wpg_request" rows="6" readonly></textarea></p>
            <p><label for="wpg_response"><?php esc_html_e( 'Respuesta del asistente', 'wpg' ); ?></label></p>
              <p><textarea id="wpg_response" rows="6" placeholder="<?php esc_attr_e( 'Aquí aparecerá la respuesta del asistente...', 'wpg' ); ?>"></textarea></p>
              <div id="wpg-editor">
                  <textarea id="wpgen-code"></textarea>
                  <div id="wpg-preview"></div>
              </div>
              <div class="wpgen-update-row">
                  <button id="wpgen-btn-update-code" type="button" class="button button-primary">
                      <?php esc_html_e( 'Actualizar código (reemplazar let data…)', 'wpg' ); ?>
                  </button>
                  <small id="wpgen-update-hint">
                      <?php esc_html_e( 'Usará la URL del dataset para regenerar data o years con todos los registros. Debería ser igual a Dataset URL.', 'wpg' ); ?>
                  </small>
              </div>
              <input type="url" id="wpgen-dataset-url" placeholder="https://raw.githubusercontent.com/usuario/repo/main/dataset.csv" />
              <p><button id="wpg-run" class="button"><?php esc_html_e( 'Vista previa', 'wpg' ); ?></button></p>
              <div id="wpg-controls"></div>
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

        $creds       = wpg_get_openai_credentials();
        $api_key     = sanitize_text_field( $_POST['api_key'] ?? $creds['api_key'] );
        $user_prompt = sanitize_textarea_field( $_POST['prompt'] ?? '' );
        $dataset_url = esc_url_raw( $_POST['dataset_url'] ?? '' );
        if ( ! $dataset_url ) {
            $dataset_url = esc_url_raw( get_option( 'gv_default_dataset_url', '' ) );
        }

        if ( ! $api_key ) {
            wp_send_json_error( [ 'message' => __( 'Faltan credenciales.', 'wpg' ) ] );
        }

        $dataset_text   = GV_Dataset_Helper::get_sample( $dataset_url );
        $user_prompt    = $this->get_base_instructions() . "\n\n" . $user_prompt;
        $combined_prompt = "DATASET:\n{$dataset_text}\n\nUSER REQUEST:\n{$user_prompt}";

        $openai = new WPG_OpenAI( $api_key );
        $code   = $openai->get_p5js_code( $combined_prompt );

        if ( is_wp_error( $code ) ) {
            wp_send_json_error(
                [
                    'message'      => $code->get_error_message(),
                    'api_response' => $code->get_error_data(),
                ]
            );
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

