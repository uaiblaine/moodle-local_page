# split_idempotent: an existing copy is no longer skipped.
s{            if \(\$fs->file_exists\(\$system->id, 'local_page', 'pagecontent', \$pageid, \$file->get_filepath\(\), \$file->get_filename\(\)\)\) \{\n                continue;\n            \}\n}{};
