# editable_recheck: drop the deleted filter from the row lookup, so a soft-deleted page can be
# opened in the editor and saved back into existence. The row still has to exist, so the "missing
# id" half of the guard keeps working — only the half about deletion goes.
s{\$row = \$DB->get_record\('local_page', \['id' => \$pageid, 'deleted' => 0\]\);}{\$row = \$DB->get_record('local_page', ['id' => \$pageid]);};
