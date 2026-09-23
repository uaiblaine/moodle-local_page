# delete_releases_slugs: lifecycle::category_deleted() marks the page deleted but keeps its slug, so
# the row no longer carries the -deleted-<id> shape every deleted page has.
s{'menuname' => slug::deleted_name\(\(string\) \$row->menuname, \$pageid\),}{'menuname' => (string) \$row->menuname,};
