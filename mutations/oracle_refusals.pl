# oracle_refusals: answer a visitor's request for a category that does not exist differently from one
# for a private category — an error instead of the login refusal. Every other answer stays the same,
# which is what makes this look like a harmless "better error"; what it does is tell an anonymous
# client which category ids exist.
s{(        if \(\$isvisitor && !publicaccess::is_public\(\$categoryid, \$predicate\)\) \{\n)(            return self::refuse\(\$url\);\n)}{$1            if (!\\core\\context\\coursecat::instance(\$categoryid, IGNORE_MISSING)) {\n                throw new \\moodle_exception('invalidcategoryid', 'error');\n            }\n$2}s;
