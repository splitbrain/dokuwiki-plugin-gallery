<?php

use dokuwiki\plugin\gallery\classes\Options;

/**
 * DokuWiki Plugin gallery (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <andi@splitbrain.org>
 */
class syntax_plugin_gallery_list extends syntax_plugin_gallery_main
{
    /** @inheritDoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('<gallery.*?>.+?</gallery>', $mode, 'plugin_gallery_list');
    }

    /** @inheritDoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $match = substr($match, 8, -10); //strip markup from start and end
        [$params, $list] = sexplode('>', $match, 2);

        $options = new Options();
        $options->parseParameters($params);

        $list = explode("\n", $list);
        $list = array_map('trim', $list);
        $list = array_filter($list);

        return [$list, $options];
    }
}
