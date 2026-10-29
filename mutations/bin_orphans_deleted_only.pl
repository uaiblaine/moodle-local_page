# bin_orphans_deleted_only: a live row under a missing context is deleted too.
s{              WHERE p.deleted = 1 AND p.contextid <> 0 AND c.id IS NULL}{              WHERE p.contextid <> 0 AND c.id IS NULL};
