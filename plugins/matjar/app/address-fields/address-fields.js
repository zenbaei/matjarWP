/**
 * Customize WooCommerce address fields.
 *
 * Features:
 * - Hide city field for Egypt.
 * - Reorder country, city, and state fields.
 * - Keep field order synced after WooCommerce updates.
 */
jQuery(function ($) {

    /**
     * Toggle city field visibility.
     *
     * Hides the city field when the selected country is Egypt.
     */
    function toggleCityFields() {
        const billingCountry = $('#billing_country').val();
        const shippingCountry = $('#shipping_country').val();

        $('#billing_city_field').toggleClass(
            'woocommerce-hidden',
            billingCountry === 'EG'
        );

        $('#shipping_city_field').toggleClass(
            'woocommerce-hidden',
            shippingCountry === 'EG'
        );
    }

    /**
     * Reorder WooCommerce address fields.
     *
     * Field order:
     *
     * Egypt:
     * Country → State
     *
     * Other countries:
     * Country → City → State
     */
    /**
 * Reorder WooCommerce address fields.
 *
 * Field order:
 * Country → City → State → Address 1 → Address 2 → ZIP
 */
    /**
 * Reorder WooCommerce address fields.
 *
 * Field order:
 * Country → City → State → Address 1 → Address 2 → ZIP
 */
    function reorderFields() {

        /**
         * Billing fields.
         */
        const billingZipField = $('#billing_postcode_field');
        const billingAddress1Field = $('#billing_address_1_field');
        const billingAddress2Field = $('#billing_address_2_field');

        if (billingZipField.length && billingAddress1Field.length) {

            billingAddress1Field.insertBefore(billingZipField);

            if (billingAddress2Field.length) {
                billingAddress2Field.insertAfter(billingAddress1Field);
            }
        }

        /**
         * Shipping fields.
         */
        const shippingZipField = $('#shipping_postcode_field');
        const shippingAddress1Field = $('#shipping_address_1_field');
        const shippingAddress2Field = $('#shipping_address_2_field');

        if (shippingZipField.length && shippingAddress1Field.length) {

            shippingAddress1Field.insertBefore(shippingZipField);

            if (shippingAddress2Field.length) {
                shippingAddress2Field.insertAfter(shippingAddress1Field);
            }
        }
    }


    /**
     * Reorder after country/state updates.
     */
    $(document.body).on(
        'change updated_checkout updated_wc_div country_to_state_changed',
        function () {
            toggleCityFields();
            reorderFields();
        }
    );

    /**
     * Initialize on page load.
     */
    $(window).on('load', function () {
        toggleCityFields();
        reorderFields();
    });


});