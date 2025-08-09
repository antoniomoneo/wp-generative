<?php
defined('ABSPATH') || exit;

add_action('wpgen_enqueue_p5js', function () {
    if (!wp_script_is('p5js', 'enqueued')) {
        wp_enqueue_script(
            'p5js',
            'https://cdn.jsdelivr.net/npm/p5@1.9.0/lib/p5.min.js',
            [],
            null,
            true
        );
    }
});
