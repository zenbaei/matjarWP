<?php

/**
 * Plugin Name: Matjar - Media Folders
 */

if (!defined('ABSPATH')) {
    exit;
}

class Media_Folder_CRUD
{

    const TAXONOMY = 'media_folder';

    public function __construct()
    {
        /**
         * Bulk Delete Folder + Media
         */
        add_filter('bulk_actions-edit-media_folder', [$this, 'add_delete_folder_bulk_action']);
        add_filter('handle_bulk_actions-edit-media_folder', [$this, 'handle_delete_folder_bulk_action'], 10, 3);

        /**
         * Delete Folder Hooks
         */
        add_action('pre_delete_term', [$this, 'delete_folder_attachments_before_term_delete'], 10, 2);
    
    //    add_action('delete_media_folder', [$this, 'delete_folder_attachments'], 10, 3);

        /**
         * Product Delete
         */
        add_action(
            'before_delete_post',
            [$this, 'delete_media_folder_and_images_on_product_delete']
        );


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
         * Notices
         */
        add_action('admin_notices', [$this, 'delete_folder_notice']);
    }

    /**
     * Add custom bulk action to delete folder with media
     */
    public function add_delete_folder_bulk_action($actions)
    {
        $actions['delete_folder_with_media'] = 'Delete folder';
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

        error_log("delete_folder_attachments_before_term_delete called");


        $term_id = is_object($term_id) ? $term_id->term_id : $term_id;

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
     * When a media folder is deleted,
     * also delete all attachments inside it
     *
    public function delete_folder_attachments($term_id, $tt_id, $taxonomy)
    {
        error_log(
            "delete_folder_attachments called"
        );
        if ($taxonomy !== self::TAXONOMY) {
            return;
        }

        $term_id = is_object($term_id) ? $term_id->term_id : $term_id;

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
     */

    // Delete media folder and its attachments when product is removed from trash
    public function delete_media_folder_and_images_on_product_delete($post_id)
    {

        // Run only for WooCommerce products
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        error_log("delete_media_folder_and_images_on_product_delete called");


        // Get folder id from metadata
        $folder_id = get_post_meta($post_id, '_media_folder_id', true);

        if (!$folder_id) {
            return;
        }

        // Delete the taxonomy term
        wp_delete_term($folder_id, self::TAXONOMY);
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
                    'taxonomy' => self::TAXONOMY,
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
}

new Media_Folder_CRUD();
