# ogimage_ignores_status: make the predicate answer true for any row that is not deleted, which is
# the mistake a reader of the docblock is most likely to make — "it ignores the viewer" read as
# "it ignores everything". Draft, archived, expired and not-yet-started images would all be public.
#
# Anchored on the status test and the window return together: replacing only one of them leaves
# the other standing and the mutation would not express the claim.
s{    if \(\(\$page->status \?\? ''\) !== 'live'\) \{\n        return false;\n    \}\n\n    return local_page_publish_window_is_open\(\$page, time\(\)\);}{    return true;}s;
