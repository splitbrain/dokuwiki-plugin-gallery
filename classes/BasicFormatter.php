<?php

namespace dokuwiki\plugin\gallery\classes;


/**
 * Formats the gallery
 *
 * This is the most basic implementation. It simply adds linked thumbnails to the page. It will not look
 * good, but will work with any renderer. Specialized formatters can be created for each renderer to make
 * use of their special features.
 */
class BasicFormatter {

    protected Options $options;
    protected \Doku_Renderer $renderer;

    /**
     * Create a new Gallery formatter
     *
     * @param \Doku_Renderer $renderer
     * @param Options $options
     */
    public function __construct(\Doku_Renderer $renderer, Options $options)
    {
        $this->options = $options;
        $this->renderer = $renderer;
    }

    /**
     * Render the whole Gallery
     *
     * @param AbstractGallery $gallery
     * @return void
     */
    public function render(AbstractGallery $gallery)
    {
        $images = $gallery->getImages();
        foreach ($images as $image) {
            $this->renderImage($image);
        }
    }

    /**
     * Render a single thumbnail image in the gallery
     *
     * @param Image $image
     * @return void
     */
    protected function renderImage(Image $image) {
        list($w, $h) = $this->getThumbnailSize($image);
        $link = $image->getDetaillink() ?: $image->getSrc();

        $imgdata = [
            'src' => $image->getSrc(),
            'title' => $image->getTitle(),
            'align' => '',
            'width' => $w,
            'height' => $h,
            'cache' => ''
        ];

        if($image->isExternal()) {
            $this->renderer->externallink($link, $imgdata);
        } else {
            $this->renderer->internalmedia(":$link", $imgdata); // prefix with : to ensure absolute src
        }
    }


    // region Utilities

    /**
     * Calculate the thumbnail size
     */
    protected function getThumbnailSize(Image $image)
    {
        $crop = $this->options->crop;
        if (!$image->getWidth() || !$image->getHeight()) {
            $crop = true;
        }
        if (!$crop) {
            list($thumbWidth, $thumbHeight) = $this->fitBoundingBox(
                $image->getWidth(),
                $image->getHeight(),
                $this->options->thumbnailWidth,
                $this->options->thumbnailHeight
            );
        } else {
            $thumbWidth = $this->options->thumbnailWidth;
            $thumbHeight = $this->options->thumbnailHeight;
        }
        return [$thumbWidth, $thumbHeight];
    }


    /**
     * Calculate the size of a thumbnail to fit into a bounding box
     *
     * @param int $imgWidth
     * @param int $imgHeight
     * @param int $bBoxWidth
     * @param int $bBoxHeight
     * @return int[]
     */
    protected function fitBoundingBox($imgWidth, $imgHeight, $bBoxWidth, $bBoxHeight)
    {
        $scale = min($bBoxWidth / $imgWidth, $bBoxHeight / $imgHeight);

        $width = round($imgWidth * $scale);
        $height = round($imgHeight * $scale);

        return [$width, $height];
    }

    // endregion
}
