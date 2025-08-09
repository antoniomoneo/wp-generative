<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function wpg_get_openai_credentials() {
    $api_key = '';
    if ( defined( 'OPENAI_API_KEY' ) && OPENAI_API_KEY ) {
        $api_key = OPENAI_API_KEY;
    } elseif ( defined( 'GV_OPENAI_API_KEY' ) && GV_OPENAI_API_KEY ) {
        $api_key = GV_OPENAI_API_KEY;
    } elseif ( getenv( 'OPENAI_API_KEY' ) ) {
        $api_key = getenv( 'OPENAI_API_KEY' );
    } else {
        foreach ( array( 'wpg_api_key', 'wpg_openai_api_key', 'td_openai_api_key', 'gv_openai_api_key', 'wpgen_openai_api_key' ) as $opt ) {
            $val = get_option( $opt, '' );
            if ( ! empty( $val ) ) {
                $api_key = $val;
                break;
            }
        }
    }

    $assistant_id = '';
    foreach ( array( 'wpg_assistant_id', 'td_assistant_id', 'gv_openai_assistant_id' ) as $opt ) {
        $val = get_option( $opt, '' );
        if ( ! empty( $val ) ) {
            $assistant_id = $val;
            break;
        }
    }
    if ( ! $assistant_id && defined( 'OPENAI_ASSISTANT_ID' ) ) {
        $assistant_id = OPENAI_ASSISTANT_ID;
    } elseif ( ! $assistant_id && getenv( 'OPENAI_ASSISTANT_ID' ) ) {
        $assistant_id = getenv( 'OPENAI_ASSISTANT_ID' );
    }

    return array(
        'api_key' => (string) $api_key,
        'assistant_id' => (string) $assistant_id,
    );
}
