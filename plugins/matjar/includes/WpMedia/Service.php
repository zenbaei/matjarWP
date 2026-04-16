<?php
namespace Matjar\WpMedia;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Service
 *
 * Contains business logic for media folders.
 *
 * Responsibilities:
 * - Delete folders with media
 * - Sync WooCommerce product images
 *
 * Layer:
 * - Business Logic Layer
 *
 * @package Matjar\Media
 */
class Service {

    /**
     * Repository instance
     *
     * @var Repository
     */
    private Repository $repository;

    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new Repository();
    }

    /**
     * Delete folder and all its media
     *
     * @param int $folderId
     * @return void
     */
    public function deleteFolderWithMedia(int $folderId): void {

        $this->repository->deleteAttachments($folderId);

        wp_delete_term($folderId, 'media_folder');
    }

    /**
     * Sync WooCommerce product images with folder
     *
     * @param int $productId
     * @param int $folderId
     * @return void
     */
    public function syncProductImages(int $productId, int $folderId): void {

        $attachments = $this->repository->getAttachments($folderId);

        if (empty($attachments)) {
            return;
        }

        $ids = wp_list_pluck($attachments, 'ID');

        // Featured image
        set_post_thumbnail($productId, $ids[0]);

        // Gallery
        update_post_meta(
            $productId,
            '_product_image_gallery',
            implode(',', array_slice($ids, 1))
        );
    }
}