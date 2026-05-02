jQuery(function ($) {

    function getCountry() {
        let shipToDifferent = $('#ship-to-different-address-checkbox').is(':checked');

        if (shipToDifferent && $('#shipping_country').length) {
            return $('#shipping_country').val();
        }

        return $('#billing_country').val();
    }

    function toggleQuoteUI() {
        let country = getCountry();

        if (!country) return; // مهم جدًا

        if (country !== 'EG') {
            $('.order-total').hide();
        } else {
            $('.order-total').show();
        }
    }

    // ✅ أول تحميل بعد ما WooCommerce يجهز نفسه
    $(document.body).on('init_checkout', function () {
        toggleQuoteUI();
    });

    // ✅ أي تحديث (تغيير دولة / عنوان)
    $(document.body).on('updated_checkout', function () {
        toggleQuoteUI();
    });

    // fallback احتياطي
    $(window).on('load', function () {
        toggleQuoteUI();
    });


});