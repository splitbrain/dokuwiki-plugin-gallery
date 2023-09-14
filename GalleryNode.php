<?php

namespace dokuwiki\plugin\gallery;

use dokuwiki\plugin\prosemirror\parser\Node;

/**
 * Gallery Node in Prosemirror editor
 */
class GalleryNode extends Node
{
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
    public function __construct($data, Node $parent)
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
        /** @var \action_plugin_gallery_prosemirror $action */
        $action = plugin_load('action', 'gallery_prosemirror');
        $defaults = $action->getDefaults();
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
        if ($query !== []) {
            $result .= '?' . implode('&', $query);
        }
        $result .= $alignLeft . '}}';
        return $result;
    }

    /**
     * Get syntax option-string if the option is different from the default
     *
     * @param string $paramName The name of the parameter
     * @param array $data The node's attributes from the editor
     * @param array $defaults The default options
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
