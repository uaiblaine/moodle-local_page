# prune_page_address: the page's own file address in contenthtml no longer keeps a file.
s{        if \(\$pageid > 0\) \{\n            \$prefixes\[\] = [^\n]+\n        \}\n}{};
