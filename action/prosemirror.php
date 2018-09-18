<?php

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
     * @param Doku_Event $event  event object
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
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
            'defaults' => array_map(function($default) { return ['default' => $default,];}, $attributes),
        ];
        $JSINFO['plugins']['gallery']['defaults']['namespace'] = ['default' => ''];
    }


    /**
     * Render our syntax instructions for prosemirror
     *
     * Triggered by event: PROSEMIRROR_RENDER_PLUGIN
     *
     * @param Doku_Event $event  event object
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
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
     * @param Doku_Event $event  event object
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
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
     * @param Doku_Event $event  event object
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function renderAttributesToHTML(Doku_Event $event, $param) {
        if ($event->data !== 'plugin_gallery_prosemirror') {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        global $INPUT;
        $node = new GalleryNode(['attrs' => json_decode($INPUT->str('attrs'), true)], new \dokuwiki\plugin\prosemirror\parser\RootNode([]));
        $syntax = $node->toSyntax();
        $html = p_render('xhtml', p_get_instructions($syntax), $info);
        echo $html;
    }
}

class GalleryNode extends \dokuwiki\plugin\prosemirror\parser\Node {

    protected $parent;
    protected $data;

    /**
     * GalleryNode constructor.
     *
     * Todo: This constructor will likely by abstracted away into a parent class or something at somepoint.
     *
     * @param $data
     * @param {Node} $parent
     */
    public function __construct($data, \dokuwiki\plugin\prosemirror\parser\Node $parent)
    {
        $this->parent = &$parent;
        $this->data = $data;
    }

    /**
     * Get the node's representation as DokuWiki Syntax
     *
     * @return string
     */
    public function toSyntax()
    {
        /** @var syntax_plugin_gallery $syntax */
        $syntax = plugin_load('syntax', 'gallery');
        $defaults = $syntax->getDataFromParams($syntax->getConf('options'));
        /** @var action_plugin_gallery_prosemirror $action */
        $action = plugin_load('action', 'gallery_prosemirror');
        $defaults = $action->cleanAttributes($defaults);
        $query = [];
        $attrs = $this->data['attrs'];
        if ($attrs['thumbnailsize'] !== $defaults['thumbnailsize']) {
            $query[] = $attrs['thumbnailsize'];
        }
        if ($attrs['imagesize'] !== $defaults['imagesize']) {
            $query[] = $attrs['imagesize'];
        }

        $query[] = $this->extractFlagParam('showname', $attrs, $defaults);
        $query[] = $this->extractFlagParam('showtitle', $attrs, $defaults);
        $query[] = $this->extractFlagParam('cache', $attrs, $defaults);
        $query[] = $this->extractFlagParam('crop', $attrs, $defaults);
        $query[] = $this->extractFlagParam('direct', $attrs, $defaults);
        $query[] = $this->extractFlagParam('lightbox', $attrs, $defaults);
        $query[] = $this->extractFlagParam('reverse', $attrs, $defaults);
        $query[] = $this->extractFlagParam('recursive', $attrs, $defaults);
        $query = array_filter($query);

        if ((int)$attrs['cols'] !== (int)$defaults['cols']) {
            $query[] = $attrs['cols'];
        }
        if ((int)$attrs['limit'] !== (int)$defaults['limit']) {
            $query[] = '=' . $attrs['limit'];
        }
        if ((int)$attrs['offset'] !== (int)$defaults['offset']) {
            $query[] = '+' . $attrs['offset'];
        }
        if ((int)$attrs['paginate'] !== (int)$defaults['paginate']) {
            $query[] = '~' . $attrs['paginate'];
        }
        if ((int)$attrs['sort'] !== (int)$defaults['sort']) {
            $query[] = $attrs['sort'];
        }
        if ($attrs['filter'] && strpos($attrs['filter'], '*') !== false) {
            $query[] = $attrs['filter'];
        }
        $alignLeft = $attrs['align'] === 'left' ? ' ' : '';
        $alignRight = $attrs['align'] === 'right' ? ' ' : '';
        $result = '{{gallery>' . $alignRight . $attrs['namespace'];
        if (!empty($query)) {
            $result .= '?' . implode('&', $query);
        }
        $result .= $alignLeft . '}}';
        return $result;
    }

    /**
     * Get syntax option-string if the option is different from the default
     *
     * @param string $paramName The name of the parameter
     * @param array  $data      The node's attributes from the editor
     * @param array  $defaults  The default options
     *
     * @return null|string Either the string to toggle the option away from the default or null
     */
    protected function extractFlagParam($paramName, $data, $defaults)
    {
        if ((bool)$data[$paramName] !== (bool)$defaults[$paramName]) {
            return ($defaults[$paramName] ? 'no' : '') . $paramName;
        }
        return null;
    }
}
