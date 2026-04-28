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
            $('.international-quote-row').show();
            $('.order-total').hide();
            $('.woocommerce-shipping-totals.shipping').hide();
            $('#payment').hide();
        } else {
            $('.international-quote-row').hide();
            $('.woocommerce-shipping-totals.shipping').show();
            $('.order-total').show();
            $('#payment').show();
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


    $(document).on('click', '#get-international-quote', function () {

        let $btn = $(this);
        let email = $('#billing_email').val();

        if (!email) {
            alert('من فضلك أدخل البريد الإلكتروني أولاً');
            $('#billing_email').focus();
            return;
        }

        // 🔥 منع الضغط المتكرر
        $btn.prop('disabled', true);

        $('#quote-result').html('جاري إرسال الطلب...');

        $.post(intlShippingHandler.ajax_url, {
            action: 'get_shipping_quote',
            email: email,
            nonce: intlShippingHandler.nonce
        }, function (response) {

            if (response.success) {
                $('#quote-result').html(response.data.message);
            } else {
                $('#quote-result').html(response.data || 'حدث خطأ');
                $btn.prop('disabled', false); // رجّع الزر لو فشل
            }

        });

    });

});