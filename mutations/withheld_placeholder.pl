# withheld_placeholder: render the row's own "no access" page for a category page withheld from a
# logged-in user. Nothing becomes readable; what changes is that the row's category is set on $PAGE,
# so a hidden category's id, a draft's existence and its category reach the body classes, where a
# missing page answers the placeholder in the system context.
s{        // A user they withhold it from meets the missing page's placeholder[^\n]*\n        if \(!\$canview\) \{\n            return self::answer\(custompage::load\(0\), false, \$url\);\n        \}\n\n}{}s;
