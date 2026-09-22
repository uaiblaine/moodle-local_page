# ogimage_context_match: drop the comparison between the page's own context and the context the
# file was requested through. Page ids are unique across the whole table, so without it the item id
# alone decides: a category context would serve a site-wide page's og:image, and the system context
# a category page's, whenever a file happens to sit under that id.
#
# The publication gate above it is a separate claim, held by ogimage_gate.
s{        if \(\(int\) \\local_page\\local\\scope::context\(\$ogimagepage\)->id !== \(int\) \$context->id\) \{\n            return false;\n        \}\n\n}{}s;
