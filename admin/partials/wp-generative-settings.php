<div class="wrap">
  <h1>WP Generative — Settings</h1>
  <form method="post" action="options.php">
    <?php settings_fields( 'wp_generative_options' ); ?>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="td_openai_api_key">OpenAI API Key</label></th>
        <td><input type="password" id="td_openai_api_key" name="td_openai_api_key" class="regular-text" value="<?php echo esc_attr( get_option('td_openai_api_key','') ); ?>" autocomplete="off" /></td>
      </tr>
      <tr>
        <th scope="row"><label for="td_assistant_id">Assistant ID</label></th>
        <td><input type="text" id="td_assistant_id" name="td_assistant_id" class="regular-text" value="<?php echo esc_attr( get_option('td_assistant_id','') ); ?>" /></td>
      </tr>
      <tr>
        <th scope="row"><label for="td_dataset_url">Default Dataset URL</label></th>
        <td><input type="url" id="td_dataset_url" name="td_dataset_url" class="regular-text" value="<?php echo esc_attr( get_option('td_dataset_url','') ); ?>" placeholder="https://raw.githubusercontent.com/..." /></td>
      </tr>
    </table>
    <?php submit_button(); ?>
  </form>
</div>
