<?php
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_options_page(
        'WP Generative – OpenAI',
        'WP Generative – OpenAI',
        'manage_options',
        'wpgen-openai',
        'wpgen_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('wpgen_openai', 'wpgen_openai_api_key', ['type'=>'string','sanitize_callback'=>'sanitize_text_field']);
    register_setting('wpgen_openai', 'wpgen_openai_model',   ['type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>'gpt-4.1']);
    register_setting('wpgen_openai', 'wpgen_openai_timeout', ['type'=>'integer','default'=>60]);
});

function wpgen_settings_page() {
    ?>
    <div class="wrap">
      <h1>WP Generative – OpenAI</h1>
      <form method="post" action="options.php">
        <?php settings_fields('wpgen_openai'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="wpgen_openai_api_key">API Key</label></th>
            <td><input type="password" id="wpgen_openai_api_key" name="wpgen_openai_api_key" value="<?php echo esc_attr(get_option('wpgen_openai_api_key','')); ?>" class="regular-text" /></td>
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
