# normalise_counter: the counter never goes past 2. A page whose -<id> form is taken moves to
# -<id>-2 without that form being checked, duplicating a page that already holds it.
s~        for \(\$counter = 2; \$istaken\(\$candidate\); \$counter\+\+\) \{\n            \$suffix = \$idsuffix \. '-' \. \$counter;~        if (\$istaken(\$candidate)) {\n            \$suffix = \$idsuffix . '-2';~;
