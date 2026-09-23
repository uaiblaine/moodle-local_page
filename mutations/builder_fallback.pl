# builder_fallback: spell the routed address even when the site's router is not configured. Core then
# answers under /r.php/, and on a server that does not hand unknown paths to r.php the address the
# page, wantsurl and the canonical tag carry answers nothing at all.
s{        if \(!self::routing_enabled\(\)\) \{\n            return self::legacy_category\(\$categoryid, \$slug\);\n        \}\n\n}{}s;
