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
     * @param EventHandler $controller DokuWiki's event controller object
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
     * @param Event $event event object
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function writeDefaultsToJSINFO(Event $event, $param)
    {
        global $JSINFO;

        $defaults = $this->getDefaults();

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
     * @param Event $event event object
     * @param mixed $param [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function renderFromInstructions(Event $event, $param)
    {
        if ($event->data['name'] !== 'gallery_main') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        $node = new Node('dwplugin_gallery');
        $data = $event->data['data'];
        // FIXME source can be something other than namespace
        [$ns, $options] = $data;

        if (cleanID($ns) === $ns) {
            $ns = ':' . $ns;
        }
        $node->attr('namespace', $ns);

        $attrs = $this->optionsToAttrs($options);
        foreach ($attrs as $name => $value) {
            $node->attr($name, $value);
        }
        $event->data['renderer']->nodestack->add($node);
    }

    /**
     * Render our syntax instructions for prosemirror
     *
     * Triggered by event: PROSEMIRROR_PARSE_UNKNOWN
     *
     * @param Event $event event object
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
     * @param Event $event event object
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

    /**
     * Get default node attributes from gallery Options object
     */
    public function getDefaults(): array
    {
        $options = new Options();

        return [
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
    }

    /**
     * Convert gallery options to node attributes
     *
     * @param Options $options
     * @return array
     */
    protected function optionsToAttrs($options)
    {
        $attrs = (array)$options;

        $attrs['thumbnailsize'] = $options->thumbnailWidth . 'x' . $options->thumbnailHeight;
        $attrs['imagesize'] = $options->lightboxWidth . 'X' . $options->lightboxHeight;
        $attrs['cols'] = $options->columns;

        unset($attrs['thumbnailWidth']);
        unset($attrs['thumbnailHeight']);
        unset($attrs['lightboxWidth']);
        unset($attrs['lightboxHeight']);
        unset($attrs['columns']);

        return $attrs;
    }
}
