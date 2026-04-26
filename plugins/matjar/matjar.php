<?php

/**
 * Plugin Name: Matjar Core
 * Description: Core customizations for Matjar book store
 */

if (!defined('ABSPATH')) {
    exit;
}


if (!defined('ABSPATH')) exit;

// Define constants EARLY
if (!defined('MATJAR_URL')) {
    define('MATJAR_URL', plugin_dir_url(__FILE__));
}

if (!defined('MATJAR_PATH')) {
    define('MATJAR_PATH', plugin_dir_path(__FILE__));
}

if (!defined('MATJAR_VERSION')) {
    define('MATJAR_VERSION', '1.0.0');
}

$modules = [
    'media-folders-taxonomy/media-folders-taxonomy.php',
    'admin-image-zoom/admin-image-zoom.php',
    'writer-taxonomy/writer-taxonomy.php',
    'publisher-taxonomy/publisher-taxonomy.php',
    'product-fields/fields-loader.php',
    'product-stock-enforcer/product-stock-enforcer.php',
    'product-weight-enforcer/product-weight-enforcer.php',
    'checkout-fields-modifier/checkout-fields-modifier.php',
    'recently-viewed-cleaner/recently-viewed-cleaner.php'
];

foreach ($modules as $module) {
    require_once __DIR__ . '/' . $module;
}
