<?php

/**
 * Plugin Name: Matjar - publisher Taxonomy
 * Description: Adds book-related structure for WooCommerce products (publishers taxonomy).
 * Version: 1.0
 * Author: Islam Zenbaei
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Book Publisher Taxonomy
 */
add_action('init', function () {

    register_taxonomy(
        'publisher',
        'product',
        [
            'labels' => [
                'name'              => 'Publishers',
                'singular_name'     => 'Publisher',
                'search_items'      => 'Search Publishers',
                'all_items'         => 'All Publishers',
                'edit_item'         => 'Edit Publisher',
                'update_item'       => 'Update Publisher',
                'add_new_item'      => 'Add New Publisher',
                'new_item_name'     => 'New Publisher Name',
                'menu_name'         => 'Publishers',
            ],

            'public'            => true,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rest_base' => 'publisher',
            // يخليها تظهر تحت Products
            'show_in_menu'      => 'edit.php?post_type=product',
            'rewrite'           => ['slug' => 'publisher']
        ]
    );
});

/**
 * Manually save 'publisher' taxonomy to product
 **/
add_action('woocommerce_rest_insert_product_object', function ($product, $request) {

    if (!empty($request['publisher']) && is_array($request['publisher'])) {

        $valid_terms = array_map('intval', $request['publisher']);

        wp_set_post_terms(
            $product->get_id(),
            $valid_terms,
            'publisher',
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
 * 3️⃣ Validate required publisher before saving product
 * ---------------------------------------------------------
 */
add_action('woocommerce_admin_process_product_object', function ($product) {

    if (empty($_POST['publisher']) || empty($_POST['publisher'][0])) {

        // Stop saving and show error
        WC_Admin_Meta_Boxes::add_error(
            __('Please select a publisher before saving the product.', 'woocommerce')
        );

        return;
    }
});


/**
 * ---------------------------------------------------------
 * 4️⃣ Save publisher taxonomy when valid
 * ---------------------------------------------------------
 */
add_action('save_post_product', function ($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!empty($_POST['publisher']) && is_array($_POST['publisher'])) {

        $publisher_ids = array_map('intval', $_POST['publisher']);

        wp_set_post_terms(
            $post_id,
            $publisher_ids,
            'publisher'
        );
    }
});
