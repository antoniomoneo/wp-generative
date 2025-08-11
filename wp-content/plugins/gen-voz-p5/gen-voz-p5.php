<?php

if ( ! defined( 'GV_OPENAI_API_KEY' ) ) {
    define( 'GV_OPENAI_API_KEY', getenv( 'OPENAI_API_KEY' ) );
}

if ( ! defined( 'GV_OPENAI_MODEL' ) ) {
    define( 'GV_OPENAI_MODEL', 'gpt-4o-2024-08-06' );
}

function gv_p5_json_schema() {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => [ 'code', 'meta', 'diagnostics' ],
        'properties' => [
            'code' => [
                'type' => 'string',
                'minLength' => 200,
            ],
            'meta' => [
                'type' => 'object',
                'required' => [ 'canvas' ],
                'additionalProperties' => false,
                'properties' => [
                    'canvas' => [
                        'type' => 'object',
                        'required' => [ 'width', 'height' ],
                        'additionalProperties' => false,
                        'properties' => [
                            'width' => [ 'type' => 'integer' ],
                            'height' => [ 'type' => 'integer' ],
                        ],
                    ],
                ],
            ],
            'diagnostics' => [
                'type' => 'object',
                'required' => [ 'validation_passed', 'lint_warnings' ],
                'additionalProperties' => false,
                'properties' => [
                    'validation_passed' => [ 'type' => 'boolean' ],
                    'lint_warnings' => [
                        'type' => 'array',
                        'items' => [ 'type' => 'string' ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Remove triple backtick fences (```), including language specifiers.
 *
 * @param string $s Raw string possibly containing fenced code blocks.
 * @return string String without fences.
 */
function gv_strip_fences( $s ) {
    return preg_replace( '/```(?:[a-zA-Z0-9_-]+)?\n?/', '', $s );
}

/**
 * Generate a p5.js sketch using OpenAI's Responses API.
 *
 * @param string $dataset_url URL to CSV dataset.
 * @param string $viz_prompt  Visualization prompt.
 * @param int    $width       Canvas width.
 * @param int    $height      Canvas height.
 *
 * @return array|WP_Error {code, meta, diagnostics} or WP_Error on failure.
 */
function gv_openai_generate_p5( $dataset_url, $viz_prompt, $width = 900, $height = 560 ) {
    $body = [
        'model'           => GV_OPENAI_MODEL,
        'response_format' => [
            'type'        => 'json_schema',
            'json_schema' => [
                'name'   => 'p5_sketch',
                'strict' => true,
                'schema' => gv_p5_json_schema(),
            ],
        ],
        'input'           => [
            [
                'role'    => 'system',
                'content' => 'Devuelve SOLO un JSON {code, meta:{canvas:{width,height}}, diagnostics}. Sin HTML ni ``` . No uses document.*, window.*, import o require. Incluye setup()+createCanvas(meta.canvas.width, meta.canvas.height). Si dataset_url existe, usa preload()+loadTable(url,\'csv\',\'header\'). Por defecto: título (drawTitle) y hover tooltip (hitTest/mouseMoved/drawTooltip).',
            ],
            [
                'role'    => 'user',
                'content' => "dataset_url: $dataset_url\nviz_prompt: $viz_prompt\ncanvas: {$width}x{$height}",
            ],
        ],
    ];

    $response = wp_remote_post(
        'https://api.openai.com/v1/responses',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . GV_OPENAI_API_KEY,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $status && 201 !== $status ) {
        return new WP_Error( 'openai_http_error', 'Unexpected HTTP status', [
            'status' => $status,
            'body'   => wp_remote_retrieve_body( $response ),
        ] );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $data ) ) {
        return new WP_Error( 'openai_invalid_json', 'Invalid JSON response' );
    }

    $output_text = '';
    if ( ! empty( $data['output'][0]['content'][0]['text'] ) ) {
        $output_text = $data['output'][0]['content'][0]['text'];
    } elseif ( isset( $data['output_text'] ) ) {
        if ( is_array( $data['output_text'] ) ) {
            $output_text = implode( "\n", $data['output_text'] );
        } else {
            $output_text = $data['output_text'];
        }
    }

    if ( '' === $output_text ) {
        return new WP_Error( 'openai_missing_output', 'No output text found' );
    }

    $payload = json_decode( $output_text, true );
    if ( ! is_array( $payload ) ) {
        return new WP_Error( 'openai_invalid_payload', 'Invalid payload JSON' );
    }

    $code = isset( $payload['code'] ) ? gv_strip_fences( $payload['code'] ) : '';
    $meta = isset( $payload['meta'] ) ? $payload['meta'] : [];
    $diagnostics = isset( $payload['diagnostics'] ) ? $payload['diagnostics'] : [];

    return [
        'code'        => $code,
        'meta'        => $meta,
        'diagnostics' => $diagnostics,
    ];
}
add_action( 'admin_menu', 'gv_p5_admin_menu' );
function gv_p5_admin_menu() {
    add_management_page(
        'Gen Voz p5 Preview',
        'Gen Voz p5 Preview',
        'manage_options',
        'gv-p5-preview',
        'gv_p5_admin_page'
    );
}

function gv_p5_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Gen Voz p5 Preview', 'gen-voz-p5' ); ?></h1>
        <form id="gv-p5-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="gv-p5-dataset-url"><?php esc_html_e( 'Dataset URL', 'gen-voz-p5' ); ?></label></th>
                    <td><input type="url" id="gv-p5-dataset-url" name="dataset_url" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gv-p5-viz-prompt"><?php esc_html_e( 'Viz Prompt', 'gen-voz-p5' ); ?></label></th>
                    <td><textarea id="gv-p5-viz-prompt" name="viz_prompt" class="large-text" rows="5"></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gv-p5-width"><?php esc_html_e( 'Width', 'gen-voz-p5' ); ?></label></th>
                    <td><input type="number" id="gv-p5-width" name="width" value="900" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gv-p5-height"><?php esc_html_e( 'Height', 'gen-voz-p5' ); ?></label></th>
                    <td><input type="number" id="gv-p5-height" name="height" value="560" /></td>
                </tr>
            </table>
            <p><button type="button" class="button button-primary" id="gv-p5-generate"><?php esc_html_e( 'Generar', 'gen-voz-p5' ); ?></button></p>
        </form>
    </div>
    <?php
}

add_action( 'admin_enqueue_scripts', 'gv_p5_admin_enqueue' );
function gv_p5_admin_enqueue( $hook ) {
    if ( 'tools_page_gv-p5-preview' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'gv-p5-admin',
        plugin_dir_url( __FILE__ ) . 'assets/gv-p5-admin.js',
        [ 'jquery' ],
        null,
        true
    );

    wp_localize_script(
        'gv-p5-admin',
        'gvP5Ajax',
        [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gv_p5_preview' ),
        ]
    );
}

add_action( 'wp_ajax_gv_p5_preview', 'gv_p5_preview' );
function gv_p5_preview() {
    check_ajax_referer( 'gv_p5_preview', 'nonce' );

    $dataset_url = isset( $_POST['dataset_url'] ) ? esc_url_raw( wp_unslash( $_POST['dataset_url'] ) ) : '';
    $viz_prompt  = isset( $_POST['viz_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['viz_prompt'] ) ) : '';
    $width       = isset( $_POST['width'] ) ? intval( $_POST['width'] ) : 900;
    $height      = isset( $_POST['height'] ) ? intval( $_POST['height'] ) : 560;

    $payload = gv_openai_generate_p5( $dataset_url, $viz_prompt, $width, $height );
    if ( is_wp_error( $payload ) ) {
        wp_send_json_error( $payload->get_error_message() );
    }

    wp_send_json_success( $payload );
}
