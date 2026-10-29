# bin_erase_files: permanent deletion leaves the page's files behind as orphans.
s{                \$fs->delete_area_files\(\$context->id, 'local_page', \$filearea, \$pageid\);\n}{};
