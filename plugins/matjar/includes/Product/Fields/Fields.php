<?php

namespace Matjar\Product\Fields;

if (!defined('ABSPATH')) exit;

/**
 * Class Fields
 *
 * Module entry point for Book Fields.
 *
 * Responsibilities:
 * - Register hooks
 * - Connect UI with Service layer
 *
 * Architecture Role:
 * - Orchestrator (no logic, no HTML)
 */
class Fields
{

    private UI $ui;
    private Service $service;

    public function __construct()
    {

        $this->ui = new UI();
        $this->service = new Service();

        // UI
        add_filter('woocommerce_product_data_tabs', [$this->ui, 'registerTab']);
        add_action('woocommerce_product_data_panels', [$this->ui, 'renderPanel']);

        // Save
        add_action('woocommerce_process_product_meta', [$this->service, 'save']);

        // Scripts
        add_action('admin_enqueue_scripts', [$this->ui, 'enqueue']);

        // AJAX
        add_action('wp_ajax_search_persons', [$this->service, 'searchPersons']);

        // AJAX
        add_action('wp_ajax_search_publishers', [$this->service, 'searchPublishers']);
    }
}
