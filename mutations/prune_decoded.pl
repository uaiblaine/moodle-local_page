# prune_decoded: the fields are no longer read URL-decoded, so an embedded H5P package's file is pruned.
s{\$hays = \[\$raw, rawurldecode\(\$raw\), }{\$hays = [\$raw, };
