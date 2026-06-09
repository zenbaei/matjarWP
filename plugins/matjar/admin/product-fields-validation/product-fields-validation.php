<?php

/**
 * Plugin Name: Matjar - Product Validation
 * Description: Validates WooCommerce product fields before saving.
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Product_Fields_Validation')) {

    class Product_Fields_Validation
    {

        /**
         * Constructor
         */
        public function __construct()
        {
            add_action(
                'woocommerce_admin_process_product_object',
                [$this, 'validate_product']
            );

            add_action(
                'admin_footer',
                [$this, 'show_validation_alert']
            );

            add_action(
                'admin_footer',
                [$this, 'force_open_product_in_new_tab']
            );

            add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        }

        /**
         * Validate product before save
         *
         * @param WC_Product $product
         */
        public function validate_product($product)
        {

            if (!$product) return;

            // Skip drafts
            if (in_array($product->get_status(), ['draft', 'auto-draft'])) {
                return;
            }

            $errors = [];

            /**
             * Validate Price
             */
            if (!$product->get_regular_price()) {
                $errors[] = 'Product price is required.';
            }

            /**
             * Validate Weight
             */
            if (!$product->get_weight()) {
                $errors[] = 'Shipping weight is required.';
            }

            /**
             * Validate Description
             */
            if (!trim($product->get_description())) {
                $errors[] = 'Product description is required.';
            }

            /**
             * Handle Errors
             */
            if (!empty($errors)) {

                set_transient(
                    'matjar_product_validation_errors',
                    $errors,
                    30
                );

                // Force draft
                $product->set_status('draft');
            }
        }

        /**
         * Show validation alert in admin
         */
        public function show_validation_alert()
        {

            $errors = get_transient('matjar_product_validation_errors');

            if (!$errors) {
                return;
            }

            delete_transient('matjar_product_validation_errors');
?>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    alert(
                        "Product Validation:\n\n" +
                        <?php echo json_encode(implode("\n", $errors)); ?>
                    );
                });
            </script>

        <?php
        }

        /**
         * Force product edit links to open in new tab
         */
        public function force_open_product_in_new_tab()
        {

            if (!function_exists('get_current_screen')) return;

            $screen = get_current_screen();

            if (!$screen || $screen->post_type !== 'product') {
                return;
            }
        ?>

            <script>
                function openProductNewTab() {
                    jQuery('#the-list .row-title').attr('target', '_blank');
                    jQuery('#the-list .row-actions .edit a').attr('target', '_blank');
                    jQuery('#the-list .column-thumb a').attr('target', '_blank');
                }

                jQuery(document).ready(openProductNewTab);
                jQuery(document).ajaxComplete(openProductNewTab);
            </script>

<?php
        }


        /**
         * Enqueue admin scripts
         */
        public function enqueue(): void
        {

            wp_enqueue_script(
                'product-fields-validation',
                plugin_dir_url(__FILE__) . 'js/product-fields-validation.js',
                ['jquery'],
                filemtime(__DIR__ . '/js/product-fields-validation.js'),
                true
            );
        }
    }

    // Initialize
    new Product_Fields_Validation();
}
