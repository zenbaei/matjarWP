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

        add_filter('woocommerce_available_payment_gateways', [$this, 'force_cod_payment_for_intl']);
        add_filter('woocommerce_no_available_payment_methods_message', [$this, 'replace_payment_message']);
        //  add_filter('woocommerce_cart_needs_payment', [$this, 'skip_payment_validation']);
        //  add_action('woocommerce_checkout_create_order', [$this, 'intl_order_status_as_pending'], 999);
        // add_action('woocommerce_checkout_order_processed', [$this, 'set_order_status_pending']);
        // add_filter('woocommerce_payment_complete_order_status', [$this, 'override_order_status_on_payment_complete']);
    }

    public function override_order_status_on_payment_complete($status, $order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) return $status;

        $country = $order->get_billing_country();

        if ($country && $country !== 'EG') {
            return 'pending'; // Force pending on payment complete for intl orders
        }

        return $status; // normal flow for EG orders
    }

    public function force_cod_payment_for_intl($gateways)
    {

        // Never touch admin
        if (is_admin()) {
            return $gateways;
        }


        if (is_wc_endpoint_url('order-pay')) {
            $order_id = absint(get_query_var('order-pay'));
            $order = wc_get_order($order_id);
            if (isset($gateways['cod'])) {
                unset($gateways['cod']);
            }
            return $gateways;
        }


        // Get live checkout country (session-based)
        $country = WC()->customer ? WC()->customer->get_billing_country() : '';

        // Prevent weird behavior on first load
        if (!$country) {
            return $gateways;
        }

        // Egypt → remove COD
        if ($country === 'EG') {
            if (isset($gateways['cod'])) {
                unset($gateways['cod']);
            }
            return $gateways;
        }

        // Other countries → only COD
        if (isset($gateways['cod'])) {
            return ['cod' => $gateways['cod']];
        }

        error_log("COD not available for country $country");

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

    public function set_order_status_pending($order_id)
    {

        $order = wc_get_order($order_id);
        if (!$order) return;

        $country = $order->get_billing_country();

        if ($country && $country !== 'EG') {

            // Force pending AFTER Woo sets status
            $order->set_status('pending', 'Forced pending for international order');
            $order->save();
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
