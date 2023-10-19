<?php

namespace dokuwiki\plugin\gallery\classes;

class XHTMLFormatter extends BasicFormatter
{
    // region Main Render Functions

    /** @inheritdoc */
    public function render(AbstractGallery $gallery)
    {
        $attr = [
            'id' => 'plugin__gallery_' . $this->options->galleryID,
            'class' => 'plugin-gallery',
        ];

        switch ($this->options->align) {
            case Options::ALIGN_FULL:
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

        $this->renderer->doc .= '<div ' . buildAttributes($attr, true) . '>';
        $images = $gallery->getImages();
        $pages = $this->paginate($images);
        foreach ($pages as $page => $images) {
            $this->renderPage($images, $page);
        }
        $this->renderPageSelector($pages);
        $this->renderer->doc .= '</div>';
    }

    /**
     * Render the page selector
     *
     * @param $pages
     * @return void
     */
    protected function renderPageSelector($pages)
    {
        if (count($pages) <= 1) return;

        $plugin = plugin_load('syntax', 'gallery_main');

        $this->renderer->doc .= '<div class="gallery-page-selector">';
        $this->renderer->doc .= '<span>' . $plugin->getLang('pages') . ' </span>';
        foreach (array_keys($pages) as $pid) {
            $this->renderer->doc .= sprintf(
                '<a href="#gallery__%s_%s">%d</a> ',
                $this->options->galleryID,
                $pid,
                $pid + 1
            );
        }
        $this->renderer->doc .= '</div>';
    }

    /**
     * Render the given images into a gallery page
     *
     * @param Image[] $images
     * @param int $page The page number
     * @return void
     */
    protected function renderPage($images, int $page)
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

        $this->renderer->doc .= '<div ' . buildAttributes($attr) . '>';
        foreach ($images as $image) {
            $this->renderImage($image);
        }
        $this->renderer->doc .= '</div>';
    }

    /** @inheritdoc */
    protected function renderImage(Image $image)
    {
        global $ID;

        // thumbnail image properties
        [$w, $h] = $this->getThumbnailSize($image, 2);

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
            $a['data-caption'] = implode(' &ndash; ', array_filter([
                '<b>' . hsc($image->getTitle()) . '</b>',
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
            if ($this->options->showcaption) {
                $p = [
                    'class' => 'gallery-caption',
                ];
                $html .= '<div ' . buildAttributes($p) . '>' . hsc($image->getDescription()) . '</div>';
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
        $this->renderer->doc .= $html;
    }

    // endregion

    // region Utilities

    /**
     * Access the detail link for this image
     *
     * @param Image $image
     * @return string
     */
    protected function getDetailLink(Image $image)
    {
        global $ID;

        if ($image->getDetaillink()) {
            // external image
            return $image->getDetaillink();
        } else {
            return ml($image->getSrc(), ['id' => $ID], $this->options->direct, '&');
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
        [$width, $height] = $this->fitBoundingBox(
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

    // endregion
}
