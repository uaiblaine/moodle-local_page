# delete_scope: make local_page_page_in_context() answer true for every row. The listing screen's
# delete action is authorised in one context and takes the page id from a link, so with the
# comparison gone a category manager could delete a site-wide page — or another category's — by
# typing its id into the URL.
s{    return \(int\) \(\$row->contextid \?\? 0\) === \\local_page\\local\\scope::stored_contextid\(\$context\);}{    return true;};
