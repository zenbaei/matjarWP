<?php

/**
 * Plugin Name: Custom Payment Gateway
 * Description: Custom WooCommerce payment gateway for international orders with manual shipping calculation.
 * Version: 1.0
 * Author: Islam Dev
 */

if (!defined('ABSPATH')) exit;

// تحميل الكلاس
add_action('plugins_loaded', function () {

    if (!class_exists('WC_Payment_Gateway')) return;

    require_once plugin_dir_path(__FILE__) . 'custom-payment-gateway.php';
});

// تسجيل الـ gateway
add_filter('woocommerce_payment_gateways', function ($methods) {
    $methods[] = 'WC_Gateway_Intl_Shipping';
    return $methods;
});
