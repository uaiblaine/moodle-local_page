# nav_capability: drop the capability check from the category settings callback, so the node is
# offered in every category to everybody who can open a category page at all. The link then leads
# to a screen that refuses them — an invitation to a locked door — and, worse, it says the plugin
# has pages here to anybody who looks at the menu.
s{    if \(!has_capability\(\\local_page\\local\\scope::capability\(\$context\), \$context\)\) \{\n        return;\n    \}\n\n}{};
