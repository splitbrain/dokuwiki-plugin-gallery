<?php

namespace dokuwiki\plugin\gallery\classes;

class Options
{
    public const SORT_FILE = 'file';
    public const SORT_CTIME = 'date';
    public const SORT_MTIME = 'mod';
    public const SORT_TITLE = 'title';
    public const SORT_RANDOM = 'random';

    public const ALIGN_FULL = 0;
    public const ALIGN_LEFT = 1;
    public const ALIGN_RIGHT = 2;
    public const ALIGN_CENTER = 3;

    // defaults
    public $galleryID = '';
    public $thumbnailWidth = 120;
    public $thumbnailHeight = 120;
    public $lightboxWidth = 1600;
    public $lightboxHeight = 1200;
    public $columns = 0;
    public $filter = '';
    public $lightbox = false;
    public $direct = false;
    public $showcaption = false;
    public $showname = false;
    public $showtitle = false;
    public $reverse = false;
    public $cache = false;
    public $crop = false;
    public $recursive = false;
    public $sort = self::SORT_FILE;
    public $limit = 0;
    public $offset = 0;
    public $paginate = 0;
    public $align = self::ALIGN_FULL;

    /**
     * Options constructor.
     */
    public function __construct()
    {
        // load options from config
        $plugin = plugin_load('syntax', 'gallery_main');
        $this->thumbnailWidth = $plugin->getConf('thumbnail_width');
        $this->thumbnailHeight = $plugin->getConf('thumbnail_height');
        $this->lightboxWidth = $plugin->getConf('image_width');
        $this->lightboxHeight = $plugin->getConf('image_height');
        $this->columns = $plugin->getConf('cols');
        $this->sort = $plugin->getConf('sort');
        $this->parseParameters($plugin->getConf('options'));
    }

    /**
     * Simple option strings parser
     *
     * @param string $params
     * @return void
     */
    public function parseParameters($params)
    {
        $params = preg_replace('/[,&?]+/', ' ', $params);
        $params = explode(' ', $params);
        foreach ($params as $param) {
            if ($param === '') continue;
            if ($param == 'titlesort') {
                $this->sort = self::SORT_TITLE;
            } elseif ($param == 'datesort') {
                $this->sort = self::SORT_CTIME;
            } elseif ($param == 'modsort') {
                $this->sort = self::SORT_MTIME;
            } elseif ($param == 'random') {
                $this->sort = self::SORT_RANDOM;
            } elseif ($param == 'left') {
                $this->align = self::ALIGN_LEFT;
            } elseif ($param == 'right') {
                $this->align = self::ALIGN_RIGHT;
            } elseif ($param == 'center') {
                $this->align = self::ALIGN_CENTER;
            } elseif ($param == 'full') {
                $this->align = self::ALIGN_FULL;
            } elseif (preg_match('/^=(\d+)$/', $param, $match)) {
                $this->limit = (int)$match[1];
            } elseif (preg_match('/^\+(\d+)$/', $param, $match)) {
                $this->offset = (int)$match[1];
            } elseif (is_numeric($param)) {
                $this->columns = (int)$param;
            } elseif (preg_match('/^~(\d+)$/', $param, $match)) {
                $this->paginate = (int)$match[1];
            } elseif (preg_match('/^(\d+)([xX])(\d+)$/', $param, $match)) {
                if ($match[2] == 'X') {
                    $this->lightboxWidth = (int)$match[1];
                    $this->lightboxHeight = (int)$match[3];
                } else {
                    $this->thumbnailWidth = (int)$match[1];
                    $this->thumbnailHeight = (int)$match[3];
                }
            } elseif (strpos($param, '*') !== false) {
                $param = preg_quote($param, '/');
                $param = '/^' . str_replace('\\*', '.*?', $param) . '$/';
                $this->filter = $param;
            } else {
                if (substr($param, 0, 2) == 'no') {
                    $opt = substr($param, 2);
                    $set = false;
                } else {
                    $opt = $param;
                    $set = true;
                }
                if (!property_exists($this, $opt) || !is_bool($this->$opt)) continue;
                $this->$opt = $set;
            }
        }
    }
}
