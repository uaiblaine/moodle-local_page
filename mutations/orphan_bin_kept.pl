# orphan_bin_kept: a page in the recycle bin no longer owns its files, so the collector takes them.
s{                 ON p.id = f.itemid\n}{                 ON p.id = f.itemid AND p.deleted = 0\n};
