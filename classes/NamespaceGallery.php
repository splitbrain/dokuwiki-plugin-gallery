<?php

namespace dokuwiki\plugin\gallery\classes;

class NamespaceGallery extends AbstractGallery
{
    /** @inheritdoc */
    public function __construct($ns, $options)
    {
        parent::__construct($ns, $options);
        $this->searchNamespace($ns, $options->recursive, $options->filter);
    }

    /**
     * Find the images
     *
     * @param string $ns
     * @param bool $recursive search recursively?
     * @param string $filter regular expresion to filter image IDs against (without namespace)
     * @throws \Exception
     */
    protected function searchNamespace($ns, $recursive, $filter)
    {
        global $conf;

        if (media_exists($ns) && !is_dir(mediaFN($ns))) {
            // this is a single file, not a namespace
            if ($this->hasImageExtension($ns)) {
                $this->images[] = new Image($ns);
            }
        } else {
            search(
                $this->images,
                $conf['mediadir'],
                [$this, 'searchCallback'],
                [
                    'depth' => $recursive ? 0 : 1,
                    'filter' => $filter
                ],
                utf8_encodeFN(str_replace(':', '/', $ns))
            );
        }
    }

    /**
     * Callback for search() to find images
     */
    public function searchCallback(&$data, $base, $file, $type, $lvl, $opts)
    {
        if ($type == 'd') {
            if (empty($opts['depth'])) return true; // recurse forever
            $depth = substr_count($file, '/'); // we can't use level because we start deeper
            if ($depth >= $opts['depth']) return false; // depth reached
            return true;
        }

        $id = pathID($file, true);

        // skip non-valid files
        if ($id != cleanID($id)) return false;
        //check ACL for namespace (we have no ACL for mediafiles)
        if (auth_quickaclcheck(getNS($id) . ':*') < AUTH_READ) return false;
        // skip non-images
        if (!$this->hasImageExtension($file)) return false;
        // skip filtered images
        if ($opts['filter'] && !preg_match($opts['filter'], noNS($id))) return false;

        // still here, add to result
        $data[] = new Image($id);
        return false;
    }
}
