<?php
/**
 * DokuWiki Plugin HyperLink; Syntax braces {{...}}
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 *  - force linkonly for media
 *  - allow anchor text formatting (formattable link text)
 *  - optional target parameter of links
 *    such as "_blank", "_self", "window 800x600"
 *
 * SYNTAX:
 *    !{{ns:image.png target="_blank" | **bold text** }}        internal media
 *    !{{doku>interwiki.jpg target="_blank" | text }}           interwiki
 *    !{{http://exaple.com/sample.pdf target="_self"| text}}    external url
 *    
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_hyperlink_braces extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $parser = null; // helper/parser.php
    protected $link_data;

    // "\{\{[^\}]+\}\}"

    // pattern 1 will match page link with title text
    // pattern 2 will match page link without title text
    protected $entry_pattern1 = '!\{\{[^\|\n]*?\|(?=.*?\}\}(?!\}))';
    protected $entry_pattern2 = '!\{\{[^\n]*?(?=\}\}(?!\}))';
    protected $exit_pattern   = '\}\}(?!\})';


    function __construct() {
        $this->mode = substr(get_class($this), 7);
    }


    function getType()  { return 'formatting'; }
    function getAllowedTypes() { return array('formatting', 'substition', 'disabled'); }
    function getPType() { return 'normal'; }
    function getSort()  { return 318; } // < Doku_Parser_Mode_media(=320)

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->entry_pattern1, $mode, $this->mode);
        $this->Lexer->addEntryPattern($this->entry_pattern2, $mode, $this->mode);
    }
    function postConnect() {
        $this->Lexer->addExitPattern($this->exit_pattern, $this->mode);
    }


    /**
     * decide which kind of media it is
     * @see function media_isexternal() in inc/media.php
     * @see function Doku_Handler_Parse_Media() in inc/parser/handler.php
     */
    protected function _getMediaType($id) {

        if ( preg_match('#^(?:https?|ftp)://#i', $id) ) {
            $type = 'externalmedia';
        } elseif ( preg_match('/^[a-zA-Z0-9\.]+>/',$id) ) {
            $type = 'interwikimedia';
        } else {
            $type = 'internalmedia';
        }
        return $type;
    }

    /**
     * get media alignment
     * @see function Doku_Handler_Parse_Media() in inc/parser/handler.php
     */
    protected function _getMediaAlignment($id) {
                // Check alignment
                // {{ns:image.png?50 target="_blank"| title}}   normal
                // {{ns:image.png?50  target="_blank"| title}}  left-align
                // {{ ns:image.png?50  target="_blank"| title}} center
                // {{ ns:image.png?50 target="_blank"| title}}  right-align

        $ralign = (substr($id,0,1) == ' ') ? true : false;
        $lalign = (substr($id, -1) == ' ') ? true : false;

        if ( $lalign & $ralign ) {
            $align = 'center';
        } elseif ( $ralign ) {
            $align = 'right';
        } elseif ( $lalign ) {
            $align = 'left';
        } else {
            $align = null;
        }
        return $align;
    }

    /**
     * get media properties
     * @see function Doku_Handler_Parse_Media() in inc/parser/handler.php
     *
     * @param  (string) $query
     * @return (array)
     */
    protected function _getMediaProps($query) {
        $attrs = array();
        $query = str_replace('&',' ', $query);

        // load prameter parser utility
        if (is_null($this->parser)) {
            $this->parser = $this->loadHelper('hyperlink_parser');
        }
        $attrs = $this->parser->getArguments($query);

        //parse width and height
        if (!isset($attrs['width']) && isset($attrs['size'])) {
            $attrs['width'] = $attrs['size'];
            unset($attrs['size']);
        }

        //get linking command
        if (@$attrs['link'] == false) {
            $linking = 'nolink';
        } elseif (@$attrs['direct']) {
            $linking = 'direct';
        } elseif (@$attrs['linkeonly']) {
            $linking = 'linkonly';
        } else {
            $linking = 'details';
        }
        $attrs['linking'] = $linking;

        //get caching command
        if (preg_match('/(nocache|recache)/i',$query,$cachemode)) {
            $cache = $cachemode[1];
        } else {
            $cache = 'cache';
        }
        $attrs['cache'] = $cache;

        return $attrs;
    }

    /**
     * get link attributes
     *
     * @param  (string) $query
     * @return (array)
     */
    protected function _getLinkAttributes($query) {
        $attrs = array();

        // load prameter parser utility
        if (is_null($this->parser)) {
            $this->parser = $this->loadHelper('hyperlink_parser');
        }
        $attrs = $this->parser->getArguments($query);

        // modify target attributes if we need to open the link in a new window
        if (preg_match('/^window\b/', $attrs['target'])) {
            $opts = $this->parser->getArguments($attrs['target']);

            $attrs['target'] = 'window';
            $attrs['class'] .= ($attrs['class'] ? ' ' : '').'openwindow';

            // add JavaScript to open a new window
            $js = $this->loadHelper('hyperlink_window');
            $attrs['onclick'] = $js->window_open($opts);
        } else {
            unset($attrs['onclick']);
        }

        return $attrs;
    }

    /**
     * handle syntax
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        switch ($state) {
            case DOKU_LEXER_ENTER:
                // see how link text should be rendered
                if (substr($match, -1) == '|') {
                    // matched entry_pattern1
                    $str = substr($match, 3, -1);
                    $text = null;  // link text is not necessary
                } else {
                    // matched entry_pattern2, we must render link text
                    $str = substr($match, 3);
                    $text = false;
                }
                $str = str_replace("\t",' ', $str);

                // separate id and options
                $matches = explode(' ', $str);
                $id = $options = '';
                $appendTo = 'id';
                foreach ($matches as $part) {
                    if (($appendTo == 'id' ) && (strpos($part, '="') !== false)) {
                        $appendTo = 'options';
                    }
                    ${$appendTo}.= (${$appendTo}) ? ' ' : '';
                    ${$appendTo}.= $part ;
                }

                // Check alignment of id
                $align = $this->_getMediaAlignment($id);

                // remove aligning spaces
                $id = trim($id);

                // split into src and parameters (using the very last questionmark)
                if (($p = strrpos($id, '?')) !== false) {
                    $src   = substr($id, 0, $p);
                    $param = substr($id, $p+1); // drop '?'
                } else {
                    $src   = $id;
                    $param = '';
                }

                // Check whether this is a local or remote image
                $call = $this->_getMediaType($src);
                $opts = array();

                // parse param
                $param = str_replace('&',' ',$param);
                $opts += $this->_getMediaProps($param);

                // adjust media linking parameter
                list($ext, $mime) = mimetype($src, false);
                if (substr($mime, 0, 5) == 'image') {
                    // force "linkonly"
                    $opts['linking'] = 'linkonly';
                } elseif ($mime == 'application/x-shockwave-flash') {
                    $opts['linking'] = 'linkonly';
                } elseif (media_supportedav($mime)) {
                    $opts['linking'] = 'linkonly';
                } else {
                    unset($opts['linking']);
                }

                // parse link options
                $opts += $this->_getLinkAttributes($options);

                $data = array($call, $src, $opts, $text);
                $this->link_data = $data;

                // intercept calls
                $ReWriter = new Doku_Handler_Nest($handler->CallWriter, $this->mode);
                $handler->CallWriter = & $ReWriter;
                // don't add any plugin instruction
                return false;

            case DOKU_LEXER_UNMATCHED: // link text
                // happens only for entry_pattern1
                $handler->_addCall('cdata', array($match), $pos);
                return false;

            case DOKU_LEXER_EXIT:      // $match is ']]'
                // get all calls we intercepted
                $calls = $handler->CallWriter->calls;

                // switch back to the old call writer
                $ReWriter = & $handler->CallWriter;
                $handler->CallWriter = & $ReWriter->CallWriter;

                // return a plugin instruction
                return array($state, $calls, $this->link_data);

        }
        return false;
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $indata) {
        global $ID, $conf;

        if ($format == 'metadata') return false;
        list($state, $calls, $link_data) = $indata;

        if ($state !== DOKU_LEXER_EXIT) return true;

        /* Entry data */
        list($call, $src, $opts, $text) = $link_data;


        /* 
         * generate html of link anchor
         * see relevant functions in inc/parser/xhtml.php file
         */
        switch ($call) {
            case 'interwikimedia':
                list($shortcut, $reference) = explode('>', $src, 2);
                $exists = null;
                $src = $renderer->_resolveInterWiki($shortcut, $reference, $exists);
            case 'externalmedia':
                $output = $renderer->externalmedia($src,
                            'TITLE',
                            $opts['align'], $opts['width'], $opts['height'],
                            $opts['cache'], $opts['linking'], true
                );
                break;
            case 'internalmedia':
                $output = $renderer->internalmedia($src,
                            'TITLE',
                            $opts['align'], $opts['width'], $opts['height'],
                            $opts['cache'], $opts['linking'], true
                );
                break;
            default:
                // dummy output
                $output = '<a href="example.com" title="example">example.com</a>';
        } //end of switch

        // get open tag of anchor
        $html = strstr($output, '>', true).'>';

        // optional attributes
        foreach ($opts as $attr => $value) {
            // restrict effective attributes
            if (!in_array($attr, array('class','target','title','onclick'))) {
                continue;
            }
            $append = in_array($attr, array('class'));
            $html = $this->setAttribute($html, $attr, $value, $append);
        }

        // open anchor tag <a>
        if ($curid) {
            $html = $this->highlighter[0].$html;
        }
        $renderer->doc.= $html;

        // render "unmatched" parts as link text
        if (is_null($text) && is_array($calls)) {
            foreach ($calls as $i) {
                if (method_exists($renderer, $i[0])) {
                    switch ($i[0]) {
                        case 'cdata':
                        case 'smiley':
                            if (!$text && trim($i[1][0])) $text = true;
                            break;
                        case 'externallink':  // external url
                            $i[0] = 'cdata';
                            if (!empty($i[1][1])) $i[1][0] = $i[1][1];
                            if (!$text) $text = true;
                            break;
                        case 'internalmedia':
                        case 'externalmedia':
                            /* force nolink for images only
                            list($ext, $mime) = mimetype($i[1][0], false);
                            if (substr($mime, 0, 5) == 'image') {
                                $i[1][6] = 'nolink';
                            } */
                            $i[1][6] = 'nolink'; // force nolink for any media files
                            if (!$text) $text = true;
                            break;
                    }
                    call_user_func_array(array($renderer,$i[0]), $i[1]);
                }
            }
        }
        // we should avoid non-visible link
        if (!$text) {
            $text = strstr($output,'>');
            $text = substr( $text, 1, -4); // drop '>' and '</a>'
            $renderer->doc.= $text;
        }

        // close </a>
        $html = '</a>';
        if ($curid) {
            $html = $html.$this->highlighter[1];
            unset($curid);
        }
        $renderer->doc.= $html;
        return true;
    }


    /**
     * Set or Append attribute to html tag
     *
     * @param  (string) $html   subject of replacement
     * @param  (string) $key    name of attribute
     * @param  (string) $value  value of attribute
     * @param  (string) $append true if appending else overwrite
     * @return (string) replaced html
     */
    protected function setAttribute($html, $key, $value, $append=false) {
        if (strpos($html, ' '.$key.'=') !== false) {
            $search = '/\b('.$key.')=([\"\'])(.*?)\g{-2}/';
            if ($append) {
                $replacement = '${1}=${2}'.$value.' ${3}${2}';
            } else {
                $replacement = '${1}=${2}'.$value.'${2}';
            }
            $html = preg_replace($search, $replacement, $html, 1);
        } elseif (strpos($html, ' ') !== false) {
            $search = strstr($html, ' ', true);
            $replacement = $search.' '.$key.'="'.$value.'"';
            $html = str_replace($search, $replacement, $html);
        } else {
            $html = rtrim($html, ' />').' '.$key.'="'.$value.'">';
        }
        return $html;
    }

}
