/**
 * File: writer-select.js
 *
 * Handles:
 * - Initializing SelectWoo (Select2)
 * - AJAX search for writers taxonomy
 *
 * Requirements:
 * - WooCommerce (selectWoo)
 * - jQuery
 */

jQuery(function ($) {

    /**
     * Initialize AJAX Select2
     */
    $('.wc-book-writer-search').selectWoo({

        ajax: {
            url: wc_book_writer.ajax_url,
            dataType: 'json',
            delay: 250,

            /**
             * Send search term
             */
            data: function (params) {
                return {
                    action: 'search_writers',
                    term: params.term,
                    nonce: wc_book_writer.nonce
                };
            },

            /**
             * Format response for Select2
             */
            processResults: function (data) {
                return {
                    results: data
                };
            },

            cache: true
        },

        minimumInputLength: 1,
        width: '100%',

        /**
         * Placeholder from HTML attribute
         */
        placeholder: function () {
            return $(this).data('placeholder');
        }

    });

});