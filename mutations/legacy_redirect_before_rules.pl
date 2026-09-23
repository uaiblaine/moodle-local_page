# legacy_redirect_before_rules: issue request::legacy()'s 303 to the routed address before the page's
# own rules and the public predicate have answered. The redirect spells the page's category and slug,
# so a visitor of a private category asking for ?id=N would learn what N names instead of meeting the
# login refusal.
s{        // Only now, with the rules and the predicate answered: [^\n]*\n        if \(\$iscategory && \$canview && links::routing_enabled\(\)\) \{\n            return self::moved\(links::page\(\$page\)\);\n        \}\n\n}{}s;
s{(        \$canview = local_page_user_can_view_page\(\$page, \$predicate\);\n        \$iscategory = )}{        if ((int) \$page->id > 0 && scope::is_category(\$page) && links::routing_enabled()) {\n            return self::moved(links::page(\$page));\n        }\n$1}s;
