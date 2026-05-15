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
    'taxonomies/media-folders-taxonomy/media-folders-taxonomy.php',
    'taxonomies/writer-taxonomy/writer-taxonomy.php',
    'taxonomies/publisher-taxonomy/publisher-taxonomy.php',
    'custom-fields/product-custom-fields/fields-loader.php',
    'admin/admin-image-zoom/admin-image-zoom.php',
    'admin/product-stock-enforcer/product-stock-enforcer.php',
    'admin/product-weight-enforcer/product-weight-enforcer.php',
    'app/recently-viewed-widget/recently-viewed-widget.php',
    'app/checkout-fields-modifier/checkout-fields-modifier.php',
    'app/custom-payment-gateway/loader.php',
    'app/custom-intl-checkout-handler/custom-intl-checkout-handler.php',
    'app/address-fields/address-fields.php',
    'app/woocommerce-currency/woocommerce-currency.php',
    'app/cart-page/hide-address.php',
];

foreach ($modules as $module) {
    require_once __DIR__ . '/' . $module;
}
