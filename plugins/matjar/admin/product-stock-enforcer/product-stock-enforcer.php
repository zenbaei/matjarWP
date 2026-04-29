<?php

/**
 * Plugin Name: Matjar - Product Stock Enforcer
 * Description: Ensures all WooCommerce products have stock management enabled, minimum stock of 1, and no backorders.
 * Author: Matjar
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

if (!class_exists('Matjar_Product_Stock_Enforcer')) {

    class Matjar_Product_Stock_Enforcer
    {

        /**
         * Constructor
         */
        public function __construct()
        {
            add_action(
                'woocommerce_admin_process_product_object',
                [$this, 'enforce_product_stock_defaults']
            );
        }

        /**
         * Enforce stock rules when product is saved
         *
         * @param WC_Product $product
         */
        public function enforce_product_stock_defaults($product)
        {

            if (!$product || !is_a($product, 'WC_Product')) {
                return;
            }

            // 🔁 Skip grouped/external products (optional safety)
            if ($product->is_type('grouped') || $product->is_type('external')) {
                return;
            }

            if (!$product->is_in_stock())
                return;

            // ✅ Always enable stock management
            $product->set_manage_stock(true);

            // ✅ Ensure stock is at least 1
            $stock = $product->get_stock_quantity();

            if ($stock === null || $stock < 1) {
                $product->set_stock_quantity(1);
            }

            // ✅ Disable backorders
            $product->set_backorders('no');
        }
    }

    // Initialize plugin
    new Matjar_Product_Stock_Enforcer();
}
