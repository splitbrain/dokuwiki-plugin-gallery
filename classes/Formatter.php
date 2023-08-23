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
     * Format the whole Gallery
     *
     * @param AbstractGallery $gallery
     * @return string
     */
    public function format(AbstractGallery $gallery)
    {
        $attr = [
            'id' => 'plugin__gallery_' . $this->options->galleryID,
            'class' => 'plugin-gallery',
        ];

        switch ($this->options->align) {
            case Options::ALIGN_FULL;
                $attr['class'] .= ' align-full';
                break;
            case Options::ALIGN_LEFT:
                $attr['class'] .= ' align-left';
                break;
            case Options::ALIGN_RIGHT:
                $attr['class'] .= ' align-right';
                break;
            case Options::ALIGN_CENTER:
                $attr['class'] .= ' align-center';
                break;
        }

        $html = '<div ' . buildAttributes($attr, true) . '>';
        $images = $gallery->getImages();
        $pages = $this->paginate($images);
        foreach ($pages as $page => $images) {
            $html .= $this->formatPage($images, $page);
        }
        $html .= $this->formatPageSelector($pages);
        $html .= '</div>';
        return $html;
    }

    /**
     * Format the page selector
     *
     * @param $pages
     * @return string
     */
    protected function formatPageSelector($pages)
    {
        if (count($pages) <= 1) return '';

        $plugin = plugin_load('syntax', 'gallery');

        $html = '<div class="gallery-page-selector">';
        $html .= '<span>' . $plugin->getLang('pages') . ' </span>';
        foreach (array_keys($pages) as $pid) {
            $html .= '<a href="#gallery__' . $this->options->galleryID . '_' . $pid . '">' . ($pid + 1) . '</a> ';
        }
        $html .= '</div>';
        return $html;
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
        $attr = [
            'class' => 'gallery-page',
            'id' => 'gallery__' . $this->options->galleryID . '_' . $page,
        ];

        // define the grid
        $colwidth = $this->options->thumbnailWidth . 'px';
        if ($this->options->columns) {
            $cols = $this->options->columns;
            if ($this->options->align === Options::ALIGN_FULL) {
                $colwidth = '1fr';
            } else {
                // calculate the max width for each column
                $maxwidth = '(100% / ' . $this->options->columns . ') - 1em';
                $colwidth = 'min(' . $colwidth . ', ' . $maxwidth . ')';
            }
        } else {
            $cols = 'auto-fill';
            $colwidth = 'minmax(' . $colwidth . ', 1fr)';
        }
        $attr['style'] = 'grid-template-columns: repeat(' . $cols . ', ' . $colwidth . ')';

        $html = '<div ' . buildAttributes($attr) . '>';
        foreach ($images as $image) {
            $html .= $this->formatImage($image);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Format a single thumbnail image in the gallery
     *
     * @param Image $image
     * @return string
     */
    protected function formatImage(Image $image)
    {
        global $ID;

        // thumbnail image properties
        list($w, $h) = $this->getThumbnailSize($image);
        $w *= 2; // retina
        $h *= 2;
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

        if ($this->options->lightbox) {
            // double escape for lightbox:
            $a['data-caption'] = join(' &ndash; ', array_filter([
                '<b>'.hsc($image->getTitle()).'</b>',
                hsc($image->getDescription())
            ]));
            $a['class'] = "lightbox JSnocheck";
            $a['rel'] = 'lightbox[gal-' . substr(md5($ID), 4) . ']'; //unique ID all images on the same page
            $a['data-url'] = $this->getLightboxLink($image);
        }

        // figure properties
        $fig = [];
        $fig['class'] = 'gallery-image';
        if ($this->options->align !== Options::ALIGN_FULL) {
            $fig['style'] = 'max-width: ' . $this->options->thumbnailWidth . 'px; ';
        }

        $html = '<figure ' . buildAttributes($fig, true) . '>';
        $html .= '<a ' . buildAttributes($a, true) . '>';
        $html .= '<img ' . buildAttributes($img, true) . ' />';
        $html .= '</a>';

        if ($this->options->showtitle || $this->options->showname) {
            $html .= '<figcaption>';
            if ($this->options->showtitle) {
                $a = [
                    'href' => $this->getDetailLink($image),
                    'class' => 'gallery-title',
                    'title' => $image->getTitle(),
                ];
                $html .= '<a ' . buildAttributes($a) . '>' . hsc($image->getTitle()) . '</a>';
            }
            if ($this->options->showname) {
                $a = [
                    'href' => $this->getDetailLink($image),
                    'class' => 'gallery-filename',
                    'title' => $image->getFilename(),
                ];
                $html .= '<a ' . buildAttributes($a) . '>' . hsc($image->getFilename()) . '</a>';
            }
            $html .= '</figcaption>';
        }

        $html .= '</figure>';
        return $html;
    }

    // endregion

    // region Utilities

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
     * Access the detail link for this image
     *
     * @param Image $image
     * @return string
     */
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
