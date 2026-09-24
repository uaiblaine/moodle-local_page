# idsuffix_once: a slug that already ends in the page's id is given a second copy of it, so
# page-12 of page 12 moves to page-12-12 rather than page-12-2.
s{        if \(self::ends_with\(\$root, \$idsuffix\)\) \{\n            \$root = [^\n]*\n        \}\n}{};
