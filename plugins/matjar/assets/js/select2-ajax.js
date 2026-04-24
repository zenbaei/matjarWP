/**
 * Select2 AJAX Engine
 *
 * Description:
 * Reusable SelectWoo (Select2) initializer for dynamic AJAX-powered fields.
 *
 * Features:
 * - Dynamic via data attributes (no hardcoding)
 * - Supports multiple fields (writers, editors, etc.)
 * - Built-in delay (debounce)
 * - Optional placeholder support
 * - Basic caching for performance
 * - Error-safe
 *
 * Required HTML attributes:
 * - data-action       → WordPress AJAX action
 * - data-nonce        → security nonce
 *
 * Optional:
 * - data-placeholder  → custom placeholder text
 *
 * Example:
 * <select
 *   class="book-select2"
 *   data-action="search_writers"
 *   data-nonce="xxxx"
 *   data-placeholder="Search writers..."
 *   multiple
 * ></select>
 */

jQuery(function ($) {

    /**
     * Simple in-memory cache
     * Prevents repeated AJAX calls for same query
     */
    const cache = {};

    // 🔥 expose cache reset globally
    window.resetSelect2Cache = function () {
        for (let key in cache) {
            delete cache[key];
        }
    };

    /**
     * Initialize all Select2 fields
     */
    $('.select2-ajax').each(function () {

        let $el = $(this);

        // Required config
        let action = $el.data('action');
        let nonce = $el.data('nonce');

        // Optional config
        let placeholder = $el.data('placeholder') || 'Search...';

        // Safety check
        if (!action || !nonce) {
            console.warn('Select2 missing required data attributes', $el);
            return;
        }

        $el.selectWoo({

            width: '100%',
            minimumInputLength: 1,
            placeholder: placeholder,
            allowClear: true,

            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250, // debounce

                /**
                 * Build request data
                 */
                data: function (params) {

                    let term = params.term || '';

                    return {
                        term: term,
                        nonce: nonce,
                        action: action
                    };
                },

                /**
                 * Handle response
                 */
                processResults: function (data, params) {

                    return {
                        results: data
                    };
                },

                /**
                 * Transport layer (with caching)
                 */
                transport: function (params, success, failure) {

                    let term = params.data.term;
                    let cacheKey = action + ':' + term;

                    // Return cached result if exists
                    if (cache[cacheKey]) {
                        success(cache[cacheKey]);
                        return;
                    }

                    let request = $.ajax(params);

                    request.then(function (data) {
                        cache[cacheKey] = data;
                        success(data);
                    });

                    request.fail(function () {
                        console.error('Select2 AJAX error:', params);
                        failure();
                    });

                    return request;
                }
            }

        });

    });

});
