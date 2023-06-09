<?php

use dokuwiki\plugin\gallery\GalleryNode;
use dokuwiki\plugin\prosemirror\parser\RootNode;
use dokuwiki\plugin\prosemirror\schema\Node;

class action_plugin_gallery_prosemirror extends DokuWiki_Action_Plugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
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
    public function writeDefaultsToJSINFO(Doku_Event $event, $param)
    {
        global $JSINFO;

        /** @var syntax_plugin_gallery $syntax */
        $syntax = plugin_load('syntax', 'gallery');
        $defaults = $syntax->getDataFromParams($syntax->getConf('options'));
        $attributes = $this->cleanAttributes($defaults);

        if (!isset($JSINFO['plugins'])) {
            $JSINFO['plugins'] = [];
        }
        $JSINFO['plugins']['gallery'] = [
            'defaults' => array_map(function ($default) {
                return ['default' => $default,];
            }, $attributes),
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
    public function renderFromInstructions(Doku_Event $event, $param)
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
        $data = $this->cleanAttributes($data);

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
     * Slightly rewrite the attributes to the format expected by our schema
     *
     * @param $data
     *
     * @return mixed
     */
    public function cleanAttributes($data)
    {
        $data['thumbnailsize'] = $data['tw'] . 'x' . $data['th'];
        $data['imagesize'] = $data['iw'] . 'X' . $data['ih'];
        if ($data['random']) {
            $data['sort'] = 'random';
        } else {
            $data['sort'] .= 'sort';
        }

        if ($data['align'] === 1) {
            $data['align'] = 'right';
        } else if ($data['align'] === 2) {
            $data['align'] = 'left';
        } else {
            $data['align'] = 'center';
        }

        unset($data['tw'], $data['th'], $data['iw'], $data['ih'], $data['random']);
        unset($data['ns'], $data['galid']);

        return $data;
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
    public function parseToSyntax(Doku_Event $event, $param)
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
    public function renderAttributesToHTML(Doku_Event $event, $param)
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

