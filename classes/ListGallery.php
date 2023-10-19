<?php

namespace dokuwiki\plugin\gallery\classes;

/**
 * A gallery created from a list of images
 */
class ListGallery extends AbstractGallery
{
    /**
     * @inheritdoc
     * @param string[] $src
     */
    public function __construct($src, Options $options)
    {
        parent::__construct($src, $options);

        foreach ($src as $item) {
            [$img, $meta] = sexplode(' ', $item, 2);
            [$title, $desc] = sexplode('|', $meta, 2);

            $img = trim($img);
            $title = trim($title);
            $desc = trim($desc);

            if (!$this->hasImageExtension($img)) continue;

            try {
                $image = new Image($img);
            } catch (\Exception $e) {
                // not found
                continue;
            }

            if ($title) $image->setTitle($title);
            if ($desc) $image->setDescription($desc);
            $this->images[] = $image;
        }
    }
}
