# slug_unique_counter: the shared collision rule stops at the -<id> form even when that is taken too,
# and hands out a duplicate. normalise_all() and unique_in_context() both go through it.
s{        for \(\$counter = 2; \$istaken\(\$candidate\); \$counter\+\+\) \{\n(?:[^\n]*\n){2}        \}\n}{};
