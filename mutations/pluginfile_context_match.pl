# pluginfile_context_match: drop the contextid clause from the category pagecontent lookup, so the
# row is found by item id alone. Any category context would then authorise, and serve, a file
# stored under that item id — including one belonging to a page of a different category.
#
# The deleted = 0 half is left in place: this gate is about the context, and the deletion half is
# asserted by the same test separately.
s{                'id' => \$itemid,\n                'contextid' => \$context->id,\n                'deleted' => 0,\n}{                'id' => \$itemid,\n                'deleted' => 0,\n};
