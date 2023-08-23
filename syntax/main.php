<?php

use dokuwiki\File\PageResolver;
use dokuwiki\plugin\gallery\classes\BasicFormatter;
use dokuwiki\plugin\gallery\classes\FeedGallery;
use dokuwiki\plugin\gallery\classes\ListGallery;
use dokuwiki\plugin\gallery\classes\NamespaceGallery;
use dokuwiki\plugin\gallery\classes\Options;
use dokuwiki\plugin\gallery\classes\XHTMLFormatter;

/**
 * Embed an image gallery
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Joe Lapp <joe.lapp@pobox.com>
 * @author     Dave Doyle <davedoyle.canadalawbook.ca>
 */
class syntax_plugin_gallery_main extends DokuWiki_Syntax_Plugin
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
        $this->Lexer->addSpecialPattern('\{\{gallery>[^}]*\}\}', $mode, 'plugin_gallery_main');
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
        [$src, $options] = $data;

        try {

            if (is_array($src)) {
                $gallery = new ListGallery($src, $options);
            } elseif (preg_match('/^https?:\/\//i', $src)) {
                $gallery = new FeedGallery($src, $options);
            } else {
                $gallery = new NamespaceGallery($src, $options);
            }

            $R->info['cache'] = $options->cache;
            switch ($mode) {
                case 'xhtml':
                    $formatter = new XHTMLFormatter($R, $options);
                    break;
                // FIXME additional specialized Formatters could be added here (PDF, ODT)
                default:
                    $formatter = new BasicFormatter($R, $options);
            }
            $formatter->render($gallery);
        } catch (Exception $e) {
            msg(hsc($e->getMessage()), -1);
            $R->cdata($this->getLang('fail'));
        }
        return true;
    }
}
