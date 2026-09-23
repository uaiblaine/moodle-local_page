# card_friendlyurl_escaped: print the card's friendly URL through a triple stash again, as upstream did.
# Harmless while the block only held wwwroot/slug; a category page's script address carries a query
# string, and its ampersand then reaches the page unescaped.
s|<pre class="custompage-url mb-2">\{\{friendlyurl\}\}</pre>|<pre class="custompage-url mb-2">{{{friendlyurl}}}</pre>|s;
