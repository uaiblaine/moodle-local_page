# reach_author: drop the author's exemption, so whoever manages a hidden category's pages without
# moodle/category:viewhiddencategories loses the preview the pages screen links to.
s{        if \(has_capability\(scope::capability\(\$context\), \$context\)\) \{\n            return true;\n        \}\n}{}s;
