# slug_reserved: make is_reserved() answer false for everything, so a page may take an address
# Moodle itself answers on. Depending on how the site's rewrite rules are ordered the page then
# never answers, or it shadows part of Moodle — and neither is visible from the edit form.
#
# Anchored on the two tests that make the decision, leaving the empty-slug early return above them
# in place, so the mutation is the rule and not the whole method.
s{        if \(in_array\(\$menuname, self::RESERVED, true\)\) \{\n            return true;\n        \}\n\n        return preg_match\(self::RESERVED_PREFIX_PATTERN, \$menuname\) === 1;}{        return false;}s;
