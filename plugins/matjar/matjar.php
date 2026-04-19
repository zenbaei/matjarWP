<?php

/**
 * Plugin Name: Matjar Core
 * Description: Core customizations for Matjar book store
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load custom checkout module
require_once plugin_dir_path(__FILE__) . 'custom-checkout-city-handler/custom-checkout-city-handler.php';
require_once plugin_dir_path(__FILE__) . 'media-folders/media-folders.php';
require_once plugin_dir_path(__FILE__) . 'admin-image-zoom/admin-image-zoom.php';
require_once plugin_dir_path(__FILE__) . 'writer-taxonomy/writer-taxonomy.php';
require_once plugin_dir_path(__FILE__) . 'product-stock-enforcer/product-stock-enforcer.php';
require_once plugin_dir_path(__FILE__) . 'product-weight-enforcer/product-weight-enforcer.php';
require_once plugin_dir_path(__FILE__) . 'recently-viewed-cleaner/recently-viewed-cleaner.php';
