/**
 
 */



jQuery(document).on('change', '#billing_area', function () {
    console.log('billing_area changed');
    jQuery(document.body).trigger('update_checkout');
});
