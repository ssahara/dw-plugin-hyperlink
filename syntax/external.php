<?php
/**
 * DokuWiki Plugin HyperLink; Syntax external
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * Supersede DokuWiki's connect patterns for automagical external urls
 * those are rendered as links without any special markups.
 * Hyperlink syntax plugin must handle them that may appear after "|"
 * as a part of link text, for example "[[id|settings of example.com]]".
 *
 * It is necessary to modify the pattern for automagical urls so that
 * such urls should not take away a vital part of hyperlink brackets "]]"
 * when they are used in link text of hyperlink syntax.
 *
 * The pattern for automagical external urls needs to be modified anyway,
 * it is extended to support IDN (Internationalized Domain Name).
 * eg. 1) [[http://xn--wgv71a119e.jp|日本語.jp]]
 *     2) http://日本語.jp
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_hyperlink_external extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattrens;

    function __construct() {
        $this->mode = substr(get_class($this), 7);

        // simplified urls (schema component omitted)
        // url vailidation preferable, ignore query and fragment components
        $domain = '(?:[a-zA-Z0-9][a-zA-Z0-9\-]{0,62}[A-Za-z0-9]\.)+(?:[A-Za-z]+)';
        $port   = '(?::\d+)?';
        $path   = '(?:\/[\w\.\-]+)*\/?';
        //$rest   = '(?:\S*(?=]]))?';

        $this->patterns[] = '\b(?i)www?(?-i)\.'.$domain.$port.$path;
        $this->patterns[] = '\b(?i)ftp?(?-i)\.'.$domain.$path;

        // external urls, supportng IDN
        $schemes = getSchemes();
        foreach ( $schemes as $scheme ) {
            // url match should not go beyond "<", "]]" and "}}]"
            $this->patterns[] = '\b(?i)'.$scheme.'(?-i)://'.'\S+?(?=<|>)';
            $this->patterns[] = '\b(?i)'.$scheme.'(?-i)://'.'\S+?(?=]]|}})';
            $this->patterns[] = '\b(?i)'.$scheme.'(?-i)://'.'\S+';
        }
    }

    function getType()  { return 'substition'; }
    function getPType() { return 'normal'; }
    function getSort()  { return 329; } // < Doku_Parser_Mode_externallink(=330)

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        foreach ( $this->patterns as $pattern ) {
            $this->Lexer->addSpecialPattern($pattern, $mode, $this->mode);
        }
    }

    /**
     * handle syntax
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        $url = strtolower($match);

        // add protocol on simple short URLs
        if (substr($url,0,4) == 'ftp.') {
            $title = $match;
            $url = 'ftp://'. $url;
        }
        if (substr($url,0,4) == 'www.') {
            $title = $match;
            $url = 'http://'. $url;
        }

        // check seriously malformed URLs
        if (parse_url($url) === false) {
            $handler->_addCall('cdata', array($match), $pos);
        } else {
            $handler->_addCall('externallink',array($url, $title), $pos);
        }

        return false; // do not call $this->render()
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {
        return true;
    }

}
