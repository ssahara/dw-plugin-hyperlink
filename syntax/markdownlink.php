<?php
/**
 * DokuWiki Plugin HyperLink; Syntax markdownlink
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * Usage/Example:
 *    [link name](url "optional title")
 *    ![alt text](url "optional title")
 *
 *    [example](https://example.com "Example site")
 *      -> <a href="https://example.com" title="Example site">example</a>
 *
 *    ![example](https://example.com/image.png "picture")
 *      -> <img href="https://example.com/image.png" alt="example" title="picture"/>
 */

if(!defined('DOKU_INC')) die();

class syntax_plugin_hyperlink_markdownlink extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattern;
    protected $schemes = null;  // registered protocols in conf/schema.conf

    function __construct() {
        $this->mode = substr(get_class($this), 7);
        $this->pattern = '!?\[[^\r\n]+\]\([^\r\n]*?(?: ?"[^\r\n]*?")?\)';
    }

    function getType()  { return 'substition'; }
    function getPType() { return 'normal'; }
    function getSort()  { return 301; } // cf. Doku_Parser_Mode_internallink(=300)

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern($this->pattern, $mode, $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        $type = ($match[0] == '!') ? 'image' : 'link';
        if ($type == 'image') $match = ltrim($match,'!');

        $n = strpos($match, '](');
        $text = substr($match, 1, $n-1);
        $url = str_replace("\t",' ', trim(substr($match, $n+2, -1)) );

        // check title in string enclosed by double quaotation chars
        if (substr($url, -1) == '"') {
             $title = strstr($url, '"');
             $url = rtrim(str_replace($title, '', $url));
             $title = substr($title, 1, -1);
        } else {
            $title = '';
        }

        // use DokuWiki handler method for email address
        // eg. [send email](mailto:foo@example.com)
        if ($email = preg_replace('/^mailto:/','', $url) !== $url) {
            $handler->internallink($email.'|'.$text, $state, $pos);
            return false;
        }

        // check image mime type
        if ($type == 'image') {
            // remove url query and fragment component
            $src = strtolower($url);
            if (strpos($src,'?') !== false)
                $src = strstr($url, '?', true);
            if (strpos($src,'#') !== false)
                $src = strstr($src, '#', true);

            list($ext, $mime) = mimetype($src);
            if (substr($mime, 0, 6) != 'image/') {
                $type = 'link';
            }
        } else {
            $ext = false;
        }

        return array($state, $type, $ext, $text, $url, $title);
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {
        global $conf;

        if ($format == 'metadata') return false;

        list($state, $type, $ext, $text, $url, $title) = $data;

        // images
        if ($type == 'image') {
            if (empty($title)) $title = $url;
            $html = '<img src="'.$url.'" alt="'.hsc($text).'" title="'.hsc($title).'" />';
            $renderer->doc .= $html;
            return true;
        }


        //!!TEST!! allow formatting of anchor text
        $text = substr(p_render('xhtml', p_get_instructions($text), $info), 5, -6);
        //error_log('markdown link:'. $text); 

        // external url might be an attack vector, only allow registered protocols
        if (substr($url, 0, 1) !== '/') {
            if (is_null($this->schemes)) $this->schemes = getSchemes();
            //list($scheme) = explode('://', $url);
            //$scheme = strtolower($scheme);
            $scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
            if (!in_array($scheme, $this->schemes)) {
                if (empty($title)) $title = $url;
                $url = '';
            }
        }

        // abbreviation if url does not given
        if (empty($url)) {
            if (empty($title)) $title = $text;
            $renderer->doc .= '<abbr title="'.hsc($title).'">'.hsc($text).'</abbr>';
            return true;
        }

        // prepare link format
        $link = array();
        $link['target'] = $conf['target']['extern'];
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        $link['more']   = '';
        $link['class']  = '';
        $link['url']    = $url;

        $link['name']  = $text;
        $link['title'] = $title ?: $url;

        // file icon
        if ($ext) {
            $class = preg_replace('/[^_\-a-z0-9]+/i', '_', $ext);
            $link['class'] .= ' mediafile mf_'.$class;
        }

        if($conf['relnofollow']) $link['rel'] .= ' nofollow';
        if($conf['target']['extern']) $link['rel'] .= ' noopener';

        // output html of formatted link
        $renderer->doc .= $renderer->_formatLink($link);
        return true;
    }
}

