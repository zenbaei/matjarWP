<?php

/**
 * Plugin Name: Matjar
 * Description: Modular WooCommerce extension for managing products, y, and media.
 * Version: 1.0.0
 * Author: Islam
 * Text Domain: matjar-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------
 * Constants
 * ---------------------------------------------------------
 *
 * Global plugin constants for paths and URLs.
 */
define('MATJAR_PATH', plugin_dir_path(__FILE__));
define('MATJAR_URL', plugin_dir_url(__FILE__));
define('MATJAR_VERSION', '1.0.0');


/**
 * ---------------------------------------------------------
 * Autoloader (PSR-4 style)
 * ---------------------------------------------------------
 *
 * Maps:
 * Matjar\X\Y → includes/X/Y.php
 */
spl_autoload_register(function ($class) {

    $prefix = 'Matjar\\';

    // Ignore classes outside our namespace
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    // Remove namespace prefix
    $relative = substr($class, strlen($prefix));

    // Convert namespace to path
    $path = str_replace('\\', '/', $relative);

    // Final file path
    $file = MATJAR_PATH . 'includes/' . $path . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});


/**
 * ---------------------------------------------------------
 * Imports (Modules)
 * ---------------------------------------------------------
 */

use Matjar\WpMedia\Module as MediaModule;
use Matjar\Product\Module as ProductModule;


/**
 * Class Matjar_Plugin
 *
 * Main plugin bootstrapper.
 *
 * Responsibilities:
 * - Ensure environment readiness (WooCommerce)
 * - Boot all application modules
 *
 * Architecture:
 * - Modular (domain-based)
 * - PSR-4 autoloaded
 * - Thin entry point (delegates to modules)
 */
class Matjar_Plugin
{

    /**
     * Constructor
     *
     * Hooks plugin initialization.
     */
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Initialize plugin
     *
     * @return void
     */
    public function init(): void
    {

        // Ensure WooCommerce is active
        if (!$this->isWooCommerceActive()) {
            return;
        }

        // Boot modules
        $this->bootModules();
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Boot all modules
     *
     * @return void
     */
    private function bootModules(): void
    {

        foreach ($this->getModules() as $module) {

            if (!class_exists($module)) {
                continue;
            }

            new $module();
        }
    }

    /**
     * Get registered modules
     *
     * Each module represents a domain entry point.
     *
     * @return array<class-string>
     */
    private function getModules(): array
    {

        return [

            /**
             * ---------------------------------
             * WordPress Media Domain
             * ---------------------------------
             */
            MediaModule::class,

            /**
             * ---------------------------------
             * Product Domain
             * ---------------------------------
             */
            ProductModule::class,
        ];
    }
}


/**
 * ---------------------------------------------------------
 * Plugin Entry Point
 * ---------------------------------------------------------
 *
 * Starts the plugin.
 */
function matjar_plugin(): void
{
    new Matjar_Plugin();
}

matjar_plugin();
