# request_legacy_reach: the same placeholder dropped from the ?id= address, which renders the row's
# "no access" page in the withheld category instead.
s{        if \(\$iscategory && !\$canview && !categoryaccess::is_reachable\(\(int\) scope::context\(\$page\)->instanceid\)\) \{\n            return self::answer\(custompage::load\(0\), false, \$url\);\n        \}\n}{}s;
