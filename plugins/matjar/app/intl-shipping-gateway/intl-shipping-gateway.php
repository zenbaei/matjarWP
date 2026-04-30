<?php

/**
 * Plugin Name: International Shipping Gateway
 * Description: Custom WooCommerce payment gateway for international orders with manual shipping calculation.
 * Version: 1.0
 * Author: Islam Dev
 */

if (!defined('ABSPATH')) exit;

/**
 * Init Gateway
 */
add_action('plugins_loaded', function () {

    if (!class_exists('WC_Payment_Gateway')) return;

    /**
     * Class WC_Gateway_Intl_Shipping
     *
     * Payment gateway for international shipping:
     * - Creates order with "on-hold"
     * - Admin calculates shipping later
     * - Sends payment link to customer
     */
    class WC_Gateway_Intl_Shipping extends WC_Payment_Gateway
    {

        public function __construct()
        {

            $this->setup_properties();

            $this->init_form_fields();

            // (من WooCommerce) → يحمّل القيم من DB`
            $this->init_settings();

            $this->load_settings();

            $this->init_hooks();
        }

        /**
         * Define base properties (static config)
         */
        private function setup_properties()
        {

            $this->id = 'intl_shipping';

            $this->method_title = 'International Shipping';
            $this->method_description = 'Shipping calculated after order, then customer pays online.';

            $this->has_fields = false;

            // Defaults (used before saving settings)
            $this->title = 'الشحن الدولي (يتم حسابه لاحقًا)';
            $this->description = 'سيتم حساب تكلفة الشحن بعد الطلب، وسيتم إرسال رابط الدفع لإتمام الطلب.';
        }

        /**
         * Load saved settings from DB
         */
        private function load_settings()
        {

            $this->enabled     = $this->get_option('enabled');
            $this->title       = $this->get_option('title');
            $this->description = $this->get_option('description');
        }


        /**
         * Register hooks related to this gateway
         */
        private function init_hooks()
        {

            // Save admin settings
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                [$this, 'process_admin_options']
            );

            add_filter(
                'woocommerce_available_payment_gateways',
                [$this, 'filter_gateways']
            );

            // Optional: customize thank you page message
            add_action(
                'woocommerce_thankyou_' . $this->id,
                [$this, 'thankyou_message']
            );
        }

        /**
         * Optional: Thank you page message
         */
        public function thankyou_message($order_id)
        {

            echo '<p class="intl-shipping-note">
    سيتم التواصل معك لحساب تكلفة الشحن وإرسال رابط الدفع.
    </p>';
        }

        /**
         * Admin settings fields
         */
        public function init_form_fields()
        {

            $this->form_fields = [

                'enabled' => [
                    'title'   => 'Enable/Disable',
                    'type'    => 'checkbox',
                    'label'   => 'Enable this gateway',
                    'default' => 'yes'
                ],

                'title' => [
                    'title'       => 'Title',
                    'type'        => 'text',
                    'default'     => 'الشحن الدولي (يتم حسابه لاحقًا)'
                ],

                'description' => [
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'default'     => 'سيتم حساب تكلفة الشحن بعد الطلب.'
                ],
            ];
        }


        /**
         * Show only for international customers
         */
        public function filter_gateways($gateways)
        {

            if (is_admin()) return $gateways;

            // تأكد إننا في checkout
            if (!is_checkout()) return $gateways;

            if (isset($gateways[$this->id])) {

                $country = WC()->customer->get_shipping_country();

                if ($country === 'EG') {
                    unset($gateways[$this->id]);
                }
            }

            return $gateways;
        }

        /**
         * Process payment
         */
        public function process_payment($order_id)
        {

            $order = wc_get_order($order_id);

            // Set status
            $order->update_status('on-hold', 'Awaiting shipping calculation');

            // Add note
            $order->add_order_note('Shipping needs manual calculation.');

            // Reduce stock
            wc_reduce_stock_levels($order_id);

            // Empty cart
            WC()->cart->empty_cart();

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        }
    }
});

/**
 * Register gateway
 */
add_filter('woocommerce_payment_gateways', function ($methods) {
    $methods[] = 'WC_Gateway_Intl_Shipping';
    return $methods;
});
