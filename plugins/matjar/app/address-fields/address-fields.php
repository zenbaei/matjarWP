<?php

/**
 * Hide WooCommerce city fields for Egypt.
 */
class Address_Fields
{

    public function __construct()
    {

        add_action(
            'wp_enqueue_scripts',
            array($this, 'enqueue_assets')
        );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_assets()
    {

        if (! is_account_page()) {
            return;
        }

        $css_path = plugin_dir_path(__FILE__) . 'address-fields.css';
        $js_path  = plugin_dir_path(__FILE__) . 'address-fields.js';

        wp_enqueue_style(
            'address-fields',
            plugin_dir_url(__FILE__) . 'address-fields.css',
            array(),
            filemtime($css_path)
        );

        wp_enqueue_script(
            'address-fields',
            plugin_dir_url(__FILE__) . 'address-fields.js',
            array('jquery'),
            filemtime($js_path),
            true
        );
    }
}

new Address_Fields();
