# robots_category_default: drop the noindex a category page gets on a site closed to search engines.
# Every category page would then be indexable by default, which is not a decision its author made.
s{        if \(scope::is_category\(\$page\) && empty\(\$CFG->opentowebcrawlers\)\) \{\n            return 'noindex';\n        \}\n\n}{}s;
