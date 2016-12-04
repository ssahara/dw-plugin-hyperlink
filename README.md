Hyper Link Syntax plugin
=========================
Extend DokuWiki Link syntax to 
* allow formatting title text of the link
* specify a frame where the linked page/media to be opened, eg. in a same tab, new tab or popup window

Syntax Usage/Example
--------------------

### Formatting the link text###
You may use basic formatting for the link text. 
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
