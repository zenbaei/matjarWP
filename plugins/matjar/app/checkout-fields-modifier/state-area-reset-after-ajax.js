/**
 * Persist originally saved checkout values
 * so they can be restored after AJAX reloads.
 *
 * This fixes the issue where:
 * - user changes city/state
 * - dynamic dropdowns reload
 * - returning to original city no longer restores state/area
 *
 * Works with WooCommerce checkout fields.
 */

jQuery(function ($) {

    /**
     * Store current values as persistent data attributes.
     *
     * We do this ONCE on initial page load,
     * before any dynamic resets happen.
     */
    function persistSavedCheckoutValues() {

        // Billing state
        const billingState = $('#billing_state').val();

        if (billingState) {
            $('#billing_state')
                .attr('data-saved-state', billingState);
        }

        // Billing area
        const billingArea = $('#billing_area').val();

        if (billingArea) {
            $('#billing_area')
                .attr('data-saved-area', billingArea);
        }

        // Shipping state
        const shippingState = $('#shipping_state').val();

        if (shippingState) {
            $('#shipping_state')
                .attr('data-saved-state', shippingState);
        }

        // Shipping area
        const shippingArea = $('#shipping_area').val();

        if (shippingArea) {
            $('#shipping_area')
                .attr('data-saved-area', shippingArea);
        }
    }

    /**
     * Restore a select value ONLY if:
     * - a saved value exists
     * - the option exists in the dropdown
     *
     * Prevents invalid assignments after AJAX reloads.
     */
    function restoreSelectValue($select, dataKey) {

        const savedValue = $select.attr(dataKey);

        if (!savedValue) {
            return;
        }

        // Check if option exists
        const optionExists =
            $select.find('option[value="' + savedValue + '"]').length > 0;

        if (!optionExists) {
            return;
        }

        // Restore value
        $select.val(savedValue).trigger('change');
    }

    /**
     * Initial persistence.
     */
    persistSavedCheckoutValues();

    /**
     * Example usage after your AJAX state reload completes.
     *
     * Call this AFTER rebuilding state options.
     */
    function restoreBillingState() {

        restoreSelectValue(
            $('#billing_state'),
            'data-saved-state'
        );
    }

    /**
     * Example usage after your AJAX area reload completes.
     *
     * Call this AFTER rebuilding area options.
     */
    function restoreBillingArea() {

        restoreSelectValue(
            $('#billing_area'),
            'data-saved-area'
        );
    }

    /**
     * Shipping equivalents.
     */
    function restoreShippingState() {

        restoreSelectValue(
            $('#shipping_state'),
            'data-saved-state'
        );
    }

    function restoreShippingArea() {

        restoreSelectValue(
            $('#shipping_area'),
            'data-saved-area'
        );
    }

    /**
     * Expose helpers globally if needed.
     *
     * Useful when your AJAX callbacks
     * live elsewhere in the codebase.
     */
    window.restoreBillingState = restoreBillingState;
    window.restoreBillingArea = restoreBillingArea;
    window.restoreShippingState = restoreShippingState;
    window.restoreShippingArea = restoreShippingArea;

});