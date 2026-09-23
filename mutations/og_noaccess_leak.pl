# og_noaccess_leak: register the head tags whatever the viewer may read. The title, description, image
# and canonical address of a draft would then reach the head of the "no access" page a visitor gets.
s{opengraph::set\(\$canview \? \\local_page\\output\\opengraph::for_page\(\$custompage, \$canonicalurl\) : null\);}{opengraph::set(\\local_page\\output\\opengraph::for_page(\$custompage, \$canonicalurl));}s;
