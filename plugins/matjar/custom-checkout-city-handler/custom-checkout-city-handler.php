<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Matjar_Checkout_Customizations
 *
 * Handles WooCommerce checkout customization for Egypt:
 * - Hides city field
 * - Uses state as city
 * - Syncs values before processing
 * - Updates labels via JS
 * - Triggers checkout refresh on state change
 */
class Matjar_Checkout_Customizations
{
    /**
     * Initialize hooks.
     */
    public function __construct()
    {
        add_filter('woocommerce_checkout_fields', [$this, 'toggleFieldsVisibility'], 999);
        add_action('woocommerce_checkout_process', [$this, 'sync_city_with_state']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 20);
        //  add_filter('woocommerce_default_address_fields', [$this, 'custom_override_default_locale_fields']);
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
    public function toggleFieldsVisibility($fields)
    {
        $elements = ['city', 'postcode'];
        $isEgypt = $this->is_egypt_selected() ? true : false;

        foreach ($elements as $el) {
            if (isset($fields['billing']['billing_' . $el])) {
                $fields['billing']['billing_' . $el]['class'][] =  $isEgypt ? 'hidden' : '';
                $fields['billing']['billing_' . $el]['required'] =  $isEgypt ? false : true;
            }

            if (isset($fields['shipping']['shipping_' . $el])) {
                $fields['shipping']['shipping_' . $el]['class'][] =  $isEgypt ? 'hidden' : '';
                $fields['shipping']['shipping_' . $el]['required'] =  $isEgypt ? false : true;
            }
        }

        $this->showAreaForEgyptOnly($fields, $isEgypt);

        return $fields;
    }

    /**
     * Show state field as area for Egypt only.
     */
    private function showAreaForEgyptOnly(&$fields, $isEgypt)
    {
        if (isset($fields['billing']['billing_area'])) {
            $fields['billing']['billing_area']['class'][] =  $isEgypt ? 'hidden' : '';
            $fields['billing']['billing_area']['required'] =  $isEgypt ? false : true;
        }

        if (isset($fields['shipping']['shipping_area'])) {
            $fields['shipping']['shipping_area']['class'][] =  $isEgypt ? 'hidden' : '';
            $fields['shipping']['shipping_area']['required'] =  $isEgypt ? false : true;
        }
    }



    /**
     * Not renaming anymore.
     */
    public function changeStateLabel($fields)
    {
        if ($this->is_egypt_selected()) {
            if (isset($fields['billing']['billing_state'])) {
                $fields['billing']['billing_state']['label'] = 'المدينة';
            }

            if (isset($fields['shipping']['shipping_state'])) {
                $fields['shipping']['shipping_state']['label'] = 'لمدينة';
            }
        }
        return $fields;
    }

    /**
     * This function worked for all fields except country and last name, So I used ordering from javascript file.
     *
    public function custom_override_default_locale_fields($fields)
    {
        $fields['first_name']['priority'] = 1;
        $fields['last_name']['priority'] = 2;

        $fields['country']['priority'] = 3;
        $fields['city']['priority'] = 4;

        $fields['state']['priority'] = 5;
        $fields['address_1']['priority'] = 6;
        $fields['address_2']['priority'] = 7;
        return $fields;
    }
     */


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

        wp_enqueue_script(
            'custom-checkout-handler',
            plugin_dir_url(__FILE__) . 'custom-checkout-handler.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . 'custom-checkout-handler.js'), // 🔥
            true
        );
    }
}

// Initialize the class
new Matjar_Checkout_Customizations();
