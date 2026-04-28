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
        add_action('woocommerce_review_order_after_order_total', [$this, 'render_quote_row']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        add_action('wp_ajax_get_shipping_quote', [$this, 'get_shipping_quote']);
        add_action('wp_ajax_nopriv_get_shipping_quote', [$this, 'get_shipping_quote']);
    }

    /**
     * 🟢 Render hidden row in checkout
     */
    public function render_quote_row()
    {
        echo '
        <tr class="international-quote-row" style="display:none;">
            <th>الشحن الدولي</th>
            <td>
                <button type="button" id="get-international-quote" class="button alt">
                    احصل على سعر للشحن
                </button>
                <div id="quote-result" style="margin-top:10px;"></div>
            </td>
        </tr>
        ';
    }

    /**
     * 🔴 AJAX handler
     */
    public function get_shipping_quote()
    {

        // ✅ Security check
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intl_shipping_handler_js_nonce')) {
            wp_send_json_error('Unauthorized');
        }

        if (!WC()->cart) {
            wp_send_json_error('Cart empty');
        }

        $email = sanitize_email($_POST['email']);

        if (!$email) {
            wp_send_json_error('Invalid email');
        }

        $customer = WC()->customer;

        // 🧠 إنشاء order حقيقي
        $order = wc_create_order();

        foreach (WC()->cart->get_cart() as $item) {
            $order->add_product($item['data'], $item['quantity']);
        }

        // 🧾 بيانات العميل
        $order->set_billing_email($email);
        $order->set_billing_country($customer->get_billing_country());
        $order->set_shipping_country($customer->get_shipping_country());

        // 🏷️ حالة خاصة (مش مدفوع)
        $order->set_status('pending', 'Shipping quote request');

        $order->calculate_totals();
        $order->save();

        $order_id = $order->get_id();

        // 📦 حساب الوزن
        $weight = 0;
        foreach (WC()->cart->get_cart() as $item) {
            $weight += (float)$item['data']->get_weight() * $item['quantity'];
        }

        // 📨 إيميل ليك
        $admin_email = get_option('admin_email');

        $subject = 'طلب شحن دولي #' . $order_id;

        $message = "
        Order ID: {$order_id}
        Customer Email: {$email}
        Country: {$customer->get_shipping_country()}
        Weight: {$weight} KG
    ";

        wp_mail($admin_email, $subject, $message);

        // 📨 إيميل للعميل
        wp_mail($email, 'تم استلام طلب الشحن', "طلبك رقم {$order_id} تم استلامه، وسيتم التواصل معك قريبًا.");

        wp_send_json_success([
            'message' => "تم إرسال الطلب بنجاح (رقم الطلب: #{$order_id})"
        ]);
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

    /**
     * 🌐 Aramex API (placeholder)
     */
    private function get_aramex_rate($weight, $country)
    {

        // مؤقت (test)
        if ($weight <= 1) return 15;
        if ($weight <= 5) return 25;
        return 40;

        // هنا تحط API الحقيقي
    }
}

// 🚀 Init
new Intl_Shipping_Handler();
