/* DOKUWIKI:include_once simple-lightbox/simple-lightbox.js */
/* DOKUWIKI:include script/prosemirror.js */

jQuery(function () {
    /**
     * Add a quicklink to the media popup
     */
    (function() {
        const $opts = jQuery('#media__opts');
        if (!$opts.length) return;
        if (!window.opener) return; // we're not in the popup

        const glbl = document.createElement('label');
        const glnk = document.createElement('a');
        const gbrk = document.createElement('br');

        glnk.innerHTML = LANG.plugins.gallery.addgal;
        glnk.style.cursor = 'pointer';
        glnk.href = '#';

        glnk.onclick = function () {
            const $h1 = jQuery('#media__ns');
            if (!$h1.length) return;
            const ns = $h1[0].textContent;
            opener.insertAtCarret('wiki__text', '{{gallery>' + ns + '}}');
            if (!dw_mediamanager.keepopen) window.close();
        };

        $opts[0].appendChild(glbl);
        glbl.appendChild(glnk);
        $opts[0].appendChild(gbrk);
    })();

    /**
     * Display a selected page and hide all others
     */
    (function() {
        // hide all pages except the first one in each gallery
        jQuery('.plugin-gallery').each(function() {
            const $gallery = jQuery(this);
            $gallery.find('.gallery-page').hide().eq(0).show();
            $gallery.find('.gallery-page-selector a').eq(0).addClass('active');
        });
        // attach page selector
        jQuery('.gallery-page-selector a').click(function(e) {
            const $self = jQuery(this);
            $self.siblings().removeClass('active');
            $self.addClass('active');
            const $gallery = $self.closest('.plugin-gallery');
            $gallery.find('.gallery-page').hide();
            $gallery.find(e.target.hash).show();
            e.preventDefault();
        });
        // make page selector visible
        jQuery('.gallery-page-selector').show();
    })();

    /**
     * Initialize the lightbox
     */
    new SimpleLightbox("a.lightbox, a[rel^='lightbox']", {
        sourceAttr: 'data-url',
        captionSelector: 'self',
        captionType: 'data',
        captionsData: 'caption',
        captionPosition: 'outside',
        captionHTML: true, // we allow HTML and double escape in the formatter
        alertError: false,
        fileExt: false,
        uniqueImages: false
    });
});

