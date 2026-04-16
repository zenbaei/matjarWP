<?php
namespace Matjar\WpMedia;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Bulk
 *
 * Handles bulk actions for media folders.
 *
 * Responsibilities:
 * - Add custom bulk actions
 * - Handle folder deletion with media
 *
 * Layer:
 * - Application Layer
 *
 * @package Matjar\Media
 */
class Bulk {

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

        add_filter('bulk_actions-edit-media_folder', [$this, 'addAction']);
        add_filter('handle_bulk_actions-edit-media_folder', [$this, 'handle'], 10, 3);
    }

    /**
     * Add bulk action
     *
     * @param array $actions
     * @return array
     */
    public function addAction(array $actions): array {

        $actions['delete_folder_with_media'] = __('Delete Folder & Media', 'matjar');

        return $actions;
    }

    /**
     * Handle bulk action
     *
     * @param string $redirect
     * @param string $action
     * @param array  $ids
     * @return string
     */
    public function handle(string $redirect, string $action, array $ids): string {

        if ($action !== 'delete_folder_with_media') {
            return $redirect;
        }

        foreach ($ids as $id) {
            $this->service->deleteFolderWithMedia((int) $id);
        }

        return $redirect;
    }
}