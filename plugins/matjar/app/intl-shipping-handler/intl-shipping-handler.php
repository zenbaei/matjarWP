<?php

/**
 * Plugin Name: International Shipping Button
 * Description: Show a button for international shipping quote in checkout.
 * Version: 1.0
 * Author: Islam Zenbaei
 */

if (!defined('ABSPATH')) exit;

class Intl_Shipping_Handler
{

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        add_filter('woocommerce_available_payment_gateways', [$this, 'baypass_payment_gateways']);
        add_filter('woocommerce_no_available_payment_methods_message', [$this, 'replace_payment_message']);
        add_filter('woocommerce_cart_needs_payment', [$this, 'skip_payment_validation']);
        add_action('woocommerce_checkout_create_order', [$this, 'intl_order_status_as_pending']);
    }

    public function baypass_payment_gateways($gateways)
    {
        // Never touch admin
        if (is_admin()) {
            return $gateways;
        }

        // Get live checkout country (session-based)
        $country = WC()->customer ? WC()->customer->get_billing_country() : '';

        // Prevent weird behavior on first load
        if (!$country) {
            return $gateways;
        }

        // 🚀 Your logic
        if ($country !== 'EG') {
            return []; // hide all gateways
        }

        return $gateways;
    }

    public function replace_payment_message($message)
    {

        $country = WC()->customer ? WC()->customer->get_billing_country() : '';

        if ($country && $country !== 'EG') {
            return 'سيتم حساب تكلفة الشحن الدولي واعلامكم عبر البريد الإلكتروني.'; // your custom message
        }

        return $message;
    }

    public function skip_payment_validation($needs_payment)
    {

        if (is_admin() && !defined('DOING_AJAX')) {
            return $needs_payment;
        }

        $country = WC()->customer ? WC()->customer->get_billing_country() : '';

        // If no country yet → don't interfere
        if (!$country) {
            return $needs_payment;
        }

        // 🌍 Non-Egypt → skip payment
        if ($country !== 'EG') {
            return false;
        }

        // 🇪🇬 Egypt → normal payment
        return true;
    }

    public function intl_order_status_as_pending($order)
    {
        $country = WC()->customer ? WC()->customer->get_billing_country() : '';

        if ($country && $country !== 'EG') {
            $order->set_status('pending');
        }
    }

    /**
     * 🔵 Enqueue JS
     */
    public function enqueue_scripts()
    {
        if (!is_checkout() || is_order_received_page()) return;

        wp_enqueue_script(
            'intl-shipping-handler-js',
            plugin_dir_url(__FILE__) . 'intl-shipping-handler.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . 'intl-shipping-handler.js'),
            true
        );

        wp_script_add_data('intl-shipping-handler-js', 'defer', true);

        wp_localize_script('intl-shipping-handler-js', 'intlShippingHandler', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('intl_shipping_handler_js_nonce')
        ]);
    }
}

// 🚀 Init
new Intl_Shipping_Handler();
