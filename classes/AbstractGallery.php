<?php

namespace dokuwiki\plugin\gallery\classes;

use dokuwiki\Utf8\Sort;

abstract class AbstractGallery
{
    /** @var Image[] */
    protected $images = [];
    /** @var Options */
    protected $options;

    /**
     * Initialize the Gallery
     *
     * @param mixed $src The source from where to get the images
     * @param Options $options Gallery configuration
     */
    public function __construct($src, Options $options)
    {
        $this->options = $options;
    }

    /**
     * Simple heuristic if something is an image
     *
     * @param string $src
     * @return bool
     */
    public function hasImageExtension($src)
    {
        return (bool)preg_match(Image::IMG_REGEX, $src);
    }

    /**
     * Get the images of this gallery
     *
     * The result will be sorted, reversed and limited according to the options
     *
     * @return Image[]
     */
    public function getImages()
    {
        $images = $this->images; // create a copy of the array

        switch ($this->options->sort) {
            case Options::SORT_FILE:
                usort($images, function ($a, $b) {
                    return Sort::strcmp($a->getFilename(), $b->getFilename());
                });
                break;
            case Options::SORT_CTIME:
                usort($images, function ($a, $b) {
                    return $a->getCreated() - $b->getCreated();
                });
                break;
            case Options::SORT_MTIME:
                usort($images, function ($a, $b) {
                    return $a->getModified() - $b->getModified();
                });
                break;
            case Options::SORT_TITLE:
                usort($images, function ($a, $b) {
                    return Sort::strcmp($a->getTitle(), $b->getTitle());
                });
                break;
            case Options::SORT_RANDOM:
                shuffle($images);
                break;
        }
        if ($this->options->reverse) {
            $images = array_reverse($images);
        }
        if ($this->options->offset) {
            $images = array_slice($images, $this->options->offset);
        }
        if ($this->options->limit) {
            $images = array_slice($images, 0, $this->options->limit);
        }

        return $images;
    }
}
