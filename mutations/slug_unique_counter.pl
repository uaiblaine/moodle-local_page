# slug_unique_counter: slug::unique_in_context() stops at the -<id> form even when that is taken too,
# and hands out a duplicate.
s{        for \(\$counter = 2; self::is_taken\(\$candidate, \$id, \$contextid\); \$counter\+\+\) \{\n(?:[^\n]*\n){2}        \}\n}{};
