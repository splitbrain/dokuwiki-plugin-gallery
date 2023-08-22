<?php

use dokuwiki\File\PageResolver;
use dokuwiki\plugin\gallery\classes\FeedGallery;
use dokuwiki\plugin\gallery\classes\Formatter;
use dokuwiki\plugin\gallery\classes\NamespaceGallery;
use dokuwiki\plugin\gallery\classes\Options;

/**
 * Embed an image gallery
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Joe Lapp <joe.lapp@pobox.com>
 * @author     Dave Doyle <davedoyle.canadalawbook.ca>
 */
class syntax_plugin_gallery extends DokuWiki_Syntax_Plugin
{

    /** @inheritdoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritdoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 301;
    }

    /** @inheritdoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('\{\{gallery>[^}]*\}\}', $mode, 'plugin_gallery');
    }

    /** @inheritdoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $ID;
        $match = substr($match, 10, -2); //strip markup from start and end

        $options = new Options();

        // unique gallery ID
        $options->galleryID = substr(md5($match), 0, 4);

        // alignment
        if (substr($match, 0, 1) == ' ') $options->align += Options::ALIGN_RIGHT;
        if (substr($match, -1, 1) == ' ') $options->align += Options::ALIGN_LEFT;

        // extract src and params
        list($src, $params) = sexplode('?', $match, 2);
        $src = trim($src);

        // resolve relative namespace
        if (!preg_match('/^https?:\/\//i', $src)) {
            $pageResolver = new PageResolver($ID);
            $src = $pageResolver->resolveId($src);
        }

        // parse parameters
        $options->parseParameters($params);

        return [
            $src, $options
        ];
    }

    /** @inheritdoc */
    public function render($mode, Doku_Renderer $R, $data)
    {
        global $ID;

        [$src, $options] = $data;

        if (preg_match('/^https?:\/\//i', $src)) {
            $gallery = new FeedGallery($src, $options);
        } else {
            $gallery = new NamespaceGallery($src, $options);
        }


        if ($mode == 'xhtml') {
            $R->info['cache'] = $options->cache;
            $formatter = new Formatter($options);
            $R->doc .= $formatter->format($gallery);

            // FIXME next steps:
            // * fix pagination CSS and JS
            // * implement minimal standard renderer for all renderers (just inline thumbnails with links)
            // * maybe implement PDF renderer separately from XHTML
            // * adjust lightbox script
            // * add more unit tests

            return true;
        } elseif ($mode == 'metadata') {
            // render the first image of the gallery, to ensure it will be used as first image if needed
            $images = $gallery->getImages();
            if (count($images)) {
                if ($images[0]->isExternal()) {
                    $R->externalmedia($images[0]->getSrc());
                } else {
                    $R->internalmedia($images[0]->getSrc());
                }
            }
            return true;
        }
        return false;
    }
}
