<?php
namespace Matjar\Product\Taxonomy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Taxonomies
 *
 * Registers Publisher taxonomy for WooCommerce products.
 *
 *
 *
 * @package Matjar
 */
class Publisher {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register']);
    }

    /**
     * Register taxonomies
     *
     * @return void
     */
    public function register(): void {

        $this->registerPublisher();
    }

    /**
     * Register Publisher taxonomy
     *
     * @return void
     */
    private function registerPublisher(): void {

        register_taxonomy('publisher', ['product'], [

            'labels' => [
                'name'          => __('Publishers', 'matjar-book-plugin'),
                'singular_name' => __('Publisher', 'matjar-book-plugin'),
            ],

            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,

            'show_in_rest'      => true,

            'rewrite' => [
                'slug' => 'publisher',
            ],
        ]);
    }
}