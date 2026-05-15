<?php

/**
 * Disable WooCommerce shipping calculator on cart page.
 */
/**
 * Remove shipping calculator from cart page.
 */
/**
 * Disable shipping calculator output completely.
 */
add_action('wp_head', function () {

    if (is_cart()) {
        echo '<style>
    .woocommerce-shipping-calculator {
        display: none !important;
    }
    .woocommerce-shipping-destination {
        display: none !important;
    }
</style>';
    }
});
