# stamp_only_unstamped: the upgrade restamps every deleted page, a time already recorded included.
s{    \$DB->set_field\('local_page', 'timedeleted', \$now, \['deleted' => 1, 'timedeleted' => 0\]\);}{    \$DB->set_field('local_page', 'timedeleted', \$now, ['deleted' => 1]);};
