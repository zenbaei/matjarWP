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

            // Hook for quick edit - intercept before save
            add_filter(
                'wp_insert_post_data',
                [$this, 'validate_on_quick_edit'],
                10,
                2
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
         * Check and validate quick edit
         *
         * @param array $data
         * @param array $postarr
         * @return array
         */
        public function validate_on_quick_edit($data, $postarr)
        {
            // Only for products
            if ($data['post_type'] !== 'product') {
                return $data;
            }

            // Skip autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return $data;
            }

            // Check if this is quick edit
            if (!isset($_POST['_inline_edit']) && (!isset($_POST['action']) || $_POST['action'] !== 'inline-edit')) {
                return $data;
            }

            if (!current_user_can('edit_product', $postarr['ID'])) {
                return $data;
            }

            $product = wc_get_product($postarr['ID']);
            if (!$product) {
                return $data;
            }

            // Run validation
            $this->validate_product($product);

            return $data;
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
             * Validate Short Description
             */
            if (!trim($product->get_short_description())) {
                $errors[] = 'Product short description is required.';
            }

            /**
             * Validate Category
             */
            $category_ids = $product->get_category_ids();

            // If not category is selected, woocommerce automatically assigns category 16 (Uncategorized) to the product. So we need to check if only category 16 is selected and show an error message.
            // Check if only category 16 is selected
            if (count($category_ids) === 1 && $category_ids[0] == 16) {
                $errors[] = 'Please choose a product category (Uncategorized is not allowed).';
            } else {
                // Remove category 16 if present
                $filtered_category_ids = array_diff($category_ids, [16]);
                // Save filtered categories (without 16)
                $product->set_category_ids($filtered_category_ids);
            }


            /**
             * Validate Tags
             */
            $tag_ids = $product->get_tag_ids();

            // Check if tags are empty
            if (empty($tag_ids)) {
                $errors[] = 'Please choose at least one product tag.';
            } else {
                // Remove tag 351 if present
                $filtered_tag_ids = array_diff($tag_ids, [351]);

                // If only tag 351 was set, don't save any tags
                if (empty($filtered_tag_ids)) {
                    $product->set_tag_ids([]);
                } else {
                    // Save filtered tags (without 351)
                    $product->set_tag_ids($filtered_tag_ids);
                }
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
