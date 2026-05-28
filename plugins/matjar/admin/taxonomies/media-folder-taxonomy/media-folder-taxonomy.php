<?php

/**
 * Plugin Name: Matjar - Media Folders
 */

if (!defined('ABSPATH')) {
    exit;
}

class Media_Folder_Taxonomy
{

    const TAXONOMY = 'media_folder';

    public function __construct()
    {
        /**
         * Core
         */
        add_action('init', [$this, 'register_taxonomy']);

        /**
         * Media Library UI
         */
        add_action('restrict_manage_posts', [$this, 'add_media_folder_dropdown']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_footer-upload.php', [$this, 'init_select2']);
        add_action('pre_get_posts', [$this, 'filter_unattached_medias']);

        /**
         * Bulk Delete Folder + Media
         */
        add_filter('bulk_actions-edit-media_folder', [$this, 'add_delete_folder_bulk_action']);
        add_filter('handle_bulk_actions-edit-media_folder', [$this, 'handle_delete_folder_bulk_action'], 10, 3);

        /**
         * Delete Folder Hooks
         */
        add_action('pre_delete_term', [$this, 'delete_folder_attachments_before_term_delete'], 10, 2);
        add_action('delete_media_folder', [$this, 'delete_folder_attachments'], 10, 3);

        /**
         * Notices
         */
        add_action('admin_notices', [$this, 'delete_folder_notice']);

        /**
         * Admin JS
         */
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_js']);

        /**
         * WooCommerce Meta
         */
        add_action('init', [$this, 'register_product_meta']);

        /**
         * WooCommerce Product Fields
         */
        add_action(
            'woocommerce_product_options_general_product_data',
            [$this, 'add_product_media_folder_dropdown']
        );

        add_action('admin_footer', [$this, 'enable_product_select2']);

        /**
         * WooCommerce Save
         */
        add_action(
            'woocommerce_process_product_meta',
            [$this, 'save_product_media_folder']
        );

        add_action(
            'woocommerce_after_product_object_save',
            [$this, 'sync_product_images'],
            20
        );

        /**
         * Product Delete
         */
        add_action(
            'before_delete_post',
            [$this, 'delete_media_folder_and_images_on_product_delete']
        );

        /**
         * Bulk Assign Media Folder
         */
        add_filter('bulk_actions-upload', [$this, 'add_assign_folder_bulk_action']);

        add_filter(
            'handle_bulk_actions-upload',
            [$this, 'handle_assign_folder_bulk_action'],
            10,
            3
        );

        add_action('admin_notices', [$this, 'bulk_assign_admin_notices']);
    }

    /**
     * 1️⃣ Register Taxonomy
     */
    public function register_taxonomy()
    {
        register_taxonomy(
            self::TAXONOMY,
            'attachment',
            [
                'labels' => [
                    'name'          => 'Media Folders',
                    'singular_name' => 'Media Folder',
                ],
                'public'                => false,
                'hierarchical'          => true,
                'show_ui'               => true,
                'show_admin_column'     => true,
                'show_in_rest'          => true,
                'rest_controller_class' => 'WP_REST_Terms_Controller',
                'map_meta_cap'          => true,
                'update_count_callback' => '_update_post_term_count',
                'capabilities' => [
                    'manage_terms' => 'manage_categories',
                    'edit_terms'   => 'manage_categories',
                    'delete_terms' => 'manage_categories',
                    'assign_terms' => 'edit_posts'
                ]
            ]
        );
    }

    /**
     * 2️⃣ Add Dropdown (builds correct WP URL format)
     */
    public function add_media_folder_dropdown()
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
        echo '<option value="unattached" ' . selected($selected, 'unattached', false) . '>Media Without Folder</option>';

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
     * ---------------------------------------------------------
     * Enqueue SelectWoo for Media Library
     * ---------------------------------------------------------
     * Loads SelectWoo (WordPress' Select2 fork) only on the
     * Media Library page so we can use searchable dropdowns.
     */
    public function enqueue_assets($hook)
    {
        if ($hook !== 'upload.php') {
            return;
        }

        wp_enqueue_script('selectWoo');
        wp_enqueue_style('woocommerce_admin_styles');

        wp_enqueue_script(
            'matjar-media-folder-js',
            plugin_dir_url(__FILE__) . 'js/media-folder.js',
            ['jquery', 'selectWoo'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'matjar-media-folder-css',
            plugin_dir_url(__FILE__) . 'css/media-folder.css',
            [],
            '1.0'
        );
    }

    /**
     * ---------------------------------------------------------
     * Enable SelectWoo on Media Folder Dropdown
     * ---------------------------------------------------------
     *
     * This script enhances the Media Folder filter dropdown
     * using SelectWoo (WordPress' version of Select2).
     *
     * Result:
     * - The dropdown becomes searchable
     * - Users can type to quickly find a folder
     */
    public function init_select2()
    {
?>
        <script>
            jQuery(function($) {

                const $folder = $('#media-folder-filter');

                $folder.selectWoo({
                    width: '220px',
                    placeholder: 'Search folder...',
                    allowClear: true
                });

                $folder.css('visibility', 'visible');
            });
        </script>
    <?php
    }

    /**
     * ---------------------------------------------------------
     * Filter Media Library: show media with NO folder
     * ---------------------------------------------------------
     *
     * When the dropdown sends:
     *
     * term = no_folder
     * taxonomy = media_folder
     *
     * This modifies the Media Library query to return attachments
     * that do NOT have any term assigned in the media_folder taxonomy.
     */
    public function filter_unattached_medias($query)
    {
        global $pagenow;

        if (
            !is_admin() ||
            !$query->is_main_query() ||
            $pagenow !== 'upload.php'
        ) {
            return;
        }

        if (
            empty($_GET['taxonomy']) ||
            $_GET['taxonomy'] !== self::TAXONOMY ||
            empty($_GET['term']) ||
            $_GET['term'] !== 'unattached'
        ) {
            return;
        }

        // Remove WordPress default taxonomy filter
        $query->set('taxonomy', '');
        $query->set('term', '');

        // Show attachments with NO folder
        $query->set('tax_query', [
            [
                'taxonomy' => self::TAXONOMY,
                'operator' => 'NOT EXISTS'
            ]
        ]);
    }

    /**
     * Add custom bulk action to delete folder with media
     */
    public function add_delete_folder_bulk_action($actions)
    {
        $actions['delete_folder_with_media'] = 'Delete Folder & Media';
        return $actions;
    }

    /**
     * Handle bulk action
     */
    public function handle_delete_folder_bulk_action($redirect, $action, $term_ids)
    {
        if ($action !== 'delete_folder_with_media') {
            return $redirect;
        }

        foreach ($term_ids as $term_id) {

            // Get all attachments in this folder
            $attachments = get_posts([
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'tax_query'      => [
                    [
                        'taxonomy' => self::TAXONOMY,
                        'field'    => 'term_id',
                        'terms'    => $term_id,
                    ]
                ]
            ]);

            // Delete attachments
            foreach ($attachments as $attachment) {
                wp_delete_attachment($attachment->ID, true);
            }

            // Delete the folder term itself
            wp_delete_term($term_id, self::TAXONOMY);
        }

        return add_query_arg('deleted_folder_media', count($term_ids), $redirect);
    }

    /**
     * Add deleting images logic using 'delete' action
     **/
    public function delete_folder_attachments_before_term_delete($term_id, $taxonomy)
    {
        if ($taxonomy !== self::TAXONOMY) {
            return;
        }

        // Get all attachments in this folder
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => self::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ]
            ]
        ]);

        if (!empty($attachments)) {
            foreach ($attachments as $attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }
        }
    }

    /**
     * Admin notice after deletion
     */
    public function delete_folder_notice()
    {
        if (!empty($_GET['deleted_folder_media'])) {

            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Folder and its media deleted successfully.</p>';
            echo '</div>';
        }
    }

    /**
     * Enqueue Admin JS
     */
    public function enqueue_admin_js($hook)
    {
        // the ajax loading not working
        return;

        if ($hook !== 'upload.php') {
            return;
        }

        wp_enqueue_script(
            'media-folders-ajax',
            plugin_dir_url(__FILE__) . 'media-folder-ajax.js',
            ['jquery'],
            null,
            true
        );
    }

    /**
     * When a media folder is deleted,
     * also delete all attachments inside it
     */
    public function delete_folder_attachments($term_id, $tt_id, $taxonomy)
    {
        if ($taxonomy !== self::TAXONOMY) {
            return;
        }

        // Get all attachments in this folder
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => self::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ]
            ]
        ]);

        // Delete each attachment permanently
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
    }

    /**
     * ---------------------------------------------------------
     * 1️⃣  Register _media_folder_id meta for WooCommerce REST
     * ---------------------------------------------------------
     * This makes the meta field available in:
     * /wp-json/wc/v3/products
     * and
     * /wp-json/wp/v2/product
     */
    public function register_product_meta()
    {
        register_post_meta('product', '_media_folder_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);
    }

    /**
     * ---------------------------------------------------------
     * 1️⃣ Add searchable Media Folder dropdown (General tab)
     * ---------------------------------------------------------
     * Uses WooCommerce built-in Select2 (wc-enhanced-select)
     * Displays folder names but saves only the folder ID as meta.
     */
    public function add_product_media_folder_dropdown()
    {
        global $post;

        $current_folder = get_post_meta($post->ID, '_media_folder_id', true);

        $folders = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);

        echo '<div class="options_group">';
        echo '<p class="form-field _media_folder_id_field">';
        echo '<label for="_media_folder_id">Media Folder</label>';

        echo '<select 
                name="_media_folder_id" 
                id="_media_folder_id" 
                class="wc-enhanced-select"
                style="width:50%;">';

        echo '<option value="">Select folder</option>';

        if (!is_wp_error($folders)) {
            foreach ($folders as $folder) {
                printf(
                    '<option value="%d" %s>%s</option>',
                    $folder->term_id,
                    selected($current_folder, $folder->term_id, false),
                    esc_html($folder->name)
                );
            }
        }

        echo '</select>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * Manually activate 'select2' searchable dropdown using javascript
     **/
    public function enable_product_select2()
    {
        global $post;

        if (!$post || $post->post_type !== 'product') {
            return;
        }
    ?>
        <script>
            jQuery(function($) {
                $('#media-folder-filter').select2({
                    width: '220px',
                    placeholder: 'Search folder'
                });
            });
        </script>
<?php
    }

    /**
     * ---------------------------------------------------------
     * 2️⃣ Save Media Folder meta when product is saved
     * ---------------------------------------------------------
     * This function is called prior to 'woocommerce_before_product_object_save' hook.
     */
    public function save_product_media_folder($post_id)
    {
        error_log('woocommerce_process_product_meta');

        if (isset($_POST['_media_folder_id'])) {

            /*
             * keep old folder id in order to modify image gallery display order logic later in
             * 'woocommerce_after_product_object_save'
             */
            $old_folder_id = get_post_meta($post_id, '_media_folder_id', true);

            $GLOBALS['old_media_folder'][$post_id] = $old_folder_id;

            $folder_id = intval($_POST['_media_folder_id']);

            if ($folder_id > 0) {
                update_post_meta($post_id, '_media_folder_id', $folder_id);
            } else {
                delete_post_meta($post_id, '_media_folder_id');
            }
        }
    }

    /**
     * Sync product images with selected Media Folder
     * and preserve attachment order.
     */
    public function sync_product_images($product)
    {
        if (!isset($_POST['_media_folder_id'])) {
            return;
        }

        $post_id  = $product->get_id();

        $old = $GLOBALS['old_media_folder'][$post_id] ?? null;
        $folder_id = intval($_POST['_media_folder_id']);

        error_log(print_r([
            'Old Folder id' => $old,
            'New Folder id' => $folder_id,
        ], true));

        /*
         * Same media folder selected.
         * This means the user probably only changed the gallery order
         * or the featured image manually in WooCommerce.
         *
         * We should NOT regenerate the gallery from the folder,
         * otherwise it will overwrite the user ordering.
         */
        if ((int) $old === (int) $folder_id) {
            return;
        }

        $this->set_product_images_by_natural_order($post_id, $folder_id);
    }

    public function set_product_images_by_natural_order($post_id, $folder_id)
    {
        if ($folder_id <= 0) {

            delete_post_meta($post_id, '_media_folder_id');
            delete_post_meta($post_id, '_product_image_gallery');

            set_post_thumbnail($post_id, 0);

            return;
        }

        update_post_meta($post_id, '_media_folder_id', $folder_id);

        // 🔹 Get attachments ordered correctly
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'media_folder',
                    'field'    => 'term_id',
                    'terms'    => $folder_id,
                ],
            ],
        ]);

        if (empty($attachments)) {
            return;
        }

        $image_ids = wp_list_pluck($attachments, 'ID');

        // ✅ Set featured image
        set_post_thumbnail($post_id, $image_ids[0]);

        // ✅ Set gallery manually via meta
        $gallery_ids = array_slice($image_ids, 1);

        update_post_meta(
            $post_id,
            '_product_image_gallery',
            implode(',', $gallery_ids)
        );
    }

    // Delete media folder and its attachments when product is removed from trash
    public function delete_media_folder_and_images_on_product_delete($post_id)
    {
        // Run only for WooCommerce products
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        // Get folder id from metadata
        $folder_id = get_post_meta($post_id, '_media_folder_id', true);

        if (!$folder_id) {
            return;
        }

        // Get all attachments in this media folder
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => 'media_folder',
                    'field'    => 'term_id',
                    'terms'    => $folder_id,
                ],
            ],
        ]);

        // Delete attachments
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }

        // Delete the taxonomy term
        wp_delete_term($folder_id, 'media_folder');
    }

    /**
     * ---------------------------------------------------------
     * Add Bulk Action: Assign to selected folder
     * ---------------------------------------------------------
     * This adds a new option inside the Media Library bulk
     * actions dropdown.
     *
     * The action will use the already selected folder from
     * the existing Media Folder filter dropdown.
     */
    public function add_assign_folder_bulk_action($actions)
    {
        $actions['assign_media_folder'] = 'Assign to selected folder';

        return $actions;
    }

    /**
     * ---------------------------------------------------------
     * Handle Bulk Action: Assign media to selected folder
     * ---------------------------------------------------------
     * Steps:
     * 1. Check if the selected bulk action is our custom action
     * 2. Read the selected folder from the existing dropdown
     * 3. Assign each selected attachment to that folder
     */
    public function handle_assign_folder_bulk_action($redirect, $action, $ids)
    {
        // Run only for our custom action
        if ($action !== 'assign_media_folder') {
            return $redirect;
        }

        /*
         * Validate that a folder was selected from the dropdown.
         * WordPress sends:
         *
         * $_REQUEST['taxonomy'] = 'media_folder'
         * $_REQUEST['term']     = folder slug
         */
        if (
            empty($_REQUEST['taxonomy']) ||
            $_REQUEST['taxonomy'] !== 'media_folder' ||
            empty($_REQUEST['term'])
        ) {
            return add_query_arg('folder_not_selected', 1, $redirect);
        }

        // Get folder term using the slug
        $term = get_term_by(
            'slug',
            sanitize_text_field($_REQUEST['term']),
            'media_folder'
        );

        if (!$term) {
            return $redirect;
        }

        // Assign each selected image to the folder
        foreach ($ids as $attachment_id) {

            wp_set_object_terms(
                $attachment_id,
                [$term->term_id],
                'media_folder',
                false
            );
        }

        // Redirect with success message
        return add_query_arg(
            'assigned_media_folder',
            count($ids),
            $redirect
        );
    }

    /**
     * ---------------------------------------------------------
     * Admin Notices
     * ---------------------------------------------------------
     * Displays a success or error message after bulk action.
     */
    public function bulk_assign_admin_notices()
    {
        // Success message
        if (!empty($_GET['assigned_media_folder'])) {

            $count = intval($_GET['assigned_media_folder']);

            echo '<div class="notice notice-success is-dismissible">';
            echo "<p>$count images assigned to the selected folder.</p>";
            echo '</div>';
        }

        // Error message if folder was not selected
        if (!empty($_GET['folder_not_selected'])) {

            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>Please select a media folder from the dropdown first.</p>';
            echo '</div>';
        }
    }
}

/**
 * Initialize Plugin
 */
new Media_Folder_Taxonomy();
