<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPG_Visualization {
    public static function register() {
        $args = [
            'public'       => false,
            'show_ui'      => false,
            'label'        => 'Visualizations',
            'supports'     => [ 'title' ],
        ];
        register_post_type( 'wpg_viz', $args );
    }
}

add_action( 'init', [ 'WPG_Visualization', 'register' ] );
