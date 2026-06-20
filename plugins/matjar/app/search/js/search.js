jQuery(document).on('submit', '.etheme-search-form', function (e) {

    const input = jQuery(this).find('.search-input');

    if (!input.val().trim()) {
        e.preventDefault();
        input.focus();
        return false;
    }
});

jQuery(function ($) {

    $('.etheme-search-form-input')
        .prop('required', true)
        .on('invalid', function () {
            this.setCustomValidity('يرجى إدخال كلمة البحث');
        })
        .on('input', function () {
            this.setCustomValidity('');
        });

});