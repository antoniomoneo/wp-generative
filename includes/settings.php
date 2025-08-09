<?php
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'wpg-settings',
        'WP Generative – OpenAI',
        'WP Generative – OpenAI',
        'manage_options',
        'wpgen-openai',
        'wpgen_settings_page'
    );
}, 20);

add_action('admin_init', function () {
    register_setting('wpgen_openai', 'wpgen_openai_api_key', ['type' => 'string',  'sanitize_callback' => 'sanitize_text_field']);
    register_setting('wpgen_openai', 'wpgen_openai_assistant_id', ['type' => 'string',  'sanitize_callback' => 'sanitize_text_field']);
    register_setting('wpgen_openai', 'wpgen_openai_model',   ['type' => 'string',  'sanitize_callback' => 'sanitize_text_field', 'default' => 'gpt-4.1']);
    register_setting('wpgen_openai', 'wpgen_openai_timeout', ['type' => 'integer', 'default' => 60]);
});

function wpgen_settings_page() {
    ?>
    <div class="wrap">
      <h1>WP Generative – OpenAI</h1>
      <form method="post" action="options.php">
        <?php settings_fields('wpgen_openai'); ?>
        <?php $creds = wpg_get_openai_credentials(); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="wpgen_openai_api_key">API Key</label></th>
            <td><input type="password" id="wpgen_openai_api_key" name="wpgen_openai_api_key" value="<?php echo esc_attr($creds['api_key']); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="wpgen_openai_assistant_id">Assistant ID</label></th>
            <td><input type="text" id="wpgen_openai_assistant_id" name="wpgen_openai_assistant_id" value="<?php echo esc_attr($creds['assistant_id']); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="wpgen_openai_model">Modelo</label></th>
            <td><input type="text" id="wpgen_openai_model" name="wpgen_openai_model" value="<?php echo esc_attr(get_option('wpgen_openai_model','gpt-4.1')); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="wpgen_openai_timeout">Timeout (s)</label></th>
            <td><input type="number" min="5" id="wpgen_openai_timeout" name="wpgen_openai_timeout" value="<?php echo esc_attr(get_option('wpgen_openai_timeout',60)); ?>" class="small-text" /></td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
      <p>Shortcode de ejemplo:</p>
      <pre>[p5js_visual data_url="https://example.com/data.csv" user_prompt="Línea temporal de temperatura con tooltip" data_format="auto" width="900" height="520" cache="30"]</pre>
    </div>
    <?php
}

function wpg_get_openai_credentials() {
    $api_key = '';
    if ( defined( 'OPENAI_API_KEY' ) && OPENAI_API_KEY ) {
        $api_key = OPENAI_API_KEY;
    } elseif ( getenv( 'OPENAI_API_KEY' ) ) {
        $api_key = getenv( 'OPENAI_API_KEY' );
    } else {
        $api_key = get_option( 'wpgen_openai_api_key', '' );
    }

    $assistant_id = get_option( 'wpgen_openai_assistant_id', '' );
    if ( ! $assistant_id && defined( 'OPENAI_ASSISTANT_ID' ) ) {
        $assistant_id = OPENAI_ASSISTANT_ID;
    } elseif ( ! $assistant_id && getenv( 'OPENAI_ASSISTANT_ID' ) ) {
        $assistant_id = getenv( 'OPENAI_ASSISTANT_ID' );
    }

    return [
        'api_key'      => (string) $api_key,
        'assistant_id' => (string) $assistant_id,
    ];
}
