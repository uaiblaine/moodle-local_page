# bin_orphans: pages whose context is gone are never deleted.
s{              WHERE p.deleted = 1 AND p.contextid <> 0 AND c.id IS NULL}{              WHERE p.deleted = 1 AND p.contextid <> 0 AND 1 = 0};
