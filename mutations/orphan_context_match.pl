# orphan_context_match: an area counts as owned whenever a page has its item id, in any context.
s{                AND \(p.contextid = f.contextid OR \(p.contextid = 0 AND f.contextid = :systemcontextid\)\)\n}{};
s{        \$params\['systemcontextid'\] = [^\n]+\n}{};
