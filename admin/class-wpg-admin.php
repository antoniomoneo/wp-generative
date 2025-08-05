<?php
defined( 'ABSPATH' ) || exit;

class WPG_Admin {
    private static $instance = null;

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_wpg_generate_code', [ $this, 'ajax_generate_code' ] );
    }

    public static function get_instance() {
        return self::$instance ?: self::$instance = new self();
    }

    public function register_menu() {
        add_menu_page(
            __( 'Generative p5.js', 'wpg' ),
            __( 'Generative p5.js', 'wpg' ),
            'manage_options',
            'wpg-assistant',
            [ $this, 'render_page' ],
            'dashicons-admin-generic'
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_wpg-assistant' !== $hook ) {
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
            '1.0.0',
            true
        );
        wp_localize_script( 'wpg-admin-js', 'WPG_Ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpg_nonce' ),
        ] );
    }

    public function render_page() {
        $api_key     = get_option( 'wpg_api_key', '' );
        $assistantId = get_option( 'wpg_assistant_id', '' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Generative p5.js Assistant', 'wpg' ); ?></h1>
            <form id="wpg-settings">
                <table class="form-table">
                    <tr>
                        <th><label for="wpg_api_key">API Key</label></th>
                        <td><input type="password" id="wpg_api_key" name="wpg_api_key" value="<?php echo esc_attr( $api_key ); ?>" size="40" /></td>
                    </tr>
                    <tr>
                        <th><label for="wpg_assistant_id">Assistant ID</label></th>
                        <td><input type="text" id="wpg_assistant_id" name="wpg_assistant_id" value="<?php echo esc_attr( $assistantId ); ?>" size="40" /></td>
                    </tr>
                    <tr>
                        <th><label for="wpg_prompt">Prompt</label></th>
                        <td><textarea id="wpg_prompt" name="wpg_prompt" rows="4" cols="50"></textarea></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Enviar al asistente', 'wpg' ), 'primary', 'wpg-send' ); ?>
            </form>

            <div id="wpg-controls" style="margin-top:2em;"></div>
            <div id="wpg-preview" style="margin-top:2em;">
                <!-- p5.js sketch se inserta aquí -->
            </div>
        </div>
        <?php
    }

    public function ajax_generate_code() {
        check_ajax_referer( 'wpg_nonce' );

        $api_key     = sanitize_text_field( $_POST['api_key'] ?? '' );
        $assistantId = sanitize_text_field( $_POST['assistant_id'] ?? '' );
        $prompt      = sanitize_textarea_field( $_POST['prompt'] ?? '' );

        update_option( 'wpg_api_key', $api_key );
        update_option( 'wpg_assistant_id', $assistantId );

        $openai = new WPG_OpenAI( $api_key, $assistantId );
        $code   = $openai->get_p5js_code( $prompt );

        if ( is_wp_error( $code ) ) {
            wp_send_json_error( [ 'message' => $code->get_error_message() ] );
        }
        wp_send_json_success( [ 'code' => $code ] );
    }
}
