<?php

/**
 * Plugin Name: Matjar - Product Validation
 * Description: Validates WooCommerce product fields before saving.
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Single_Product_Page')) {

    class Single_Product_Page
    {

        /**
         * Constructor
         */
        public function __construct()
        {

            add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        }

        /**
         * Enqueue scripts and styles
         */
        public function enqueue(): void
        {

            wp_enqueue_script(
                'single-product-page-js',
                plugin_dir_url(__FILE__) . 'js/single-product-page.js',
                ['jquery'],
                filemtime(__DIR__ . '/js/single-product-page.js'),
                true
            );
        }
    }

    // Initialize
    new Single_Product_Page();
}
