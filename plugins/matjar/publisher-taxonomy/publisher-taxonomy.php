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
