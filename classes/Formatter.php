<?php

namespace dokuwiki\plugin\gallery\classes;


class Formatter
{
    protected Options $options;

    /**
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    // region Main Formatters

    /**
     * @param AbstractGallery $gallery
     * @return string
     */
    public function format(AbstractGallery $gallery)
    {
        $html = '<div class="plugin-gallery" id="gallery__' . $this->options->galleryID . '">';

        $images = $gallery->getImages();
        $pages = $this->paginate($images);
        foreach ($pages as $page => $images) {
            $html .= $this->formatPage($images, $page);
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Create an array of pages for the given images
     *
     * @param Image[] $images
     * @return Image[][]
     */
    protected function paginate($images)
    {
        if ($this->options->paginate) {
            $pages = array_chunk($images, $this->options->paginate);
        } else {
            $pages = [$images];
        }

        return $pages;
    }

    /**
     * Format the given images into a gallery page
     *
     * @param Image[] $images
     * @param int $page The page number
     * @return string
     */
    protected function formatPage($images, int $page)
    {
        $html = '<div class="gallery_page" id="gallery__' . $this->options->galleryID . '_' . $page . '">';
        foreach ($images as $image) {
            $html .= $this->formatImage($image);
        }
        $html .= '</div>';
        return $html;
    }

    protected function formatImage(Image $image)
    {
        global $ID;

        // thumbnail image properties
        list($w, $h) = $this->getThumbnailSize($image);
        $img = [];
        $img['width'] = $w;
        $img['height'] = $h;
        $img['src'] = ml($image->getSrc(), ['w' => $w, 'h' => $h], true, '&');
        $img['alt'] = $image->getFilename();
        $img['loading'] = 'lazy';

        // link properties
        $a = [];
        $a['href'] = $this->getDetailLink($image);
        $a['title'] = $image->getTitle();
        $a['data-caption'] = $image->getDescription();
        if ($this->options->lightbox) {
            $a['class'] = "lightbox JSnocheck";
            $a['rel'] = 'lightbox[gal-' . substr(md5($ID), 4) . ']'; //unique ID for the gallery
            $a['data-url'] = $this->getLightboxLink($image);
        }

        // figure properties
        $fig = [];
        $fig['class'] = 'gallery-image';
        $fig['style'] = 'width: ' . $w . 'px;';

        # differentiate between the URL for the lightbox and the URL for details
        # using a data-attribute
        # needs slight adjustment in the swipebox script
        # fall back to href when no data-attribute is set
        # lightbox url should have width/height limit -> adjust from old defaults to 1600x1200?
        # use detail URLs for thumbnail, title and filename
        # when direct is set it should link to full size image


        $html = '<figure ' . buildAttributes($fig, true) . '>';
        $html .= '<a ' . buildAttributes($a, true) . '>';
        $html .= '<img ' . buildAttributes($img, true) . ' />';
        $html .= '</a>';

        if ($this->options->showtitle || $this->options->showname) {
            $html .= '<figcaption>';
            if ($this->options->showname) {
                $a = [
                    'href' => $this->getDetailLink($image),
                    'class' => 'gallery-filename',
                    'title' => $image->getFilename(),
                ];
                $html .= '<a ' . buildAttributes($a) . '>' . hsc($image->getFilename()) . '</a>';
            }
            if ($this->options->showtitle) {
                $a = [
                    'href' => $this->getDetailLink($image),
                    'class' => 'gallery-title',
                    'title' => $image->getTitle(),
                ];
                $html .= '<a ' . buildAttributes($a) . '>' . hsc($image->getTitle()) . '</a>';
            }
            $html .= '</figcaption>';
        }

        $html .= '</figure>';
        return $html;
    }

    // endregion

    // region Utilities

    protected function getDetailLink(Image $image)
    {
        if ($image->getDetaillink()) {
            // external image
            return $image->getDetaillink();
        } else {
            return ml($image->getSrc(), '', $this->options->direct, '&');
        }
    }

    /**
     * Get the direct link to the image but limit it to a certain size
     *
     * @param Image $image
     * @return string
     */
    protected function getLightboxLink(Image $image)
    {
        // use original image if no size is available
        if (!$image->getWidth() || !$image->getHeight()) {
            return ml($image->getSrc(), '', true, '&');
        }

        // fit into bounding box
        list($width, $height) = $this->fitBoundingBox(
            $image->getWidth(),
            $image->getHeight(),
            $this->options->lightboxWidth,
            $this->options->lightboxHeight
        );

        // no upscaling
        if ($width > $image->getWidth() || $height > $image->getHeight()) {
            return ml($image->getSrc(), '', true, '&');
        }

        return ml($image->getSrc(), ['w' => $width, 'h' => $height], true, '&');
    }

    /** Calculate the thumbnail size */
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
