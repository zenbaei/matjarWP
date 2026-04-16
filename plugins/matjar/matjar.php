<?php
/**
 * Plugin Name: Matjar Core
 * Description: Core customizations for Matjar store
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load custom checkout module
require_once plugin_dir_path(__FILE__) . 'custom-checkout-city-handler/custom-checkout-city-handler.php';
require_once plugin_dir_path(__FILE__) . 'media-files/media-files.php';
require_once plugin_dir_path(__FILE__) . 'admin-image-zoom/admin-image-zoom.php';
require_once plugin_dir_path(__FILE__) . 'writer-taxonomy/writer-taxonomy.php';