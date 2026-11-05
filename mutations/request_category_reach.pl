# request_category_reach: drop the placeholder a logged-in user gets for a category withheld from them.
# The read rule still refuses the page, so nothing is readable; what changes is the answer: the row's
# "no access" page in the hidden category, whose id then reaches $PAGE and the body classes, where a
# missing category answers the placeholder in the system context.
s{        // A logged-in user the site would not name the category to[^\n]*\n        if \(\$context && !\$isvisitor && !categoryaccess::is_reachable\(\$categoryid\)\) \{\n            return self::answer\(custompage::load\(0\), false, \$url\);\n        \}\n\n}{}s;
