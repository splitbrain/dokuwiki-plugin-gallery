<?php

namespace dokuwiki\plugin\gallery\classes;

use dokuwiki\Utf8\PhpString;

class Image
{
    public const IMG_REGEX = '/\.(jpe?g|gif|png|svg|webp)$/i';

    protected $isExternal = false;
    protected $src;
    protected $filename;
    protected $localfile;
    protected $title;
    protected $description;
    protected $width;
    protected $height;
    protected $created = 0;
    protected $modified = 0;
    protected $detaillink;


    /**
     * @param string $src local ID or external URL to image
     * @throws \Exception
     */
    public function __construct($src)
    {
        $this->src = $src;
        if (preg_match('/^https:\/\//i', $src)) {
            $this->isExternal = true;
            $path = parse_url($src, PHP_URL_PATH);
            $this->filename = basename($path);
        } else {
            $this->localfile = mediaFN($src);
            if (!file_exists($this->localfile)) throw new \Exception('File not found: ' . $this->localfile);
            $this->filename = basename($this->localfile);
            $this->modified = filemtime($this->localfile);

            $jpegMeta = new \JpegMeta($this->localfile);
            $this->title = $jpegMeta->getField('Simple.Title');
            $this->description = $jpegMeta->getField('Iptc.Caption');
            $this->created = $jpegMeta->getField('Date.EarliestTime');
            $this->width = $jpegMeta->getField('File.Width');
            $this->height = $jpegMeta->getField('File.Height');
        }
    }

    public function isExternal()
    {
        return $this->isExternal;
    }

    public function getSrc()
    {
        return $this->src;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function setFilename(string $filename)
    {
        $this->filename = $filename;
    }

    public function getLocalfile()
    {
        return $this->localfile;
    }

    public function setLocalfile(string $localfile)
    {
        $this->localfile = $localfile;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        if (empty($this->title) || $this->title == $this->filename) {
            $title = str_replace('_', ' ', $this->filename);
            $title = preg_replace(self::IMG_REGEX, '', $title);
            $title = PhpString::ucwords($title);
            return $title;
        }
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return trim(str_replace("\n", ' ', $this->description));
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @param int $width
     */
    public function setWidth($width)
    {
        $this->width = $width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @param int $height
     */
    public function setHeight($height)
    {
        $this->height = $height;
    }

    /**
     * @return int
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param int $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @return int
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @param int $modified
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
    }

    public function getDetaillink()
    {
        return $this->detaillink;
    }

    public function setDetaillink($detaillink)
    {
        $this->detaillink = $detaillink;
    }
}
