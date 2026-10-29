# bin_delete_releases_slug: a deleted page keeps holding its friendly URL.
s{            'timedeleted' => time\(\),\n            'menuname' => slug::deleted_name\(\(string\) \$row->menuname, \$pageid\),}{            'timedeleted' => time(),\n            'menuname' => (string) \$row->menuname,};
