# viewer_category_reach: drop the logged-in category clause from local_page_user_can_view_page(). The
# request class still answers the placeholder at the category addresses, so what changes is the ?id=
# address and the file route: a hidden or unlisted category's pages and embedded files reach every
# logged-in user again.
s{    // A logged-in user reads it only where the site would name its category to them[^\n]*\n    if \(\n        \$context->contextlevel == CONTEXT_COURSECAT\n        && !\\local_page\\local\\publicaccess::is_visitor\(\)\n        && !\\local_page\\local\\categoryaccess::is_reachable\(\(int\) \$context->instanceid\)\n    \) \{\n        return false;\n    \}\n\n}{}s;
