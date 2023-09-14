<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\gallery\classes\Options;
use dokuwiki\plugin\gallery\GalleryNode;
use dokuwiki\plugin\prosemirror\parser\RootNode;
use dokuwiki\plugin\prosemirror\schema\Node;

class action_plugin_gallery_prosemirror extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(EventHandler $controller)
    {
        // check if prosemirror is installed
        if (!class_exists('\dokuwiki\plugin\prosemirror\schema\Node')) return;

        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, 'writeDefaultsToJSINFO');
        $controller->register_hook('PROSEMIRROR_RENDER_PLUGIN', 'BEFORE', $this, 'renderFromInstructions');
        $controller->register_hook('PROSEMIRROR_PARSE_UNKNOWN', 'BEFORE', $this, 'parseToSyntax');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'renderAttributesToHTML');
    }

    /**
     * Render our syntax instructions for prosemirror
     *
     * Triggered by event: DOKUWIKI_STARTED
     *
     * @param Doku_Event $event event object
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function writeDefaultsToJSINFO(Event $event, $param)
    {
        global $JSINFO;

        $options = new Options();
        $defaults = [
            'thumbnailsize' => $options->thumbnailWidth . 'x' . $options->thumbnailHeight,
            'imagesize' => $options->lightboxWidth . 'X' . $options->lightboxHeight,
            'cache' => $options->cache,
            'filter' => $options->filter,
            'showname' => $options->showname,
            'showtitle' => $options->showtitle,
            'crop' => $options->crop,
            'direct' => $options->direct,
            'reverse' => $options->reverse,
            'recursive' => $options->recursive,
            'align' => $options->align,
            'cols' => $options->columns,
            'limit' => $options->limit,
            'offset' => $options->offset,
            'paginate' => $options->paginate,
            'sort' => $options->sort,
        ];

        if (!isset($JSINFO['plugins'])) {
            $JSINFO['plugins'] = [];
        }
        $JSINFO['plugins']['gallery'] = [
            'defaults' => array_map(function ($default) {
                return ['default' => $default,];
            }, $defaults),
        ];
        $JSINFO['plugins']['gallery']['defaults']['namespace'] = ['default' => ''];
    }


    /**
     * Render our syntax instructions for prosemirror
     *
     * Triggered by event: PROSEMIRROR_RENDER_PLUGIN
     *
     * @param Doku_Event $event event object
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function renderFromInstructions(Event $event, $param)
    {
        if ($event->data['name'] !== 'gallery') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        $node = new Node('dwplugin_gallery');
        // FIXME we may have to parse the namespace from the original syntax ?
        $data = $event->data['data'];
        $ns = $data['ns'];

        if (cleanID($ns) === $ns) {
            $ns = ':' . $ns;
        }
        $node->attr('namespace', $ns);
        foreach ($data as $name => $value) {
            $node->attr($name, $value);
        }
        $event->data['renderer']->nodestack->add($node);
    }

    /**
     * Render our syntax instructions for prosemirror
     *
     * Triggered by event: PROSEMIRROR_PARSE_UNKNOWN
     *
     * @param Doku_Event $event event object
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function parseToSyntax(Event $event, $param)
    {
        if ($event->data['node']['type'] !== 'dwplugin_gallery') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        $event->data['newNode'] = new GalleryNode($event->data['node'], $event->data['parent']);
    }

    /**
     * Render the nodes attributes to html so it can be displayed in the editor
     *
     * Triggered by event: AJAX_CALL_UNKNOQN
     *
     * @param Doku_Event $event event object
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function renderAttributesToHTML(Event $event, $param)
    {
        if ($event->data !== 'plugin_gallery_prosemirror') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT;
        $node = new GalleryNode(['attrs' => json_decode($INPUT->str('attrs'), true)], new RootNode([]));
        $syntax = $node->toSyntax();
        $html = p_render('xhtml', p_get_instructions($syntax), $info);
        echo $html;
    }
}
