<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Checkout_Fields_Modifier
 *
 * Handles WooCommerce checkout customization for Egypt:
 * - Hides city field
 * - Uses state as city
 * - Syncs values before processing
 * - Updates labels via JS
 * - Triggers checkout refresh on state change
 */
class Checkout_Fields_Modifier
{
    /**
     * Initialize hooks.
     */
    public function __construct()
    {
        add_filter('woocommerce_checkout_fields', [$this, 'toggleFieldsRequired'], 999);
        add_action('woocommerce_checkout_process', [$this, 'sync_city_with_state']);
        add_action('woocommerce_checkout_update_order_review', [$this, 'save_billing_area_to_session']);
        add_action('woocommerce_review_order_after_shipping', [$this, 'display_shipping_note']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 20);
    }

    /**
     * Determine if the selected billing country is Egypt.
     *
     * @return bool True if Egypt (EG), false otherwise.
     */
    private function is_egypt_selected(): bool
    {
        $country = WC()->customer ? WC()->customer->get_billing_country() : '';
        return $country === 'EG';
    }


    /**
     * Toggle visibility and required status of city and postcode fields based on country selection.
     * This also must be implemented in JS to handle city change.   
     */
    public function toggleFieldsRequired($fields)
    {
        $fields['billing']['billing_address_2']['label_class'] = []; // نشيل screen-reader-text

        $elements = ['city', 'postcode'];
        $isEgypt = $this->is_egypt_selected();
        $isRequired = !$isEgypt;

        foreach ($elements as $el) {

            foreach (['billing', 'shipping'] as $type) {

                $key = $type . '_' . $el;

                if (!isset($fields[$type][$key])) {
                    continue;
                }

                $fields[$type][$key]['required'] = $isRequired;
            }
        }

        $fields = $this->toggleAreaRequired($fields, $isEgypt);

        return $fields;
    }

    /**
     * It only shows on billing because shipping doesn't have this field, so we don't need to check it there.
     */
    private function toggleAreaRequired($fields, $isEgypt)
    {
        $key = 'billing_area';

        if (isset($fields['billing'][$key])) {
            $fields['billing'][$key]['required'] = $isEgypt;
        }

        return $fields;
    }

    /**
     * Sync city field with state before checkout processing.
     * Ensures WooCommerce receives a valid city value.
     *
     * Only applied when country is Egypt.
     *
     * @return void
     */
    public function sync_city_with_state()
    {
        if (!$this->is_egypt_selected()) {
            return;
        }

        if (!empty($_POST['billing_state'])) {
            $_POST['billing_city'] = sanitize_text_field($_POST['billing_state']);
        }

        if (!empty($_POST['shipping_state'])) {
            $_POST['shipping_city'] = sanitize_text_field($_POST['shipping_state']);
        }
    }

    public function save_billing_area_to_session($posted_data)
    {
        error_log('posted_data = ' . var_export($posted_data, true));
        parse_str($posted_data, $data);

        if (!empty($data['billing_area']) && WC()->session) {
            WC()->session->set(
                'billing_area',
                sanitize_text_field($data['billing_area'])
            );

            error_log(
                'session billing_area = ' .
                    var_export(WC()->session->get('billing_area'), true)
            );
        }
    }

    public function display_shipping_note()
    {
        $chosen_methods = WC()->session->get('chosen_shipping_methods');

        if (empty($chosen_methods)) {
            return;
        }

        error_log(print_r(WC()->session->get('chosen_shipping_methods'), true));

        if (in_array('flat_rate:2', $chosen_methods, true)) {
            echo '<tr class="shipping-note">
                <td colspan="2">
                  <div class="shipping-note-content">
                يستغرق الشحن داخل القاهرة من 3-4 أيام عمل وخارجها من 4-7 أيام عمل.
                  </div>
                </td>
              </tr>';
        }
    }

    /**
     * Enqueue checkout JS file.
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        if (!is_checkout()) return;

        $base_url  = plugin_dir_url(__FILE__);
        $base_path = plugin_dir_path(__FILE__);

        wp_enqueue_script(
            'checkout-fields-modifier-js',
            $base_url . 'checkout-fields-modifier.js',
            ['jquery'],
            filemtime($base_path . 'checkout-fields-modifier.js'),
            true
        );

        wp_enqueue_style(
            'checkout-fields-modifier-css',
            $base_url . 'checkout-fields-modifier.css',
            [],
            filemtime($base_path . 'checkout-fields-modifier.css')
        );
    }
}

// Initialize the class
new Checkout_Fields_Modifier();
