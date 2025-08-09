<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Simple setting under Settings > Reading: default dataset URL.
add_action( 'admin_init', function() {
       register_setting( 'reading', 'gv_default_dataset_url', array(
               'type'              => 'string',
               'sanitize_callback' => 'esc_url_raw',
               'default'           => '',
       ) );
       add_settings_field(
               'gv_default_dataset_url',
               __( 'Default dataset URL (WP Generative)', 'wp-generative' ),
               function() {
                       $val = esc_url( get_option( 'gv_default_dataset_url', '' ) );
                       echo '<input type="url" name="gv_default_dataset_url" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="https://.../data.csv" />';
               },
               'reading'
       );
} );

