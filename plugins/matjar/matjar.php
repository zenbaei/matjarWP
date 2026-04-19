<?php

/**
 * Plugin Name: Matjar Core
 * Description: Core customizations for Matjar book store
 */

if (!defined('ABSPATH')) {
    exit;
}

$modules = [
    'media-folders/media-folders.php',
    'admin-image-zoom/admin-image-zoom.php',
    'writer-taxonomy/writer-taxonomy.php',
    'publisher-taxonomy/publisher-taxonomy.php',
    'fields/fields-loader.php',
    'product-stock-enforcer/product-stock-enforcer.php',
    'product-weight-enforcer/product-weight-enforcer.php',
    'custom-checkout-city-handler/custom-checkout-city-handler.php',
    'recently-viewed-cleaner/recently-viewed-cleaner.php'
];

foreach ($modules as $module) {
    require_once __DIR__ . '/' . $module;
}
