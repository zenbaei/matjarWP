<?php

/**
 * Plugin Name: Matjar - Writer Taxonomy
 * Description: Adds book-related structure for WooCommerce products (Writers taxonomy).
 * Version: 1.0
 * Author: Islam Zenbaei
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Book Writer Taxonomy
 */
add_action('init', function () {

    register_taxonomy(
        'writer',
        'product',
        [
            'labels' => [
                'name'              => 'Writers',
                'singular_name'     => 'Writer',
                'search_items'      => 'Search Writers',
                'all_items'         => 'All Writers',
                'edit_item'         => 'Edit Writer',
                'update_item'       => 'Update Writer',
                'add_new_item'      => 'Add New Writer',
                'new_item_name'     => 'New Writer Name',
                'menu_name'         => 'Writers',
            ],

            'public'            => true,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rest_base' => 'writer',
            // يخليها تظهر تحت Products
            'show_in_menu'      => 'edit.php?post_type=product',
            'rewrite'           => ['slug' => 'writer']
        ]
    );
});

/**
 * Manually save 'writer' taxonomy to product
 **/
add_action('woocommerce_rest_insert_product_object', function ($product, $request) {

    if (!empty($request['writer']) && is_array($request['writer'])) {

        $valid_terms = array_map('intval', $request['writer']);

        wp_set_post_terms(
            $product->get_id(),
            $valid_terms,
            'writer',
            false
        );
    }
}, 20, 2);


/**
 * Flush rewrite rules on activation
 */
register_activation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

/**
 * Flush rewrite rules on deactivation
 */
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});


/**
 * ---------------------------------------------------------
 * 3️⃣ Validate required Writer before saving product
 * ---------------------------------------------------------
 */
add_action('woocommerce_admin_process_product_object', function ($product) {

    if (empty($_POST['writer']) || empty($_POST['writer'][0])) {

        // Stop saving and show error
        WC_Admin_Meta_Boxes::add_error(
            __('Please select a Writer before saving the product.', 'woocommerce')
        );

        return;
    }
});


/**
 * ---------------------------------------------------------
 * 4️⃣ Save Writer taxonomy when valid
 * ---------------------------------------------------------
 */
add_action('save_post_product', function ($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!empty($_POST['writer']) && is_array($_POST['writer'])) {

        $writer_ids = array_map('intval', $_POST['writer']);

        wp_set_post_terms(
            $post_id,
            $writer_ids,
            'writer'
        );
    }
});
