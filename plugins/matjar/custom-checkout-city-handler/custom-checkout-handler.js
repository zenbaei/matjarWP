jQuery(function ($) {

    function getCountry(selector) {
        return $(selector).val() || $('input[name="' + selector.replace('#', '') + '"]').val();
    }

    function isEgypt() {

        var useShipping = $('#ship-to-different-address-checkbox').is(':checked');

        var country = useShipping
            ? getCountry('#shipping_country')
            : getCountry('#billing_country');

        return country === 'EG';
    }

    /** 
     * Not renaming state to city anymore...
     * 
    function updateStateLabel() {
        return;
        if (isEgypt()) {
            $('label[for="billing_state"]').text('المدينة *');
            $('label[for="shipping_state"]').text('المدينة *');

        } else {

            $('label[for="billing_state"]').text('المنطقة *');
            $('label[for="shipping_state"]').text('المنطقة *');

        }
    }
        */

    function triggerCheckoutUpdateOnStateChange() {

        $('form.checkout').on('change', 'select[name="billing_state"], select[name="shipping_state"]', function () {
            $(document.body).trigger('update_checkout');
        });

        // Select2 support
        $(document).on('select2:select', '#billing_state, #shipping_state', function () {
            $(document.body).trigger('update_checkout');
        });
    }

    function toggleFieldsVisibility() {

        const fields = [
            'city',
            'postcode'
        ];

        fields.forEach(field => {
            toggleField(fields);
        });


        showAreaForEgyptOnly();

    }

    function toggleField(field) {
        if (isEgypt()) {
            $('#billing_${field}_field').hide();
            $('#shipping_${field}_field').hide();
        } else {
            $('#billing_${field}_field').show();
            $('#shipping_${field}_field').show();
        }
    }


    function showAreaForEgyptOnly() {
        // This field doesn't exist in Shipping, so we only toggle it in Billing
        isEgypt() ?
            $('#billing_area_field').show() : $('#billing_area_field').hide();
    }


    function init() {
        triggerCheckoutUpdateOnStateChange();
        toggleFieldsVisibility();
    }

    // Initial run
    init();

    // On country change
    $('form.checkout').on('change', '#billing_country, #shipping_country', function () {
        toggleFieldsVisibility();
    });

    // After Woo AJAX refresh
    $(document.body).on('updated_checkout', function () {
        toggleFieldsVisibility();
    });

});


/**
 * updated_checkout event is triggered after loading and after WooCommerce updates the checkout form via AJAX, which can happen when the user changes the country, state, or other fields that affect the available options. By listening to this event, we can ensure that our custom field ordering logic runs every time the checkout form is refreshed, keeping the fields in the desired order regardless of any changes made by WooCommerce.
 */
jQuery(document.body).on('updated_checkout', function () {
    reorderBillingFields();
    reorderShippingFields();
});

function reorderBillingFields() {
    const wrapper = document.querySelector('.woocommerce-billing-fields__field-wrapper');
    if (!wrapper) return;

    const order = [
        'billing_first_name_field',
        'billing_last_name_field',
        'billing_country_field',
        'billing_city_field',
        'billing_state_field',
        'billing_area_field',
        'billing_address_1_field',
        'billing_address_2_field',
        'billing_email_field',
        'billing_phone_field',
        'billing_postcode_field',
    ];

    order.forEach(id => {
        const el = document.getElementById(id);
        if (el) wrapper.appendChild(el);
    });
}

function reorderShippingFields() {
    const wrapper = document.querySelector('.woocommerce-shipping-fields__field-wrapper');
    if (!wrapper) return;

    const order = [
        'shipping_first_name_field',
        'shipping_last_name_field',
        'shipping_country_field',
        'shipping_city_field',
        'shipping_state_field',
        'shipping_area_field',
        'shipping_address_1_field',
        'shipping_address_2_field',
        'shipping_postcode_field',
    ];

    order.forEach(id => {
        const el = document.getElementById(id);
        if (el) wrapper.appendChild(el);
    });
}