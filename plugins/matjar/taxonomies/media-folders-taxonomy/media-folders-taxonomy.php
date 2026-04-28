<?php

/**
 * Plugin Name: Matjar - Media Folders Taxonomy
 */

if (!defined('ABSPATH')) exit;

class Media_Folders_Taxonomy
{
    const TAXONOMY = 'media_folder';

    public function __construct()
    {
        // Core
        add_action('init', [$this, 'register_taxonomy']);

        // Media Library
        add_action('restrict_manage_posts', [$this, 'add_filter_dropdown']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_footer-upload.php', [$this, 'init_select2']);
        add_action('pre_get_posts', [$this, 'filter_no_folder']);

        // Delete logic
        add_action('pre_delete_term', [$this, 'delete_folder_attachments'], 10, 2);

        // WooCommerce
        add_action('woocommerce_product_options_general_product_data', [$this, 'product_dropdown']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);
    }

    /**
     * Register taxonomy
     */
    public function register_taxonomy()
    {
        register_taxonomy(self::TAXONOMY, 'attachment', [
            'labels' => [
                'name' => 'Media Folders',
                'singular_name' => 'Media Folder',
            ],
            'public' => false,
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts'
            ]
        ]);
    }

    /**
     * Dropdown filter
     */
    public function add_filter_dropdown()
    {
        global $pagenow;
        if ($pagenow !== 'upload.php') return;

        $selected = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);

        echo '<select id="media-folder-filter" name="term">';
        echo '<option value="">All Folders</option>';
        echo '<option value="no_folder" ' . selected($selected, 'no_folder', false) . '>No Folder</option>';

        foreach ($terms as $term) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($term->slug),
                selected($selected, $term->slug, false),
                esc_html($term->name)
            );
        }

        echo '</select>';
        echo '<input type="hidden" name="taxonomy" value="' . self::TAXONOMY . '">';
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_assets($hook)
    {
        if ($hook !== 'upload.php') return;

        // Load only if WooCommerce موجود
        if (function_exists('WC')) {
            wp_enqueue_script('selectWoo');
            wp_enqueue_style('woocommerce_admin_styles');
        } else {
            wp_enqueue_script('selectWoo');
            wp_enqueue_style('select2');
        }
    }

    /**
     * Init SelectWoo
     */
    public function init_select2()
    {
?>
        <script>
            jQuery(function($) {
                $('#media-folder-filter').selectWoo({
                    width: '220px',
                    placeholder: 'Search folder...',
                    allowClear: true
                });
            });
        </script>
<?php
    }

    /**
     * Filter: no folder
     */
    public function filter_no_folder($query)
    {
        global $pagenow;

        if (!is_admin() || !$query->is_main_query() || $pagenow !== 'upload.php') return;

        if (
            empty($_GET['taxonomy']) ||
            $_GET['taxonomy'] !== self::TAXONOMY ||
            empty($_GET['term']) ||
            $_GET['term'] !== 'no_folder'
        ) return;

        $query->set('tax_query', [
            [
                'taxonomy' => self::TAXONOMY,
                'operator' => 'NOT EXISTS'
            ]
        ]);
    }

    /**
     * Delete attachments when folder deleted
     */
    public function delete_folder_attachments($term_id, $taxonomy)
    {
        if ($taxonomy !== self::TAXONOMY) return;

        $attachments = get_posts([
            'post_type' => 'attachment',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => self::TAXONOMY,
                    'field' => 'term_id',
                    'terms' => $term_id,
                ]
            ]
        ]);

        foreach ($attachments as $id) {
            wp_delete_attachment($id, true);
        }
    }

    /**
     * WooCommerce dropdown
     */
    public function product_dropdown()
    {
        global $post;

        $current = get_post_meta($post->ID, '_media_folder_id', true);

        $folders = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);

        echo '<div class="options_group">';
        echo '<p class="form-field">';
        echo '<label>Media Folder</label>';

        echo '<select name="_media_folder_id" id="_media_folder_id" class="wc-enhanced-select">';

        echo '<option value="">Select folder</option>';

        foreach ($folders as $folder) {
            printf(
                '<option value="%d" %s>%s</option>',
                $folder->term_id,
                selected($current, $folder->term_id, false),
                esc_html($folder->name)
            );
        }

        echo '</select></p></div>';
    }

    /**
     * Save product meta
     */
    public function save_product_meta($post_id)
    {
        if (!isset($_POST['_media_folder_id'])) return;

        $folder_id = intval($_POST['_media_folder_id']);

        if ($folder_id > 0) {
            update_post_meta($post_id, '_media_folder_id', $folder_id);
        } else {
            delete_post_meta($post_id, '_media_folder_id');
        }
    }
}

/**
 * Init plugin
 */
new Media_Folders_Taxonomy();
