<?php

namespace dokuwiki\plugin\gallery\test;

use dokuwiki\plugin\gallery\classes\FeedGallery;
use dokuwiki\plugin\gallery\classes\Options;
use DokuWikiTest;

/**
 * Media Feed tests for the gallery plugin
 *
 * @group plugin_gallery
 * @group plugins
 * @group internet
 */
class FeedGalleryTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['gallery'];

    public function testGetImages()
    {
        $url = 'https://www.flickr.com/services/feeds/photoset.gne?nsid=22019303@N00&set=72177720310667219&lang=en-us&format=atom';
        $gallery = new FeedGallery($url, new Options());
        $images = $gallery->getImages();
        $this->assertIsArray($images);
        $this->assertCount(3, $images);
    }
}
