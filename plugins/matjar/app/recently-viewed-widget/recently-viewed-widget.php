<?php

/**
 * Plugin Name: Matjar - Recently Viewed Fix
 * Description: Hides "Recently Viewed" section when no products exist.
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Matjar_Recently_Viewed_Fix')) {

    class Matjar_Recently_Viewed_Fix
    {

        /**
         * Constructor
         */
        public function __construct()
        {
            add_action('wp_footer', [$this, 'add_inline_javascript']);
        }

        /**
         * Add inline JS to footer
         */
        public function add_inline_javascript()
        {
?>
            <script>
                (function($) {
                    $(document).ready(function() {

                        // Ensure widget exists
                        var $recent = $('.recently-viewed');

                        if ($recent.length && $recent.find('img').length === 0) {
                            $recent.closest('section').hide();
                        }

                    });
                })(jQuery);
            </script>
<?php
        }
    }

    // Initialize
    new Matjar_Recently_Viewed_Fix();
}
