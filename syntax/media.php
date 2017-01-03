<?php
/**
 * DokuWiki Plugin HyperLink; Syntax media
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * Supersede DokuWiki's connect patterns for media
 *
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_hyperlink_media extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattrens;

    function __construct() {
        $this->mode = substr(get_class($this), 7);

        // @see class Doku_Parser_Mode_media in inc/parser/parser.php
        //$this->patterns[] = "\{\{[^\}]+\}\}";
        //$this->patterns[] = '\{\{.+?\}\}';

        // ignore single "}" and check "}}" allowing nested "{...}"
        $nest = str_repeat('(?>[^\{\}\n]+|\{', 3).str_repeat('\})*', 3);
        $this->patterns[] = '\{\{'.$nest.'\}\}';
    }

    function getType()  { return 'substition'; }
    function getPType() { return 'normal'; }
    function getSort()  { return 319; } // < Doku_Parser_Mode_media(=320)

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

        $handler->media($match, $state, $pos);
        return false; // do not call $this->render()
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {
        return true;
    }

}
