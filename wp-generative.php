<?php
/**
 * Plugin Name:       WP Generative p5.js Assistant
 * Description:       Envía prompts a un asistente de OpenAI y genera código p5.js con controles dinámicos.
 * Version:           1.1.0
 * Author:            KGMT Knowledge Services
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-wpg-openai.php';
require_once __DIR__ . '/includes/class-wpg-visualization.php';
require_once __DIR__ . '/admin/class-wpg-admin.php';

add_action( 'plugins_loaded', function () {
    WPG_Admin::get_instance();
} );

