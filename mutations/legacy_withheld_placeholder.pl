# legacy_withheld_placeholder: the same placeholder dropped from the ?id= address, which then renders
# the row's "no access" page in the withheld page's category.
s{        if \(\$iscategory && !\$canview\) \{\n            return self::answer\(custompage::load\(0\), false, \$url\);\n        \}\n}{}s;
