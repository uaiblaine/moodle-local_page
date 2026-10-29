# pluginfile_context_match: drop the contextid clause from the pagecontent row lookup, so the row is
# found by item id alone. Any context would then authorise, and serve, a file stored under that item
# id - including one belonging to a page of another context.
#
# The deleted = 0 half is left in place: this gate is about the context.
s{            'id' => \$itemid,\n            'contextid' => \\local_page\\local\\scope::stored_contextid\(\$context\),\n            'deleted' => 0,\n}{            'id' => \$itemid,\n            'deleted' => 0,\n};
