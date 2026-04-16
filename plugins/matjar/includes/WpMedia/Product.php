<?php
namespace Matjar\WpMedia;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Product
 *
 * Handles WooCommerce integration.
 *
 * Responsibilities:
 * - Save media folder for product
 * - Sync images after saving
 *
 * Layer:
 * - Integration Layer
 *
 * @package Matjar\Media
 */
class Product {

    /**
     * Service instance
     *
     * @var Service
     */
    private Service $service;

    /**
     * Constructor
     */
    public function __construct() {

        $this->service = new Service();

        add_action('woocommerce_process_product_meta', [$this, 'save']);
        add_action('woocommerce_after_product_object_save', [$this, 'sync'], 20);
    }

    /**
     * Save selected media folder
     *
     * @param int $postId
     * @return void
     */
    public function save(int $postId): void {

        if (isset($_POST['_media_folder_id'])) {
            update_post_meta($postId, '_media_folder_id', (int) $_POST['_media_folder_id']);
        }
    }

    /**
     * Sync images after product save
     *
     * @param \WC_Product $product
     * @return void
     */
    public function sync($product): void {

        $productId = $product->get_id();

        $folderId = isset($_POST['_media_folder_id'])
            ? (int) $_POST['_media_folder_id']
            : 0;

        if ($folderId > 0) {
            $this->service->syncProductImages($productId, $folderId);
        }
    }
}