# bin_restore_unique: a restored page takes back its old slug even when a live page holds it now.
s{            \$menuname = slug::unique_in_context\(\$wanted, \$pageid, \$contextid\);}{            \$menuname = \$wanted;};
