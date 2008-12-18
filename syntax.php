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
        return confToHash(dirname(__FILE__).'/info.txt');
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
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        global $ID;
        $match = substr($match,10,-2); //strip markup from start and end

        $data = array();

        // alignment
        $data['align'] = 0;
        if(substr($match,0,1) == ' ') $data['align'] += 1;
        if(substr($match,-1,1) == ' ') $data['align'] += 2;

        // extract params
        list($ns,$params) = explode('?',$match,2);
        $ns = trim($ns);

        // namespace (including resolving relatives)
        $data['ns'] = resolve_id(getNS($ID),$ns);

        // set the defaults
        $data['tw']       = $this->getConf('thumbnail_width');
        $data['th']       = $this->getConf('thumbnail_height');
        $data['iw']       = $this->getConf('image_width');
        $data['ih']       = $this->getConf('image_height');
        $data['cols']     = $this->getConf('cols');
        $data['filter']   = '';
        $data['lightbox'] = false;
        $data['direct']   = false;
        $data['showname'] = false;
        $data['showtitle'] = false;
        $data['reverse']  = false;
        $data['random']   = false;
        $data['cache']    = true;
        $data['crop']     = false;
        $data['sort']     = $this->getConf('sort');

        // parse additional options
        $params = $this->getConf('options').','.$params;
        $params = preg_replace('/[,&\?]+/',' ',$params);
        $params = explode(' ',$params);
        foreach($params as $param){
            if($param === '') continue;
            if($param == 'titlesort'){
                $data['sort'] = 'title';
            }elseif($param == 'datesort'){
                $data['sort'] = 'date';
            }elseif($param == 'modsort'){
                $data['sort'] = 'mod';
            }elseif(is_numeric($param)){
                $data['cols'] = (int) $param;
            }elseif(preg_match('/^(\d+)([xX])(\d+)$/',$param,$match)){
                if($match[2] == 'X'){
                    $data['iw'] = $match[1];
                    $data['ih'] = $match[3];
                }else{
                    $data['tw'] = $match[1];
                    $data['th'] = $match[3];
                }
            }elseif(strpos($param,'*') !== false){
                $param = preg_quote($param,'/');
                $param = '/^'.str_replace('\\*','.*?',$param).'$/';
                $data['filter'] = $param;
            }else{
                if(substr($param,0,2) == 'no'){
                    $data[substr($param,2)] = false;
                }else{
                    $data[$param] = true;
                }
            }
        }

        // implicit direct linking?
        if($data['lightbox']) $data['direct']   = true;


        return $data;
    }

    /**
     * Create output
     */
    function render($mode, &$R, $data) {
        if($mode != 'xhtml') return false;

        $R->info['cache'] = $data['cache'];
        $R->doc .= $this->_gallery($data);
        return true;
    }

    /**
     * Gather all photos matching the given criteria
     */
    function _findimages(&$data){
        global $conf;
        $files = array();

        $dir = utf8_encodeFN(str_replace(':','/',$data['ns']));

        // all possible images for the given namespace (or a single image)
        if(is_file($conf['mediadir'].'/'.$dir)){
            require_once(DOKU_INC.'inc/JpegMeta.php');
            $files[] = array(
                'id'    => $data['ns'],
                'isimg' => preg_match('/\.(jpe?g|gif|png)$/',$dir),
                'file'  => basename($dir),
                'mtime' => filemtime($conf['mediadir'].'/'.$dir),
                'meta'  => new JpegMeta($conf['mediadir'].'/'.$dir)
            );
            $data['_single'] = true;
        }else{
            search($files,$conf['mediadir'],'search_media',array(),$dir);
            $data['_single'] = false;
        }

        // done, yet?
        $len = count($files);
        if(!$len) return $files;
        if($data['single']) return $files;

        // filter images
        for($i=0; $i<$len; $i++){
            if($data['filter']){
                if(!preg_match($data['filter'],noNS($files[$i]['id']))) unset($files[$i]);
            }else{
                if(!$files[$i]['isimg']) unset($files[$i]); // this is faster, because RE was done before
            }
        }
        if($len<1) return $files;

        // random?
        if($data['random']){
            shuffle($files);
            return $files;
        }

        // sort?
        if($data['sort'] == 'date'){
            usort($files,array($this,'_datesort'));
        }elseif($data['sort'] == 'mod'){
            usort($files,array($this,'_modsort'));
        }elseif($data['sort'] == 'title'){
            usort($files,array($this,'_titlesort'));
        }

        // reverse?
        if($data['reverse']) $files = array_reverse($files);


        return $files;
    }

    /**
     * usort callback to sort by file lastmodified time
     */
    function _modsort($a,$b){
        if($a['mtime'] < $b['mtime']) return -1;
        if($a['mtime'] > $b['mtime']) return 1;
        return strcmp($a['file'],$b['file']);
    }

    /**
     * usort callback to sort by EXIF date
     */
    function _datesort($a,$b){
        $da = $a['meta']->getDateField('EarliestTime');
        $db = $b['meta']->getDateField('EarliestTime');
        if($da < $db) return -1;
        if($da > $db) return 1;
        return strcmp($a['file'],$b['file']);
    }

    /**
     * usort callback to sort by EXIF title
     */
    function _titlesort($a,$b){
        $ta = $a['meta']->getField('Simple.Title');
        $tb = $b['meta']->getField('Simple.Title');
        return strcmp($ta,$tb);
    }


    /**
     * Does the gallery formatting
     */
    function _gallery($data){
        global $conf;
        global $lang;
        $ret = '';

        $files = $this->_findimages($data);

        //anything found?
        if(!count($files)){
            $ret .= '<div class="nothing">'.$lang['nothingfound'].'</div>';
            return $ret;
        }

        // prepare alignment
        $align = '';
        $xalign = '';
        if($data['align'] == 1){
            $align  = ' gallery_right';
            $xalign = ' align="right"';
        }
        if($data['align'] == 2){
            $align  = ' gallery_left';
            $xalign = ' align="left"';
        }
        if($data['align'] == 3){
            $align  = ' gallery_center';
            $xalign = ' align="center"';
        }
        if(!$data['_single']){
            if(!$align) $align = ' gallery_center'; // center galleries on default
            if(!$xalign) $xalign = ' align="center"';
        }

        // build gallery
        if($data['_single']){
            $ret .= '<div class="gallery'.$align.'"'.$xalign.'>';
            $ret .= $this->_image($files[0],$data);
            $ret .= $this->_showname($files[0],$data);
            $ret .= $this->_showtitle($files[0],$data);
            $ret .= '</div> ';
        }elseif($data['cols'] > 0){ // format as table
            $ret .= '<table class="gallery'.$align.'"'.$xalign.'>';
            $i = 0;
            foreach($files as $img){
                if(!$img['isimg']) continue;

                if($i == 0){
                    $ret .= '<tr>';
                }

                $ret .= '<td>';
                $ret .= $this->_image($img,$data);
                $ret .= $this->_showname($img,$data);
                $ret .= $this->_showtitle($img,$data);
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
            $ret .= '<div class="gallery'.$align.'"'.$xalign.'>';

            foreach($files as $img){
                if(!$img['isimg']) continue;

                $ret .= '<div>';
                $ret .= $this->_image($img,$data);
                $ret .= $this->_showname($img,$data);
                $ret .= $this->_showtitle($img,$data);
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

        // calculate thumbnail size
        if($data['crop']){
            $w = $data['tw'];
            $h = $data['th'];
            $dim = array('w'=>$w,'h'=>$h);
        }else{
            $w = (int) $img['meta']->getField('File.Width');
            $h = (int) $img['meta']->getField('File.Height');
            $dim = array();
            if($w > $data['tw'] || $h > $data['th']){
                $ratio = $img['meta']->getResizeRatio($data['tw'],$data['th']);
                $w = floor($w * $ratio);
                $h = floor($h * $ratio);
                $dim = array('w'=>$w,'h'=>$h);
            }
        }

        //prepare img attributes
        $i             = array();
        $i['width']    = $w;
        $i['height']   = $h;
        $i['border']   = 0;
        $i['alt']      = $img['meta']->getField('Simple.Title');
        $i['longdesc'] = trim(str_replace("\n",' ',$img['meta']->getField('Iptc.Caption')));
        if(!$i['longdesc']) unset($i['longdesc']);
        $i['class']    = 'tn';
        $iatt = buildAttributes($i);
        $src  = ml($img['id'],$dim);

        // prepare lightbox dimensions
        $w_lightbox = $img['meta']->getField('File.Width');
        $h_lightbox = $img['meta']->getField('File.Height');
        $dim_lightbox = array();
        if($w_lightbox > $data['iw'] || $h_lightbox > $data['ih']){
            $ratio = $img['meta']->getResizeRatio($data['iw'],$data['ih']);
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

        if(!$data['showname'] ) { return ''; }

        //prepare link
        $lnk = ml($img['id'],array('id'=>$ID),false);

        // prepare output
        $ret  = '';
        $ret .= '<br /><a href="'.$lnk.'">';
        $ret .= hsc($img['file']);
        $ret .= '</a>';
        return $ret;
    }

    /**
     * Defines how title + link should look
     */
    function _showtitle($img,$data){
        global $ID;

        if(!$data['showtitle'] ) { return ''; }

        //prepare link
        $lnk = ml($img['id'],array('id'=>$ID),false);

        // prepare output
        $ret  = '';
        $ret .= '<br /><a href="'.$lnk.'">';
        $ret .= hsc($img['meta']->getField('Simple.Title'));
        $ret .= '</a>';
        return $ret;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
