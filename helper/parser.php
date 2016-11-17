<?php
/**
 * DokuWiki Plugin HyperLink; helper component
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Satoshi Sahara <sahara.satoshi@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_hyperlink_parser extends DokuWiki_Plugin {

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
    function getArguments($args='') {
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
