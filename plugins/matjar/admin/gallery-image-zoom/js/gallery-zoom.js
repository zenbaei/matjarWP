(function ($) {
    $(function () {

        console.log('GALLERY ZOOM READY');

        function openImagePopup(src) {

            if (!src) return;

            src = src.replace(/-\d+x\d+(?=\.)/, '');

            let img = new Image();

            img.onload = function () {

                let width = Math.floor(screen.availWidth * 0.5);
                let height = Math.floor(screen.availHeight * 0.8);

                let left = (screen.availWidth - width) / 2;
                let top = (screen.availHeight - height) / 2;


                let features =
                    ["width=" + width,
                    "height=" + height,
                    "left=" + left,
                    "top=" + top,
                        "resizable=yes"].join(",");

                let win = window.open('', '_blank', features);

                if (!win) {
                    alert('Popup blocked');
                    return;
                }

                let html = [
                    '<html>',
                    '<head>',
                    '<title>Image Preview</title>',
                    '<style>',
                    'body{margin:0;display:flex;align-items:center;justify-content:center;background:#000}',
                    'img{max-width:100%;max-height:100%;cursor:zoom-in;transition:transform .2s ease}',
                    '</style>',
                    '</head>',
                    '<body>',
                    '<img id="zoomImg" src="' + src + '">',
                    '<script>',
                    'let img=document.getElementById("zoomImg");',
                    'let scale=1;',
                    'img.addEventListener("wheel",function(e){',
                    'e.preventDefault();',
                    'scale+=e.deltaY*-0.001;',
                    'scale=Math.min(Math.max(1,scale),4);',
                    'img.style.transform="scale("+scale+")";',
                    '});',
                    '<\/script>',
                    '</body>',
                    '</html>'
                ].join('');

                win.document.write(html);
                win.document.close();
            };

            img.src = src;
        }


        function addFeaturedButton() {

            let box = $('#postimagediv');

            if (!box.length) return;
            if (box.find('.wc-zoom-featured').length) return;

            let btn = $('<button class="button wc-zoom-featured">🔍 View Image</button>')
                .css({
                    marginTop: '10px',
                    width: '100%'
                });

            btn.on('click', function (e) {
                e.preventDefault();

                let img = box.find('img');
                if (!img.length) return;

                openImagePopup(img.attr('src'));
            });

            box.find('.inside').append(btn);
        }


        function addGalleryButtons() {

            let container = $('#product_images_container');

            if (!container.length) return;

            container.find('li.image').each(function () {

                let li = $(this);

                if (li.find('.wc-zoom-gallery').length) return;

                let btn = $('<button class="button wc-zoom-gallery">🔍</button>')
                    .css({
                        position: 'absolute',
                        top: '5px',
                        right: '5px',
                        zIndex: 10,
                        padding: '2px 6px',
                        opacity: 0,
                        transition: 'opacity 0.2s ease'
                    });

                li.css('position', 'relative');

                li.on('mouseenter', function () {
                    btn.css('opacity', 1);
                });

                li.on('mouseleave', function () {
                    btn.css('opacity', 0);
                });

                btn.on('click', function (e) {

                    e.preventDefault();
                    e.stopPropagation();

                    let id = li.data('attachment_id');
                    if (!id) return;

                    fetch('/wp-json/wp/v2/media/' + id)
                        .then(res => res.json())
                        .then(data => {
                            openImagePopup(data.source_url);
                        });
                });

                li.append(btn);
            });
        }


        let tries = 0;

        let interval = setInterval(function () {

            addFeaturedButton();
            addGalleryButtons();

            tries++;

            if (tries > 10) {
                clearInterval(interval);
            }

        }, 500);

    });
})(jQuery);