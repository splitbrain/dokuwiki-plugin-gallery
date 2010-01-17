/**
 * Script for the Gallery Plugin to add nifty inline image viewing.
 *
 * It's based upon lightbox plus by Takuya Otani which is based upon
 * lightbox by Lokesh Dhakar.
 *
 * For the DokuWiki plugin the following modifications were made:
 *
 * - addEvent removed (is shipped with DokuWiki)
 * - IDs were changed to avoid clashs
 * - previous and next buttons added
 * - keyboard support added
 * - neighbor preloading (not sure if it works)
 *
 * @license Creative Commons Attribution 2.5 License
 * @author  Takuya Otani <takuya.otani@gmail.com>
 * @link    http://serennz.cool.ne.jp/sb/sp/lightbox/
 * @author  Lokesh Dhakar <lokesh@huddletogether.com>
 * @link    http://www.huddletogether.com/projects/lightbox/
 * @author  Andreas Gohr <andi@splitbrain.org>
 */

/* Original copyright notices follow:

  lightbox_plus.js
  == written by Takuya Otani <takuya.otani@gmail.com> ===
  == Copyright (C) 2006 SimpleBoxes/SerendipityNZ Ltd. ==

    Copyright (C) 2006 Takuya Otani/SimpleBoxes - http://serennz.cool.ne.jp/sb/
    Copyright (C) 2006 SerendipityNZ - http://serennz.cool.ne.jp/snz/

    This script is licensed under the Creative Commons Attribution 2.5 License
    http://creativecommons.org/licenses/by/2.5/

    basically, do anything you want, just leave my name and link.

    Original script : Lightbox JS : Fullsize Image Overlays
    Copyright (C) 2005 Lokesh Dhakar - http://www.huddletogether.com
    For more information on this script, visit:
    http://huddletogether.com/projects/lightbox/
*/



/**
 * This variable will enable the lightbox mode for every normal image
 * embedded through the normal wiki image syntax and using the ?direct
 * parameter.
 *
 * If you don't like that behavior, set this variable to 0.
 */
var lightboxForEveryImg = 0;

function WindowSize()
{ // window size object
    this.w = 0;
    this.h = 0;
    return this.update();
}
WindowSize.prototype.update = function()
{
    var d = document;
    this.w =
      (window.innerWidth) ? window.innerWidth
    : (d.documentElement && d.documentElement.clientWidth) ? d.documentElement.clientWidth
    : d.body.clientWidth;
    this.h =
      (window.innerHeight) ? window.innerHeight
    : (d.documentElement && d.documentElement.clientHeight) ? d.documentElement.clientHeight
    : d.body.clientHeight;
    return this;
};
function PageSize()
{ // page size object
    this.win = new WindowSize();
    this.w = 0;
    this.h = 0;
    return this.update();
}
PageSize.prototype.update = function()
{
    var d = document;
    this.w =
      (window.innerWidth && window.scrollMaxX) ? window.innerWidth + window.scrollMaxX
    : (d.body.scrollWidth > d.body.offsetWidth) ? d.body.scrollWidth
    : d.body.offsetWidt;
    this.h =
      (window.innerHeight && window.scrollMaxY) ? window.innerHeight + window.scrollMaxY
    : (d.body.scrollHeight > d.body.offsetHeight) ? d.body.scrollHeight
    : d.body.offsetHeight;
    this.win.update();
    if (this.w < this.win.w) this.w = this.win.w;
    if (this.h < this.win.h) this.h = this.win.h;
    return this;
};
function PagePos()
{ // page position object
    this.x = 0;
    this.y = 0;
    return this.update();
}
PagePos.prototype.update = function()
{
    var d = document;
    this.x =
      (window.pageXOffset) ? window.pageXOffset
    : (d.documentElement && d.documentElement.scrollLeft) ? d.documentElement.scrollLeft
    : (d.body) ? d.body.scrollLeft
    : 0;
    this.y =
      (window.pageYOffset) ? window.pageYOffset
    : (d.documentElement && d.documentElement.scrollTop) ? d.documentElement.scrollTop
    : (d.body) ? d.body.scrollTop
    : 0;
    return this;
};
function UserAgent()
{ // user agent information
    var ua = navigator.userAgent;
    this.isWinIE = this.isMacIE = false;
    this.isGecko  = ua.match(/Gecko\//);
    this.isSafari = ua.match(/AppleWebKit/);
    this.isOpera  = window.opera;
    if (document.all && !this.isGecko && !this.isSafari && !this.isOpera) {
        this.isWinIE = ua.match(/Win/);
        this.isMacIE = ua.match(/Mac/);
        this.isNewIE = (ua.match(/MSIE 5\.5/) || ua.match(/MSIE 6\.0/));
    }
    return this;
}
// === lightbox ===
function LightBox(option)
{
    var self = this;
    self._imgs = new Array();
    self._wrap = null;
    self._box  = null;
    self._open = -1;
    self._page = new PageSize();
    self._pos  = new PagePos();
    self._ua   = new UserAgent();
    self._expandable = false;
    self._expanded = false;
    self._expand = option.expandimg;
    self._shrink = option.shrinkimg;
    return self._init(option);
}
LightBox.prototype = {
    _init : function(option)
    {
        var self = this;
        var d = document;
        if (!d.getElementsByTagName) return;
        var links = d.getElementsByTagName("a");
        for (var i=0;i<links.length;i++) {
            var anchor = links[i];
            var num = self._imgs.length;

            //check if this is a direct link to an image
            if ( (anchor.getAttribute("href") && anchor.getAttribute("rel") == "lightbox") ||
                 (lightboxForEveryImg && anchor.getAttribute("class") &&
                  anchor.getAttribute("class").match("media") &&
                  anchor.firstChild.nodeName.toLowerCase().match("img") &&
                  anchor.getAttribute("href") &&
                  anchor.getAttribute("href").match("lib/exe/fetch.php|_media/")) ){
                // okay add lightbox
            }else{
                continue;
            }


            // initialize item
            self._imgs[num] = {src:anchor.getAttribute("href"),w:-1,h:-1,title:'',caption:'',cls:anchor.className};
            if (anchor.getAttribute("title"))
                self._imgs[num].title = anchor.getAttribute("title");
            else if (anchor.firstChild && anchor.firstChild.getAttribute && anchor.firstChild.getAttribute("title"))
                self._imgs[num].title = anchor.firstChild.getAttribute("title");
            if (anchor.firstChild && anchor.firstChild.getAttribute &&
                anchor.firstChild.getAttribute("longdesc")) {
                self._imgs[num].caption = anchor.firstChild.getAttribute("longdesc");
            }
            anchor.onclick = self._genOpener(num); // set closure to onclick event
        }
        var body = d.getElementsByTagName("body")[0];
        self._wrap = self._createWrapOn(body,option.loadingimg);
        self._box  = self._createBoxOn(body,option);
        return self;
    },
    _genOpener : function(num)
    {
        var self = this;
        return function() {
            self._show(num);
            if(window.event) window.event.returnValue = false;
            return false;
        }
    },
    _createWrapOn : function(obj,imagePath)
    {
        var self = this;
        if (!obj) return null;
        // create wrapper object, translucent background
        var wrap = document.createElement('div');
        wrap.id = 'gallery__overlay';
        with (wrap.style) {
            display = 'none';
            position = 'fixed';
            top = '0px';
            left = '0px';
            zIndex = '50';
            width = '100%';
            height = '100%';
        }
        if (self._ua.isWinIE) wrap.style.position = 'absolute';
        addEvent(wrap,"click",function() { self._close(); });
        obj.appendChild(wrap);
        // create loading image, animated image
        var imag = new Image;
        imag.onload = function() {
            var spin = document.createElement('img');
            spin.id = 'gallery__loadingImage';
            spin.src = imag.src;
            spin.style.position = 'relative';
            self._set_cursor(spin);
            addEvent(spin,'click',function() { self._close(); });
            wrap.appendChild(spin);
            imag.onload = function(){};
        };
        if (imagePath != '') imag.src = imagePath;
        return wrap;
    },
    _createBoxOn : function(obj,option)
    {
        var self = this;
        if (!obj) return null;
        // create lightbox object, frame rectangle
        var box = document.createElement('div');
        box.id = 'gallery__lightbox';
        with (box.style) {
            display = 'none';
            position = 'absolute';
            zIndex = '60';
        }
        obj.appendChild(box);
        // create image object to display a target image
        var img = document.createElement('img');
        img.id = 'gallery__lightboxImage';
        self._set_cursor(img);
        addEvent(img,'click',function(){ self._close(); });
        addEvent(img,'mouseover',function(){ self._show_action(); });
        addEvent(img,'mouseout',function(){ self._hide_action(); });
        box.appendChild(img);
        var zoom = document.createElement('img');
        zoom.id = 'gallery__actionImage';
        with (zoom.style) {
            display = 'none';
            position = 'absolute';
            top = '15px';
            left = '15px';
            zIndex = '70';
        }
        self._set_cursor(zoom);
        zoom.src = self._expand;
        addEvent(zoom,'mouseover',function(){ self._show_action(); });
        addEvent(zoom,'click', function() { self._zoom(); });
        box.appendChild(zoom);
        addEvent(window,'resize',function(){ self._set_size(true); });
        // close button
        if (option.closeimg) {
            var btn = document.createElement('img');
            btn.id = 'gallery__closeButton';
            with (btn.style) {
                display = 'inline';
                position = 'absolute';
                right = '10px';
                top = '10px';
                zIndex = '80';
            }
            btn.src = option.closeimg;
            self._set_cursor(btn);
            addEvent(btn,'click',function(){ self._close(); });
            box.appendChild(btn);
        }
        // next button
        if (option.nextimg) {
            var btn = document.createElement('img');
            btn.id = 'gallery__nextButton';
            with (btn.style) {
                display = 'inline';
                position = 'absolute';
                right = '10px';
                bottom = '10px';
                zIndex = '80';
            }
            btn.src = option.nextimg;
            self._set_cursor(btn);
            addEvent(btn,'click',function(){ self._move(+1) });
            box.appendChild(btn);
        }
        // prev button
        if (option.previmg) {
            var btn = document.createElement('img');
            btn.id = 'gallery__prevButton';
            with (btn.style) {
                display = 'inline';
                position = 'absolute';
                left = '10px';
                bottom = '10px';
                zIndex = '80';
            }
            btn.src = option.previmg;
            self._set_cursor(btn);
            addEvent(btn,'click',function(){ self._move(-1) });
            box.appendChild(btn);
        }
        // caption text
        var caption = document.createElement('span');
        caption.id = 'gallery__lightboxCaption';
        with (caption.style) {
            display = 'none';
            position = 'absolute';
            zIndex = '80';
        }
        box.appendChild(caption);
        return box;
    },
    _set_photo_size : function()
    {
        var self = this;
        if (self._open == -1) return;
        var imag = self._box.firstChild;
        var targ = { w:self._page.win.w - 30, h:self._page.win.h - 40 };
        var orig = { w:self._imgs[self._open].w, h:self._imgs[self._open].h };

        // shrink image with the same aspect
        var ratio = 1.0;
        if ((orig.w >= targ.w || orig.h >= targ.h) && orig.h && orig.w)
            ratio = ((targ.w / orig.w) < (targ.h / orig.h)) ? targ.w / orig.w : targ.h / orig.h;
        imag.width  = Math.floor(orig.w * ratio);
        imag.height = Math.floor(orig.h * ratio);
        self._expandable = (ratio < 1.0) ? true : false;
        if (self._ua.isWinIE) self._box.style.display = "block";
        self._box.style.top  = [self._pos.y + (self._page.win.h - imag.height - 33) / 2,'px'].join('');
        self._box.style.left = [((self._page.win.w - imag.width - 30) / 2),'px'].join('');
        self._show_caption(true);
    },
    _set_size : function(onResize)
    {
        var self = this;
        if (self._open == -1) return;
        self._page.update();
        self._pos.update();
        var spin = self._wrap.firstChild;
        if (spin) {
            var top = (self._page.win.h - spin.height) / 2;
            if (self._wrap.style.position == 'absolute') top += self._pos.y;
            spin.style.top  = [top,'px'].join('');
            spin.style.left = [(self._page.win.w - spin.width - 30) / 2,'px'].join('');
        }
        if (self._ua.isWinIE) {
            self._wrap.style.width  = [self._page.win.w,'px'].join('');
            self._wrap.style.height = [self._page.h,'px'].join('');
        }
        if (onResize) self._set_photo_size();
    },
    _show_action : function()
    {
        var self = this;
        if (self._open == -1 || !self._expandable) return;
        var obj = document.getElementById('gallery__actionImage');
        if (!obj) return;
        obj.src = (self._expanded) ? self._shrink : self._expand;
        obj.style.display = 'inline';
    },
    _hide_action : function()
    {
        var self = this;
        var obj = document.getElementById('gallery__actionImage');
        if (obj) obj.style.display = 'none';
    },
    _zoom : function()
    {
        var self = this;
        if (self._expanded) {
            self._set_photo_size();
            self._expanded = false;
        } else if (self._open > -1) {
            var imag = self._box.firstChild;
            self._box.style.top  = [self._pos.y,'px'].join('');
            self._box.style.left = '0px';
            imag.width  = self._imgs[self._open].w;
            imag.height = self._imgs[self._open].h;
            self._show_caption(false);
            self._expanded = true;
        }
        self._show_action();
    },
    _show_caption : function(enable)
    {
        var self = this;
        var caption = document.getElementById('gallery__lightboxCaption');
        if (!caption) return;
        if (caption.innerHTML.length == 0 || !enable) {
            caption.style.display = 'none';
        } else { // now display caption
            var imag = self._box.firstChild;
            with (caption.style) {
                top = [imag.height + 10,'px'].join(''); // 10 is top margin of lightbox
                left = '0px';
                width = [imag.width + 20,'px'].join(''); // 20 is total side margin of lightbox
                height = '';
                paddingBottom = '3px';
                display = 'block';
            }
        }
    },
    _move : function(by)
    {
        var self = this;
        var num  = self._open + by;
        // wrap around at start and end
        if(num < 0) num = self._imgs.length - 1;
        if(num >= self._imgs.length) num = 0;

        self._disable_keyboard();
        self._hide_action();
        self._box.style.display  = "none";
        self._show(num);
    },
    _show : function(num)
    {
        var self = this;
        var imag = new Image;
        if (num < 0 || num >= self._imgs.length) return;
        var loading = document.getElementById('gallery__loadingImage');
        var caption = document.getElementById('gallery__lightboxCaption');
        self._open = num; // set opened image number
        self._set_size(false); // calc and set wrapper size
        self._wrap.style.display = "block";
        if (loading) loading.style.display = 'inline';
        imag.onload = function() {
            if (self._imgs[self._open].w == -1) {
                // store original image width and height
                self._imgs[self._open].w = imag.width;
                self._imgs[self._open].h = imag.height;
            }
            if (caption) caption.innerHTML = '<b>'+self.hsc(self._imgs[self._open].title) + '</b><br />' +
                                             self.hsc(self._imgs[self._open].caption);
            self._set_photo_size(); // calc and set lightbox size
            self._hide_action();
            self._box.style.display = "block";
            self._box.firstChild.src = imag.src;
            self._box.firstChild.setAttribute('title',self._imgs[self._open].title);
            if (loading) loading.style.display = 'none';
        };
        self._expandable = false;
        self._expanded = false;
        self._enable_keyboard();
        imag.src = self._imgs[self._open].src;
        self._preload_neighbors(num);
    },
    _preload_neighbors: function(num){
        var self = this;

        if((self._imgs.length - 1) > num){
            var preloadNextImage = new Image();
            preloadNextImage.src = self._imgs[num + 1].src;
        }
        if(num > 0){
            var preloadPrevImage = new Image();
            preloadPrevImage.src = self._imgs[num - 1].src;
        }
    },
    _set_cursor : function(obj)
    {
        var self = this;
        if (self._ua.isWinIE && !self._ua.isNewIE) return;
        obj.style.cursor = 'pointer';
    },
    _close : function()
    {
        var self = this;
        self._open = -1;
        self._disable_keyboard();
        self._hide_action();
        self._wrap.style.display = "none";
        self._box.style.display  = "none";
    },
    _enable_keyboard: function()
    {
        //globally store refernce to current lightbox object:
        __lightbox = this;
        addEvent(document,'keydown',this._keyboard_action);
    },
    _disable_keyboard: function()
    {
        //remove global pointer:
        delete __lightbox;
        removeEvent(document,'keydown',this._keyboard_action);
    },
    _keyboard_action: function(e) {
        var self = __lightbox;
        var keycode = 0;

        if(e.which){ // mozilla
            keycode = e.which;
        }else{ // IE
            keycode = event.keyCode;
        }

        var key = String.fromCharCode(keycode).toLowerCase();
        if((key == 'x') || (key == 'c') || (keycode == 27)){   // close lightbox
            self._close();
        } else if( (key == 'p') || (keycode == 37) ){  // display previous image
            self._move(-1);
        } else if(key == 'n' || (keycode == 39) ){  // display next image
            self._move(+1);
        }
    },

    hsc: function(str) {
        str = str.replace(/&/g,"&amp;");
        str = str.replace(/\"/g,"&quot;");
        str = str.replace(/\'/g,"&#039;");
        str = str.replace(/</g,"&lt;");
        str = str.replace(/>/g,"&gt;");
        return str;
    }

};

/**
 * Add a quicklink to the media popup
 */
function gallery_plugin(){
    var opts = $('media__opts');
    if(!opts) return;
    if(!window.opener) return;

    var glbl = document.createElement('label');
    var glnk = document.createElement('a');
    var gbrk = document.createElement('br');
    glnk.name         = 'gallery_plugin';
    glnk.innerHTML    = 'Add namespace as gallery';
    glnk.style.cursor = 'pointer';

    glnk.onclick = function(){
        var h1 = $('media__ns');
        if(!h1) return;
        var ns = h1.innerHTML;
        opener.insertAtCarret('wiki__text','{{gallery>'+ns+'}}');
        if(!media.keepopen) window.close();
    };

    opts.appendChild(glbl);
    glbl.appendChild(glnk);
    opts.appendChild(gbrk);
}

/**
 * Display a selected page and hide all others
 */
function gallery_pageselect(e){
    var galid = e.target.hash.substr(10,4);

    var pages = getElementsByClass('gallery__'+galid,document,'div');
    for(var i=0; i<pages.length; i++){
        if(pages[i].id == e.target.hash.substr(1)){
            pages[i].style.display = '';
        }else{
            pages[i].style.display = 'none';
        }
    }
    return false;
}

// === main ===
addInitEvent(function() {
    var lightbox = new LightBox({
        loadingimg:DOKU_BASE+'lib/plugins/gallery/images/loading.gif',
        expandimg:DOKU_BASE+'lib/plugins/gallery/images/expand.gif',
        shrinkimg:DOKU_BASE+'lib/plugins/gallery/images/shrink.gif',
        closeimg:DOKU_BASE+'lib/plugins/gallery/images/close.gif',
        nextimg:DOKU_BASE+'lib/plugins/gallery/images/next.gif',
        previmg:DOKU_BASE+'lib/plugins/gallery/images/prev.gif'
    });
    gallery_plugin();

    // hide all pages except the first one
    var pages = getElementsByClass('gallery_page',document,'div');
    for(var i=0; i<pages.length; i++){
        if(!pages[i].id.match(/_1/)){
            pages[i].style.display = 'none';
        }
    }

    // attach page selector
    var pgsel = getElementsByClass('gallery_pgsel',document,'a');
    for(var i=0; i<pgsel.length; i++){
        addEvent(pgsel[i],'click',gallery_pageselect);
    }

});
