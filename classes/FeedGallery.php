<?php

namespace dokuwiki\plugin\gallery\classes;

use FeedParser;
use SimplePie\Enclosure;

class FeedGallery extends AbstractGallery
{
    protected $feedHost;
    protected $feedPath;

    /**
     * @inheritdoc
     * @param string $url
     */
    public function __construct($url, Options $options)
    {
        parent::__construct($url, $options);
        $this->initBaseUrl($url);
        $this->parseFeed($url);
    }

    /**
     * Parses the given feed and adds all images to the gallery
     *
     * @param string $url
     * @return void
     * @throws \Exception
     */
    protected function parseFeed($url)
    {
        $feed = new FeedParser();
        $feed->set_feed_url($url);

        $ok = $feed->init();
        if (!$ok) throw new \Exception($feed->error());

        foreach ($feed->get_items() as $item) {
            $enclosure = $item->get_enclosure();
            if (!$enclosure instanceof Enclosure) continue;

            // skip non-image enclosures
            if ($enclosure->get_type() && substr($enclosure->get_type(), 0, 5) != 'image') {
                continue;
            } elseif (!$this->hasImageExtension($enclosure->get_link())) {
                continue;
            }

            $enclosureLink = $this->makeAbsoluteUrl($enclosure->get_link());
            $detailLink = $this->makeAbsoluteUrl($item->get_link());

            $image = new Image($enclosureLink);
            $image->setDetaillink($detailLink);
            $image->setTitle(htmlspecialchars_decode($enclosure->get_title() ?? '', ENT_COMPAT));
            $image->setDescription(strip_tags(htmlspecialchars_decode(
                $enclosure->get_description() ?? '',
                ENT_COMPAT
            )));
            $image->setCreated($item->get_date('U'));
            $image->setModified($item->get_date('U'));
            $image->setWidth($enclosure->get_width());
            $image->setHeight($enclosure->get_height());

            $this->images[] = $image;
        }
    }

    /**
     * Make the given URL absolute using feed's URL as base
     *
     * @param string $url
     * @return string
     */
    protected function makeAbsoluteUrl($url)
    {

        if (!preg_match('/^https?:\/\//i', $url)) {
            if ($url[0] == '/') {
                $url = $this->feedHost . $url;
            } else {
                $url = $this->feedHost . $this->feedPath . $url;
            }
        }
        return $url;
    }

    /**
     * Initialize base url to use for broken feeds with non-absolute links
     * @param string $url The feed URL
     * @return void
     */
    protected function initBaseUrl($url)
    {
        $main = parse_url($url);
        $this->feedHost = $main['scheme'] . '://' . $main['host'] . (empty($main['port']) ? '' : ':' . $main['port']);
        $this->feedPath = dirname($main['path']) . '/';
    }
}
