<?php
/**
 * DokuWiki Plugin HyperLink; helper component
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_hyperlink_window extends DokuWiki_Plugin {

    /**
     * JavaScript to open a new window, executed as onclick event
     */
    function window_open($opts) {

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

}
