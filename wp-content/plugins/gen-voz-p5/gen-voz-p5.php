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
