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
         * Register all hooks related to enforcing this gateway
         */
        private function init_hooks()
        {

            // حفظ إعدادات الأدمن
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                [$this, 'process_admin_options']
            );

            // فلترة وسائل الدفع
            add_filter(
                'woocommerce_available_payment_gateways',
                [$this, 'filter_gateways']
            );

            // تحقق قبل إنشاء الطلب
            add_action(
                'woocommerce_checkout_process',
                [$this, 'validate_gateway']
            );

            // فرض وسيلة الدفع عند إنشاء الطلب
            add_action(
                'woocommerce_checkout_create_order',
                [$this, 'enforce_gateway_on_order'],
                10,
                2
            );

            // Optional: customize thank you page message
            add_action(
                'woocommerce_thankyou_' . $this->id,
                [$this, 'thankyou_message']
            );


            // اختياري: بعد on-hold
            /* later if we need to do an action after order is on-hold, we can use this hook
            add_action(
                'woocommerce_order_status_on-hold',
                [$this, 'on_hold_actions']
            );
            */
        }


        /**
         * Filter available gateways on checkout
         *
         * الهدف:
         * - إخفاء باقي وسائل الدفع للشحن الدولي
         * - إجبار اختيار الـ gateway الحالي
         * - إخفاؤه من صفحة order-pay
         *
         * @param array $gateways
         * @return array
         */
        public function filter_gateways($gateways)
        {

            // تجاهل الأدمن
            if (is_admin()) {
                return $gateways;
            }

            // إخفاء في صفحة الدفع لطلب موجود
            if (is_wc_endpoint_url('order-pay')) {
                unset($gateways[$this->id]);
                return $gateways;
            }

            // اشتغل فقط في checkout
            if (!is_checkout()) {
                return $gateways;
            }

            $country = WC()->customer->get_shipping_country();

            // لو مصر → اخفي الـ gateway ده
            if ($country === 'EG') {
                unset($gateways[$this->id]);
                return $gateways;
            }


            // لو شحن دولي (مش مصر)
            if ($country && $country !== 'EG') {

                // إزالة كل gateways ما عدا ده
                foreach ($gateways as $id => $gateway) {
                    if ($id !== $this->id) {
                        unset($gateways[$id]);
                    }
                }

                // إجبار الاختيار في session
                WC()->session->set('chosen_payment_method', $this->id);
            }

            return $gateways;
        }


        /**
         * Validate selected gateway before checkout submit
         *
         * الهدف:
         * - منع التلاعب بالـ request
         * - التأكد إن المستخدم اختار gateway الصحيح
         */
        public function validate_gateway()
        {

            if (is_admin()) {
                return;
            }

            $country = WC()->customer->get_shipping_country();

            if ($country && $country !== 'EG') {

                $chosen = $_POST['payment_method'] ?? '';

                if ($chosen !== $this->id) {
                    wc_add_notice('طريقة الدفع غير صالحة للشحن الدولي.', 'error');
                }
            }
        }


        /**
         * Enforce gateway on order creation
         *
         * الهدف:
         * - آخر طبقة أمان
         * - ضمان إن الطلب فعليًا مسجل بالـ gateway الصحيح
         *
         * @param WC_Order $order
         * @param array $data
         */
        public function enforce_gateway_on_order($order, $data)
        {

            $country = WC()->customer->get_shipping_country();

            if ($country && $country !== 'EG') {

                if ($order->get_payment_method() !== $this->id) {
                    $order->set_payment_method($this->id);
                }
            }
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
         * Initialize order for international shipping (no payment yet).
         * Sets order to on-hold, reduces stock, and redirects to thank you page.
         *
         * This method is called automatically by WooCommerce during checkout
         * when this gateway is selected. No hook is required.
         *
         * @param int $order_id
         * @return array
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
