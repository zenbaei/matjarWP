jQuery(function ($) {

    const $folder = $('#media-folder-filter');

    $folder.selectWoo({
        width: '220px',
        placeholder: 'Search folder...',
        allowClear: true
    });

    $folder.css('visibility', 'visible');
});