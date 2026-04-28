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
