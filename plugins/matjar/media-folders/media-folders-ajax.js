jQuery(document).ready(function($) {

    $('#media-folder-filter').on('change', function() {

        let term = $(this).val();

        let url = new URL(window.location.href);

        if (term) {
            url.searchParams.set('taxonomy', 'media_folder');
            url.searchParams.set('term', term);
        } else {
            url.searchParams.delete('taxonomy');
            url.searchParams.delete('term');
        }

        // Ajax load
        $.get(url.toString(), function(response) {

            let newTable = $(response).find('.wp-list-table tbody').html();

            $('.wp-list-table tbody').html(newTable);

            // Update URL without reload
            window.history.pushState({}, '', url.toString());
        });

    });

});
