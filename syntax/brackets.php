<?php
/**
 * DokuWiki Plugin HyperLink; Syntax brackets
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 *  - allow anchor text formatting (formattable link text)
 *  - optional target parameter of links
 *    such as "_blank", "_self", "window 800x600"
 *
 * SYNTAX:
 *    [[id target="_blank" | **bold text** ]]          internal page
 *    [[doku>interwiki target="_blank" | text ]]       interwiki
 *    [[http://exaple.com target="_self" | text ]]     external url
 *    [[@foo@example.com|contact **me**!]]             mail link
 *
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_hyperlink_brackets extends DokuWiki_Syntax_Plugin {

    protected $mode;

    // "\[\[(?:(?:[^[\]]*?\[.*?\])|.*?)\]\]"

    // pattern 1 will match page link with title text
    // pattern 2 will match page link without title text
    protected $entry_pattern1 = '\[\[[^\|\n]*?\|(?=.*?\]\](?!\]))';
    protected $entry_pattern2 = '\[\[[^\n]*?(?=\]\](?!\]))';
    protected $exit_pattern   = '\]\](?!\])';

    function __construct() {
        $this->mode = substr(get_class($this), 7);
    }


    function getType()  { return 'formatting'; }
    function getAllowedTypes() { return array('formatting', 'substition', 'disabled'); }
    function getPType() { return 'normal'; }
    function getSort()  { return 298; } // < Doku_Parser_Mode_internallink(=300)

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
     * decide which kind of link it is
     * @see function internallink() in inc/parser/handler.php
     */
    protected function _getLinkType($id) {

        $link = array($id);

        if ( preg_match('/^[a-zA-Z0-9\.]+>{1}.*$/u',$link[0]) ) {
            // Interwiki
            $type = 'interwikilink';
        } elseif ( preg_match('/^\\\\\\\\[^\\\\]+?\\\\/u',$link[0]) ) {
            // Windows Share
            $type = 'windowssharelink';
        } elseif ( preg_match('#^([a-z0-9\-\.+]+?)://#i',$link[0]) ) {
            // external link (accepts all protocols)
            $type = 'externallink';
        } elseif ( preg_match('<'.PREG_PATTERN_VALID_EMAIL.'>',$link[0]) ) {
            // E-Mail (pattern above is defined in inc/mail.php)
            $type = 'emaillink';
        } elseif ( preg_match('!^#.+!',$link[0]) ) {
            // local link
            $type = 'locallink';
        } else {
            // internal link
            $type = 'internallink';
        }
        return $type;
    }


    /**
     * handle syntax
     */
    function handle($match, $state, $pos, Doku_Handler $handler){

        switch ($state) {
            case DOKU_LEXER_ENTER:
                // separate id and params
                if (substr($match, -1) == '|') {
                    // matched entry_pattern1
                    $str = substr($match, 2, -1);
                    $text = null;  // link text is not necessary
                } else {
                    // matched entry_pattern2, we must render link text
                    $str = substr($match, 2);
                    $text = false;
                }
                $str = str_replace("\t",' ', trim($str));

                //$match = preg_replace('/\s{2,}/', '', $str);
                //list($id, $params) = explode(' ', $match, 2);

                $matches = explode(' ', $str);
                $appendTo = 'id';
                foreach ($matches as $part) {
                    if (($appendTo == 'id' ) && (strpos($part, '="') !== false)) {
                        $appendTo = 'params';
                    }
                    if (${$appendTo}) ${$appendTo} .= ' ';
                    ${$appendTo} .= $part;
                }

                // check which kind of link
                $type = $this->_getLinkType($id);

                $data = array($type, $id, $params, $text);
                return array($state, $data);

            case DOKU_LEXER_UNMATCHED: // link text
                // happens only for entry_pattern1
                $handler->_addCall('cdata', array($match), $pos);
                return false;

            case DOKU_LEXER_EXIT:      // $match is ']]'
                return array($state, '');
        }
        return false;
    }

    /**
     * Render output
     */
    function render($format, Doku_Renderer $renderer, $indata) {
        global $ID, $conf;

        if ($format !== 'xhtml') return false;
        list($state, $data) = $indata;

        switch($state) {
            case DOKU_LEXER_ENTER:
                list($type, $id, $params, $text) = $data;

                /* 
                 * generate html of link anchor
                 * see relevant functions in inc/parser/xhtml.php file
                 */
                if ($type == 'locallink') {
                    $output = $renderer->locallink($id, $name, true);
                } elseif ($type == 'internallink') {
                    $output = $renderer->internallink($id, $name, $search, true, 'content');
                } elseif ($type == 'externallink') {
                    $output = $renderer->externallink($id, $name, true);
                } elseif ($type == 'interwikilink') {
                    list($wikiName, $wikiUri) = explode('>', $id, 2);
                    $wikiName = strtolower($wikiName);
                    $output = $renderer->interwikilink($id, $name, $wikiName, $wikiUri, true);
                } elseif ($type == 'windowssharelink') {
                    $output = $renderer->windowssharelink($id, $name, true);
                } elseif ($type == 'emaillink') {
                    $output = $renderer->emaillink($id, $name, true);
                } else {
                    // dummy output
                    $output = '<a href="example.com" title="example">example.com</a>';
                }
                $html = strstr($output, '>', true);

                if ($params) {
                    $attrs = $this->getArguments($params);

                    // modify attributes if we need to open the link in a new window
                    if (preg_match('/^window\b/',$attrs['target'])) {
                        $opts = $this->getArguments($attrs['target']);
                        $attrs['target'] = 'window';
                        $attrs['class'] .= ($attrs['class'] ? ' ' : '').'openwindow';
                    }

                    foreach ($attrs as $attr => $value) {
                        // restrict effective attributs
                        if (!in_array($attr, array('class','target','title'))) {
                            continue;
                        }
                        $append = in_array($attr, array('class'));
                        $html = $this->setAttribute($html, $attr, $value, $append);
                    }

                    // add JavaScript to open a new window
                    if ($attrs['target'] == 'window') {
                        $html = $this->setAttribute($html, 'onclick', $this->_window_open($opts));
                    }

                }
                $html.= '>';
                $renderer->doc.= $html;

                // render link text, if necessary
                if ($text !== null) {
                    $text = strstr($output,'>');
                    $text = substr( $text, 1, -4); // drop '>' and '</a>'
                    $renderer->doc.= $text;
                }

                break;

            case DOKU_LEXER_EXIT:
                $renderer->doc.= '</a>';
                break;
        }
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

    /**
     * JavaScript to open a new window, executed as onclick event
     */
    protected function _window_open($opts) {

        if (array_key_exists('width',  $opts)) $win['width']  = $opts['width'];
        if (array_key_exists('height', $opts)) $win['height'] = $opts['height'];
        $win['resizeable'] = array_key_exists('resizeable', $opts) ? $opts['resizeable'] : 1;
        $win['location']   = array_key_exists('location', $opts) ? $opts['location'] : 1;
        $win['status']     = array_key_exists('status', $opts) ? $opts['status'] : 1;
        $win['titlebar']   = array_key_exists('titlebar', $opts) ? $opts['titlebar'] : 1;
        $win['menubar']    = array_key_exists('menubar', $opts) ? $opts['menubar'] : 0;
        $win['toolbar']    = array_key_exists('toolbar', $opts) ? $opts['toolbar'] : 0;
        $win['scrollbars'] = array_key_exists('scrollbars', $opts) ? $opts['scrollbars'] : 1;

        foreach ($win as $key => $value) { $spec.= $key.'='.$value.','; }
        $spec = rtrim($spec, ',');

        $js = "javascript:void window.open(this.href,'_blank','".$spec."'); return false;";
        return $js;
    }

    // copy from select2go plugin
    /* ---------------------------------------------------------
     * get each named/non-named arguments as array variable
     *
     * Named arguments is to be given as key="value" (quoted).
     * Non-named arguments is assumed as boolean.
     *
     * @param string $args   arguments
     * @return array     parsed arguments in $arg['key']=value
     * ---------------------------------------------------------
     */
    protected function getArguments($args='') {
        $arg = array();
        if (empty($args)) return $arg;

        // get named arguments (key="value"), ex: width="100"
        // value must be quoted in argument string.
        $val = "([\"'`])(?:[^\\\\\"'`]|\\\\.)*\g{-1}";
        $pattern = "/\b(\w+)=($val) ?/";
        preg_match_all($pattern, $args, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $arg[$match[1]] = substr($match[2], 1, -1); // drop quates from value string
            $args = str_replace($match[0], '', $args); // remove parsed substring
        }

        // get named numeric value argument, ex width=100
        // numeric value may not be quoted in argument string.
        $val = '\d+';
        $pattern = "/\b(\w+)=($val) ?/";
        preg_match_all($pattern, $args, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $arg[$match[1]] = (int)$match[2];
            $args = str_replace($match[0], '', $args); // remove parsed substring
        }

        // get non-named arguments
        $tokens = preg_split('/\s+/', $args);
        foreach ($tokens as $token) {

            // get size parameters specified as non-named arguments
            // assume as single size or eles width and height pair
            //  ex: 85% |  256x256 | 800,600px | 85%,300px
            $pattern = '/(\d+(\%|em|pt|px)?)(?:[,xX]?(\d+(\%|em|pt|px)?))?$/';
            if (preg_match($pattern, $token, $matches)) {
                //error_log('helper matches: '.count($matches).' '.var_export($matches, 1));
                if ((count($matches) > 4) && empty($matches[2])) {
                    $matches[2] = $matches[4];
                    $matches[1] = $matches[1].$matches[4];
                }
                if (count($matches) > 3) {
                    $arg['width']  = $matches[1];
                    $arg['height'] = $matches[3];
                } else {
                    $arg['size'] = $matches[1];
                }
            }

            // get flags, ex: showdate, noshowfooter
            if (preg_match('/^(?:!|not?)(.+)/',$token, $matches)) {
                // denyed/negative prefixed token
                $arg[$matches[1]] = false;
            } elseif (preg_match('/^[A-Za-z]/',$token)) {
                $arg[$token] = true;
            }
        }
        return $arg;
    }

}
