<?php
if (!defined('ABSPATH')) exit;

class Custom_Intl_Checkout_Handler
{
    private $gateway_id = 'intl_shipping';

    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Register hooks
     */
    private function init_hooks()
    {
        add_filter(
            'woocommerce_available_payment_gateways',
            [$this, 'filter_gateways']
        );

        add_action(
            'woocommerce_checkout_process',
            [$this, 'validate_gateway']
        );

        add_action(
            'woocommerce_checkout_create_order',
            [$this, 'enforce_gateway_on_order'],
            10,
            2
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
     * Filter payment gateways based on customer country.
     * - Hide this gateway in Egypt
     * - Force this gateway for international orders
     * - Hide on order-pay page
     *
     * @param array $gateways
     * @return array
     */
    public function filter_gateways($gateways)
    {
        if (is_admin()) return $gateways;

        if (is_wc_endpoint_url('order-pay')) {
            unset($gateways[$this->gateway_id]);
            return $gateways;
        }

        if (!is_checkout()) return $gateways;

        $country = $this->get_customer_country();

        if (!$country) return $gateways;

        // Egypt → hide
        if ($country === 'EG') {
            unset($gateways[$this->gateway_id]);
            return $gateways;
        }

        // International → force
        if (!isset($gateways[$this->gateway_id])) return $gateways;

        foreach ($gateways as $id => $gateway) {
            if ($id !== $this->gateway_id) {
                unset($gateways[$id]);
            }
        }

        WC()->session->set('chosen_payment_method', $this->gateway_id);

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

        // هل المستخدم اختار عنوان شحن مختلف؟
        $use_shipping = !empty($_POST['ship_to_different_address']);

        $country =  $use_shipping ? $_POST['shipping_country'] : $_POST['billing_country'];

        if (!$country)
            $country = $this->get_customer_country();

        if ($country && $country !== 'EG') {
            $chosen = $_POST['payment_method'] ?? '';

            if ($chosen !== $this->gateway_id) {
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
        $country = $this->get_customer_country();

        if ($country && $country !== 'EG') {
            if ($order->get_payment_method() !== $this->gateway_id) {
                $order->set_payment_method($this->gateway_id);
            }
        }
    }


    /**
     * Get customer country (shipping or billing)
     */
    private function get_customer_country()
    {
        $use_shipping = WC()->session->get('ship_to_different_address');

        $country = $use_shipping
            ? WC()->customer->get_shipping_country()
            : WC()->customer->get_billing_country();

        // fallback
        if (!$country) {
            $country = WC()->customer->get_shipping_country() ?: WC()->customer->get_billing_country();
        }

        return $country;
    }
}

new Custom_Intl_Checkout_Handler();


/*
    Debug hooks - to be removed later
*/
add_action('woocommerce_checkout_order_processed', function ($order_id) {
    error_log('ORDER CREATED: ' . $order_id);
});

add_action('woocommerce_before_checkout_process', function () {
    error_log('CHECKOUT PROCESS STARTED');
});


add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    if (!empty($errors->get_error_codes())) {
        // نرمي exception يوقف creation
        throw new Exception('Checkout validation failed.');
    }
}, 9999, 2);

add_action('woocommerce_new_order', function ($order_id) {
    error_log('NEW ORDER: ' . $order_id);
    error_log('REQUEST URI: ' . $_SERVER['REQUEST_URI']);
    error_log('DOING AJAX: ' . (defined('DOING_AJAX') ? 'YES' : 'NO'));
    error_log('POST: ' . print_r($_POST, true));
});
