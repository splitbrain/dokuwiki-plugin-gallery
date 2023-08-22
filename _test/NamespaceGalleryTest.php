<?php

namespace dokuwiki\plugin\gallery\test;

use dokuwiki\plugin\gallery\classes\NamespaceGallery;
use dokuwiki\plugin\gallery\classes\Options;
use DokuWikiTest;

/**
 * Namespace Gallery tests for the gallery plugin
 *
 * @group plugin_gallery
 * @group plugins
 */
class NamespaceGalleryTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['gallery'];

    /**
     * Copy demo images to the media directory
     *
     * @inheritdoc
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        global $conf;
        \TestUtils::rcopy($conf['mediadir'], __DIR__ . '/data/media/gallery');
    }


    /**
     * Check that the images are returned correctly
     */
    public function testGetImages()
    {
        $gallery = new NamespaceGallery('gallery', new Options());

        $images = $gallery->getImages();
        $this->assertIsArray($images);
        $this->assertCount(3, $images);
    }
}
