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
    'admin/taxonomies/media-folder-taxonomy/loader.php',
    'admin/taxonomies/writer-taxonomy/writer-taxonomy.php',
    'admin/taxonomies/publisher-taxonomy/publisher-taxonomy.php',
    'admin/custom-fields/product-custom-fields/loader.php',
    'admin/gallery-image-zoom/gallery-image-zoom.php',
    'admin/product-stock-enforcer/product-stock-enforcer.php',
    'admin/product-fields-validation/product-fields-validation.php',
    'admin/custom-fields/global-custom-fields/expenses-field.php',
    'app/recently-viewed-widget/recently-viewed-widget.php',
    'app/checkout-fields-modifier/checkout-fields-modifier.php',
    'app/custom-payment-gateway/loader.php',
    'app/custom-intl-checkout-handler/custom-intl-checkout-handler.php',
    'app/address-fields/address-fields.php',
    'app/woocommerce-currency/woocommerce-currency.php',
    'app/cart-page/cart-page.php',
    'app/single-product-page/single-product-page.php',
];

foreach ($modules as $module) {
    require_once __DIR__ . '/' . $module;
}
