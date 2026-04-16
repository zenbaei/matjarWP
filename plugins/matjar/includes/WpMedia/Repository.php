<?php
namespace Matjar\WpMedia;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Repository
 *
 * Handles data access for media folders.
 *
 * Responsibilities:
 * - Fetch attachments by folder
 * - Delete attachments
 *
 * Layer:
 * - Data Access Layer
 *
 * @package Matjar\Media
 */
class Repository {

    /**
     * Get all attachments in a media folder
     *
     * @param int $folderId
     * @return array
     */
    public function getAttachments(int $folderId): array {

        return get_posts([
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'tax_query'      => [[
                'taxonomy' => 'media_folder',
                'terms'    => $folderId,
            ]]
        ]);
    }

    /**
     * Delete all attachments inside a folder
     *
     * @param int $folderId
     * @return void
     */
    public function deleteAttachments(int $folderId): void {

        $attachments = $this->getAttachments($folderId);

        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
    }
}