# Drop the visitor's refusal from the top of catalogue::for_viewer(): the list a visitor gets stays
# empty for a private category (the read-side rule refuses each row), but the anonymous request now
# reaches the database first, doing work that differs between ids that exist and ids that do not.
s{        // Step 1: the visitor's refusal[^\n]*\n        if \(publicaccess::is_visitor\(\) && !publicaccess::is_public\(\$categoryid, \$predicate\)\) \{\n            return \[\];\n        \}\n\n}{}s;
