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

        add_action('wp_ajax_get_shipping_quote_full', [$this, 'handle_quote']);
        add_action('wp_ajax_nopriv_get_shipping_quote_full', [$this, 'handle_quote']);
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
     * 🔵 Enqueue JS
     */
    public function enqueue_scripts()
    {

        if (!is_checkout()) return;

        wp_enqueue_script(
            'intl-quote-js',
            plugin_dir_url(__FILE__) . 'intl-quote.js',
            ['jquery'],
            null,
            true
        );

        wp_localize_script('intl-quote-js', 'intlQuote', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    /**
     * 🔴 AJAX handler
     */
    public function handle_quote()
    {

        if (!WC()->customer || !WC()->cart) {
            wp_send_json_error('No data');
        }

        $customer = WC()->customer;

        $data = [
            'country' => $customer->get_shipping_country(),
            'city'    => $customer->get_shipping_city(),
            'postcode' => $customer->get_shipping_postcode(),
            'weight'  => 0,
            'items'   => []
        ];

        foreach (WC()->cart->get_cart() as $item) {
            $product = $item['data'];

            $item_weight = (float)$product->get_weight() * $item['quantity'];

            $data['weight'] += $item_weight;

            $data['items'][] = [
                'name'   => $product->get_name(),
                'qty'    => $item['quantity'],
                'weight' => $item_weight
            ];
        }

        // 🔥 هنا تقدر تبعت لـ Aramex
        $price = $this->get_aramex_rate($data['weight'], $data['country']);

        if ($price) {
            wp_send_json_success([
                'price' => $price,
                'html'  => "تكلفة الشحن: $" . $price
            ]);
        } else {
            wp_send_json_error('تعذر حساب الشحن');
        }
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
