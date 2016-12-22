Hyper Link Syntax plugin
=========================
Extend DokuWiki Link syntax to 
* allow formatting title text of the link
* specify a frame where the linked page/media to be opened, eg. in a same tab, new tab or popup window

Note: When link text contains external URLs, they should be treated as normal text to avoid “link in link” occurrence. Therefore, syntax of automagical link patterns for external URLs (and simplified URLs) has modified slightly from original DokuWiki syntax. 

Usage/Example
--------------------
Hyperlink plugin supersedes the DokuWiki built-in syntax of creating links – double brackets `[[...]]` – to enable additonal feature. It however should be compatible with original syntax usage...

### Formatting the link text###
This plugin has initially developed to implement formatting in link text. You may use **bold**, *italic*, <sup>superscript</sup> and <sub>subscript</sub> text in links, for instance use a chemical formula <a href="https://en.wikipedia.org/wiki/Ferrocyanide" title="Ferrocyanide" rel="nofollow" target="_blank">[Fe<sup>II</sup>(CN)<sub>6</sub>]<sup>4-</sup></a> as anchor text.

[Text conversion](https://www.dokuwiki.org/wiki:syntax#text_conversions) and [smiileys](https://www.dokuwiki.org/smileys) are available in the link text.

```
[[ns:page|**internal** page]]                      internal page
[[http://example.com|**external** link title]]     external url
[[doku>interwiki|//interwiki// title]]             interwiki
[[foo@example.com|contact **me**! :-)]]            mail link
```


### Specify how the linked page will be opened ###

```
[[ns:page target="_blank" |**internal** link title]]    open in a new tab (or window)
[[ns:page target="_self"  |**internal** link title]]    open in same tab
[[https://example.com target="window 600x400" |title]]  open in new window
```

----
Licensed under the GNU Public License (GPL) version 2


(c) 2015-2016 Satoshi Sahara \<sahara.satoshi@gmail.com>
