jQuery(function ($) {
    // Calculate weight based on book series input
    $('input[name="_book_series"]').on('input change', function () {
        let seriesValue = parseFloat($(this).val());
        console.log('Book series value:', seriesValue); // Debugging log
        if (!isNaN(seriesValue)) {
            $('input[name="_weight"]').val(seriesValue * 0.5).trigger('change');
        }
    }).trigger('change'); // runs once after page load
});