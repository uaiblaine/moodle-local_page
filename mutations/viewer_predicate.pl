# viewer_predicate: drop the public-category clause from local_page_user_can_view_page(). The viewer's
# request class still refuses a visitor at the page's address, so nothing a browser sees at that
# address changes — what changes is the file route, which asks this function alone and would serve a
# private category's embedded files to anybody.
s{    // A category page reaches a visitor only when its category is public[^\n]*\n    if \(\n        \$context->contextlevel == CONTEXT_COURSECAT\n        && \\local_page\\local\\publicaccess::is_visitor\(\)\n        && !\\local_page\\local\\publicaccess::is_public\(\(int\) \$context->instanceid, \$predicate\)\n    \) \{\n        return false;\n    \}\n\n}{}s;
