<?php
/**
 * DokuWiki Plugin HyperLink; Syntax external
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * Supersede DokuWiki's connect patterns for
 * simplified urls those start '/\b(www|ftp)\./i'
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_hyperlink_external extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattrens;

    function __construct() {
        $this->mode = substr(get_class($this), 7);

        // url vailidation, ignore query and fragment component
        $domain = '(?:[a-zA-Z0-9][a-zA-Z0-9\-]{0,62}[A-Za-z0-9]\.)+(?:[A-Za-z]+)';
        $port   = '(?::\d+)?';
        $path   = '(?:\/[\w\.\-]+)*\/?';

        $this->patterns[] = '\b(?i)www?(?-i)\.'.$domain.$port.$path;
        $this->patterns[] = '\b(?i)ftp?(?-i)\.'.$domain.$path;
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
            $url   = 'ftp://'. $url;
        }
        if (substr($url,0,4) == 'www.') {
            $title = $match;
            $url = 'http://'. $url;
        }

        $handler->_addCall('externallink',array($url, $title), $pos);

        return false; // do not call $this->render()
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {
        return true;
    }

}
