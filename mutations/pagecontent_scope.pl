# pagecontent_scope: drop the context clause from the shared site-wide pagecontent search, so the
# content of ANY live page authorises a file of that area — a category page's content included.
# The pages of a category are written by whoever holds local/page:managecategorypages there, so
# with the clause gone one of them names a site-wide file in their own page and it is their own
# page's viewability that decides whether the file is served, instead of the state of the page the
# file belongs to.
#
# Like the other pluginfile gates, the test passes ['dontdie' => true]: with the guard gone the
# call falls through to send_stored_file(), and a die inside PHPUnit kills the run before any
# verdict line is printed, which mdl-mutate reads as "the suite never ran" rather than as a red.
s{    \$params\['ctx'\] = .*\n    \$sql = "SELECT \* FROM \{local_page\} WHERE deleted = 0 AND contextid = :ctx AND \(}{    \$sql = "SELECT * FROM {local_page} WHERE deleted = 0 AND (};
