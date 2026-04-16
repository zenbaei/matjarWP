jQuery(function ($) {

    $('.book-select2').selectWoo({
        ajax: {
            url: book_ajax.url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term,
                    nonce: book_ajax.nonce,
                    action: 'search_writers'
                };
            },
            processResults: function (data) {
                return { results: data };
            }
        },
        minimumInputLength: 1,
        width: '100%'
    });

});