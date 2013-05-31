/* DOKUWIKI:include_once jquery.magnific.js */

/**
 * Add a quicklink to the media popup
 */
function gallery_plugin(){
    var $opts = jQuery('#media__opts');
    if(!$opts.length) return;
    if(!window.opener) return;

    var glbl = document.createElement('label');
    var glnk = document.createElement('a');
    var gbrk = document.createElement('br');
    glnk.name         = 'gallery_plugin';
    glnk.innerHTML    = LANG.plugins.gallery.addgal; //FIXME localize
    glnk.style.cursor = 'pointer';

    glnk.onclick = function(){
        var $h1 = jQuery('#media__ns');
        if(!$h1.length) return;
        var ns = $h1[0].innerHTML;
        opener.insertAtCarret('wiki__text','{{gallery>'+ns+'}}');
        if(!dw_mediamanager.keepopen) window.close();
    };

    $opts[0].appendChild(glbl);
    glbl.appendChild(glnk);
    $opts[0].appendChild(gbrk);
}

/**
 * Display a selected page and hide all others
 */
function gallery_pageselect(e){
    var galid = e.target.hash.substr(10,4);
    var $pages = jQuery('div.gallery__'+galid);
    $pages.hide();
    jQuery('#'+e.target.hash.substr(1)).show();
    return false;
}

// === main ===
jQuery(function(){
    /**
     * Initialize the magnific popup lightbox
     */
    jQuery("a.lightbox, a[rel^='lightbox']").magnificPopup({
        type: 'image',
        image: {
            // we use our own title provider for proper escaping and longdesc support
            titleSrc: function(item){
                var $title = jQuery(document.createElement('div'));
                $title.text(item.el.attr('title'));
                var $desc = jQuery(document.createElement('small'));
                $desc.text(item.el.find('img').attr('longdesc'));
                $title.append($desc);
                return $title;
            }
        },
        gallery: {
            enabled: true
        }
    });

    gallery_plugin();

    // hide all pages except the first one
    var $pages = jQuery('div.gallery_page');
    $pages.hide();
    $pages.eq(0).show();

    // attach page selector
    jQuery('a.gallery_pgsel').click(gallery_pageselect);
});

