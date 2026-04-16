<?php
namespace Matjar\Product\Taxonomy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Person
 *
 * Responsible ONLY for registering the Person taxonomy.
 *
 * Responsibilities:
 * - Register taxonomy
 * - Enable REST support
 * 
 * Registers shared taxonomy for all people:
 * - Writers
 * - Editors
 * - (future roles)
 *
 * Notes:
 * - UI handled in Book/UI.php
 * - Saving handled in Book/Service.php
 *
 * @package Matjar
 */
class Person {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register']);
    }

    /**
     * Register Person taxonomy
     *
     * @return void
     */
    public function register(): void {

        register_taxonomy('person', 'product', [

            'labels' => [
                'name'          => __('Persons', 'matjar-book-plugin'),
                'singular_name' => __('Person', 'matjar-book-plugin'),
            ],

            'public'            => true,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,

            // 🔥 مهم عشان AJAX + REST
            'show_in_rest'      => true,
            'rest_base'         => 'person',

            // يظهر تحت منتجات
            'show_in_menu'      => 'edit.php?post_type=product',

            'rewrite' => [
                'slug' => 'person',
            ],
        ]);
    }
}