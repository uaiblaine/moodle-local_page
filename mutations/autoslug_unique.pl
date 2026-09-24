# autoslug_unique: a page saved with no slug is named page-<id> without asking whether another live
# page of its context already holds that name, so two live pages end up at one address.
s{\$autoslug = \\local_page\\local\\slug::unique_in_context\(\n(?:[^\n]*\n){3}\s*\);}{\$autoslug = 'page-' . (int) \$result;};
