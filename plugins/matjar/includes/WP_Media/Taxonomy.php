<?php
namespace Matjar\WP_Media;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Taxonomy
 *
 * Registers the "media_folder" taxonomy for WordPress attachments.
 *
 * Responsibilities:
 * - Define taxonomy structure
 * - Attach taxonomy to media (attachments)
 *
 * Layer:
 * - Data Layer (Structure Definition)
 *
 * @package Matjar\Media
 */
class Taxonomy {

    /**
     * Constructor
     *
     * Hooks into WordPress init action.
     */
    public function __construct() {
        add_action('init', [$this, 'register']);
    }

    /**
     * Register media_folder taxonomy
     *
     * @return void
     */
    public function register(): void {

        register_taxonomy('media_folder', 'attachment', [
            'labels' => [
                'name'          => __('Media Folders', 'matjar'),
                'singular_name' => __('Media Folder', 'matjar'),
            ],
            'public'       => false,
            'hierarchical' => true,
            'show_ui'      => true,
            'show_in_rest' => true,
        ]);
    }
}