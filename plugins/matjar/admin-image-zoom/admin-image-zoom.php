<?php
/**
 * Plugin Name: Admin Product Image Zoom
 * Description: Zoom WooCommerce admin images safely
 */

add_action('admin_enqueue_scripts', function ($hook) {

    // 🚫 منع AJAX (مهم)
    if (wp_doing_ajax()) return;

    // صفحات المنتجات فقط
    if (!in_array($hook, ['post.php', 'post-new.php'])) return;

    $post_id = $_GET['post'] ?? 0;

    if ($post_id && get_post_type($post_id) !== 'product') return;

wp_enqueue_script(
    'admin-zoom-js',
    plugin_dir_url(__FILE__) . 'admin-zoom.js',
    ['jquery'],
    filemtime(plugin_dir_path(__FILE__) . 'admin-zoom.js'),
    true
);
});