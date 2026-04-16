<?php

if (!defined('ABSPATH')) {
    exit;
}

// Hide city field + rename state
add_filter('woocommerce_checkout_fields', function($fields){

    if (isset($fields['billing']['billing_city'])) {
        $fields['billing']['billing_city']['class'][] = 'hidden';
    }

    if (isset($fields['shipping']['shipping_city'])) {
        $fields['shipping']['shipping_city']['class'][] = 'hidden';
    }

    if (isset($fields['billing']['billing_state'])) {
        $fields['billing']['billing_state']['label'] = 'City';
    }

    if (isset($fields['shipping']['shipping_state'])) {
        $fields['shipping']['shipping_state']['label'] = 'City';
    }

    return $fields;
});

// Sync city = state
add_action('woocommerce_checkout_process', function(){

    if (!empty($_POST['billing_state'])) {
        $_POST['billing_city'] = sanitize_text_field($_POST['billing_state']);
    }

    if (!empty($_POST['shipping_state'])) {
        $_POST['shipping_city'] = sanitize_text_field($_POST['shipping_state']);
    }

});