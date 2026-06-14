jQuery(function ($) {

    function stripCurrency() {
        $('.price_label .from, .price_label .to').each(function () {
            $(this).text($(this).text().replace(/\s*(ج\.م|م\.ج)/g, ''));
        });
    }

    stripCurrency();

    $(document.body).on('price_slider_updated', stripCurrency);
});