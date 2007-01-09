<?php
/**
 * Embed an image gallery
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Joe Lapp <joe.lapp@pobox.com>
 * @author     Dave Doyle <davedoyle.canadalawbook.ca>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/JpegMeta.php');

class syntax_plugin_gallery extends DokuWiki_Syntax_Plugin {
    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Andreas Gohr',
            'email'  => 'andi@splitbrain.org',
            'date'   => '2006-11-12',
            'name'   => 'Gallery Plugin',
            'desc'   => 'Creates a gallery of images from a namespace',
            'url'    => 'http://wiki.splitbrain.org/plugin:gallery',
        );
    }

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 301;
    }


    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{gallery>[^}]*\}\}',$mode,'plugin_gallery');
    }


    /**
     * Parse option
     */
    function parseOpt($params, $name) {
        if(preg_match('/\b'.$name.'\b/i',$params,$match)) {
	    return true;
        }else if(preg_match('/\bno'.$name.'\b/i',$params,$match)) {
	    return false;
	}else{
            return $this->getConf($name);
        }
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        $match = substr($match,10,-2); //strip markup from start and end

        $data = array();

        //handle params
        list($ns,$params) = explode('?',$match,2);

        //namespace
        $data['ns'] = $ns;

        //max thumb dimensions
        if(preg_match('/\b(\d+)x(\d+)\b/',$params,$match)){
            $data['w'] = $match[1];
            $data['h'] = $match[2];
        }else{
            $data['w'] = $this->getConf('thumbnail_width');
            $data['h'] = $this->getConf('thumbnail_height');
        }

        //max lightbox dimensions
        if(preg_match('/\b(\d+)X(\d+)\b/',$params,$match)){
            $data['w_lightbox'] = $match[1];
            $data['h_lightbox'] = $match[2];
        }else{
            $data['w_lightbox'] = $this->getConf('image_width');
            $data['h_lightbox'] = $this->getConf('image_height');
        }

        //number of images per row
        if(preg_match('/\b(\d+)\b/i',$params,$match)){
            $data['cols'] = $match[1];
        }else{
            $data['cols'] = $this->getConf('cols');
        }

        //show the filename
	$data['showname'] = $this->parseOpt($params, 'showname');

        //lightbox style?
	$data['lightbox'] = $this->parseOpt($params, 'lightbox');
	
        //direct linking?
	if($data['lightbox']) {
            $data['direct']   = true; //implicit direct linking
	}else{
	    $data['direct'] = $this->parseOpt($params, 'direct');
	}

        //reverse sort?
	$data['reverse'] = $this->parseOpt($params, 'reverse');

        return $data;
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        if($mode == 'xhtml'){
            $renderer->doc .= $this->_gallery($data);
            return true;
        }
        return false;
    }

    /**
     * Does the gallery formatting
     */
    function _gallery($data){
        global $conf;
        global $lang;
        $ret = '';

        //use the search to get all files
        $ns = cleanID($data['ns']);
        $dir = utf8_encodeFN(str_replace(':','/',$ns));
        $files = array();
        search($files,$conf['mediadir'],'search_media',array(),$dir);

        //anything found?
        if(!count($files)){
            $ret .= '<div class="nothing">'.$lang['nothingfound'].'</div>';
            return $ret;
        }

        //reverse if wanted
        if($data['reverse']) rsort($files);

        // build gallery
        if($data['cols'] > 0){ // format as table
            $ret .= '<table class="gallery">';
            $i = 0;
            foreach($files as $img){
                if(!$img['isimg']) continue;

                if($i == 0){
                    $ret .= '<tr>';
                }

                $ret .= '<td>';
                $ret .= $this->_image($img,$data);
                $ret .= $this->_showname($img,$data);
                $ret .= '</td>';

                $i++;

                $close_tr = true;
                if($i == $data['cols']){
                    $ret .= '</tr>';
                    $close_tr = false;
                    $i = 0;
                }
            }

            if ($close_tr){
                // add remaining empty cells
                for(;$i < $data['cols']; $i++){
                    $ret .= '<td></td>';
                }
                $ret .= '</tr>';
            }

            $ret .= '</table>';
        }else{ // format as div sequence
            $ret .= '<div class="gallery">';

            foreach($files as $img){
                if(!$img['isimg']) continue;

                $ret .= '<div>';
                $ret .= $this->_image($img,$data);
                $ret .= $this->_showname($img,$data);
                $ret .= '</div> ';
            }

            $ret .= '<br style="clear:both" /></div>';
        }

        return $ret;
    }

    /**
     * Defines how a thumbnail should look like
     */
    function _image($img,$data){
        global $ID;

        $w = $img['meta']->getField('File.Width');
        $h = $img['meta']->getField('File.Height');
        $dim = array();
        if($w > $data['w'] || $h > $data['h']){
            $ratio = $img['meta']->getResizeRatio($data['w'],$data['h']);
            $w = floor($w * $ratio);
            $h = floor($h * $ratio);
            $dim = array('w'=>$w,'h'=>$h);
        }

        //prepare img attributes
        $i           = array();
        $i['width']  = $w;
        $i['height'] = $h;
        $i['border'] = 0;
        $i['alt']    = $img['meta']->getField('Simple.Title');
        $i['class']  = 'tn';
        $iatt = buildAttributes($i);
        $src  = ml($img['id'],$dim);

        // prepare lightbox dimensions
        $w_lightbox = $img['meta']->getField('File.Width');
        $h_lightbox = $img['meta']->getField('File.Height');
        $dim_lightbox = array();
        if($w_lightbox > $data['w_lightbox'] || $h_lightbox > $data['h_lightbox']){
            $ratio = $img['meta']->getResizeRatio($data['w_lightbox'],$data['h_lightbox']);
            $w_lightbox = floor($w_lightbox * $ratio);
            $h_lightbox = floor($h_lightbox * $ratio);
            $dim_lightbox = array('w'=>$w_lightbox,'h'=>$h_lightbox);
        }

        //prepare link attributes
        $a           = array();
        $a['title']  = $img['meta']->getField('Simple.Title');
        if($data['lightbox']){
            $href   = ml($img['id'],$dim_lightbox);
            $a['class'] = "lightbox JSnocheck";
            $a['rel']   = "lightbox";
        }else{
            $href   = ml($img['id'],array('id'=>$ID),$data['direct']);
        }
        $aatt = buildAttributes($a);

        // prepare output
        $ret  = '';
        $ret .= '<a href="'.$href.'" '.$aatt.'>';
        $ret .= '<img src="'.$src.'" '.$iatt.' />';
        $ret .= '</a>';
        return $ret;
    }


    /**
     * Defines how a filename + link should look
     */
    function _showname($img,$data){
        global $ID;

        if(!$data['showname']) { return ''; }

        //prepare link
        $lnk = ml($img['id'],array('id'=>$ID),false);

        // prepare output
        $ret  = '';
        $ret .= '<br /><a href="'.$lnk.'">';
        $ret .= $img['file']; // see fix posted on the wiki
        $ret .= '</a>';
        return $ret;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
