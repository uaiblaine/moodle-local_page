# guard_order: move the visitor's refusal in request::category() from before the lookup to after it.
# The answers a visitor gets stay the same — a login redirect for a private category, for a missing
# one, for a page that is not there — which is exactly why this reordering is dangerous: it reads as
# harmless, and what it changes is that an anonymous request now reaches the database before it is
# refused. The test that holds the order measures that: zero statements on the refusal path.
s{        // Step 1: the visitor's one refusal[^\n]*\n        if \(\$isvisitor && !publicaccess::is_public\(\$categoryid, \$predicate\)\) \{\n            return self::refuse\(\$url\);\n        \}\n\n}{}s;
s{\n(        if \(\$page === null \|\| \(int\) \$page->id <= 0\) \{\n)}{\n        if (\$isvisitor && !publicaccess::is_public(\$categoryid, \$predicate)) {\n            return self::refuse(\$url);\n        }\n\n$1}s;
