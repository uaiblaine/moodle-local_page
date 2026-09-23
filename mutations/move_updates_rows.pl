# move_updates_rows: lifecycle::category_moved() moves the files but never re-points the rows, which
# keep naming the old context after core has deleted it.
s{\n                \$DB->update_record\('local_page', \(object\) \[\n                    'id' => \$pageid,\n                    'contextid' => \$newcontextid,\n                    'categoryid' => \$newparentid,\n                \]\);\n}{\n};
