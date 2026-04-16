<?php

namespace Matjar\Product;

defined('ABSPATH') || exit;

if (!defined('FILTER_VALIDATE_BOOL')) {
    define('FILTER_VALIDATE_BOOL', 258);
}

/**
 * Product Validation Module
 *
 * Validates required WooCommerce product fields:
 * - Regular Price
 * - Shipping Weight
 * - Product Description
 *
 * Behavior:
 * - Runs when product is saved
 * - Skips draft / auto-draft
 * - Forces product back to draft if invalid
 * - Displays alert message in admin (temporary debugging UI)
 */
class Validation
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
            [$this, 'show_alert']
        );
    }

    /**
     * Validate product before save
     *
     * @param \WC_Product $product
     * @return void
     */
    public function validate_product($product)
    {

        // Skip drafts
        if (in_array($product->get_status(), ['draft', 'auto-draft'])) {
            return;
        }

        $errors = [];

        /**
         * Validate Regular Price
         */
        if (!$product->get_regular_price()) {
            $errors[] = __('Product price is required.', 'matjar');
        }

        /**
         * Validate Shipping Weight
         */
        if (!$product->get_weight()) {
            $errors[] = __('Shipping weight is required.', 'matjar');
        }

        /**
         * Validate Description
         */
        if (!trim($product->get_description())) {
            $errors[] = __('Product description is required.', 'matjar');
        }

        /**
         * Handle Validation Errors
         */
        if (!empty($errors)) {

            set_transient(
                'matjar_product_validation_errors',
                $errors,
                30
            );

            // Force product back to draft
            $product->set_status('draft');
        }
    }

    /**
     * Show validation alert (temporary debugging UI)
     *
     * @return void
     */
    public function show_alert()
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
}
