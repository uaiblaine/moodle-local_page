# slug_released: make deleted_name() hand back the slug it was given, so deleting a page keeps
# holding the address for ever and normalise_all() stops mangling deleted rows.
#
# Anchored on the two returns that build the mangled value, not on the whole method, so the empty
# slug naming above them still runs and the mutation is only about the release.
s{        if \(self::ends_with\(\$base, \$suffix\)\) \{\n            return self::truncate\(\$base\);\n        \}\n\n        return self::stem\(\$base, \$suffix\) \. \$suffix;}{        return self::truncate(\$base);}s;
