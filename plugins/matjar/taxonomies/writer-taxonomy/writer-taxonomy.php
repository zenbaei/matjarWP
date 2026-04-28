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
 * Make sure rest is handled correctly then remove this function
 **/
/*
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
*/

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
 * Slugify the arabic names of writers and publishers to make them URL-friendly.
 */
add_filter('wp_insert_term_data', function ($data, $taxonomy) {

    if (!in_array($taxonomy, ['writer', 'publisher'])) {
        return $data;
    }

    // if slug already set manually → keep it
    if (!empty($data['slug'])) {
        return $data;
    }

    $name = $data['name'];

    if (function_exists('transliterator_transliterate')) {
        $slug = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; Lower()',
            $name
        );
    } else {
        $slug = $name;
    }

    $slug = sanitize_title($slug);

    $data['slug'] = $slug;

    return $data;
}, 10, 2);
