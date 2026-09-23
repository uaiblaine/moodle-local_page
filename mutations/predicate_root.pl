# predicate_root: drop the refusal of ids that no category can have, so the root and negative ids are
# handed to the predicate as though they could be public.
s{        // A page never belongs to the root[^\n]*\n        if \(\$categoryid <= 0\) \{\n            return false;\n        \}\n\n}{}s;
