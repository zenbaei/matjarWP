<?php

add_filter('etheme_ajax_search_products_query', function ($args) {

    $args['search_columns'] = ['post_title'];

    return $args;
});

add_filter('theme_mod_search_by_sku_et-desktop', function ($value) {
    error_log('theme_mod filter fired');
    return false;
});

add_filter('etheme_ajax_search_posts_query', function ($args) {
    $args['post__in'] = [0];
    return $args;
});


add_action('wp_enqueue_scripts', function () {

    $base_url  = plugin_dir_url(__FILE__);
    $base_path = plugin_dir_path(__FILE__);

    wp_enqueue_style(
        'matjar-search-css',
        $base_url . 'css/search.css',
        [],
        filemtime($base_path . 'css/search.css')
    );

    wp_enqueue_script(
        'matjar-search-js',
        $base_url . 'js/search.js',
        ['jquery'],
        filemtime($base_path . 'js/search.js'),
        true
    );
});
