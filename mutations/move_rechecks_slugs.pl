# move_rechecks_slugs: lifecycle::category_moved() carries each slug over unchecked, so a page arriving
# with a slug the new parent already holds duplicates it.
s{\n                if \(\(int\) \$row->deleted === 0\) \{\n                    \$menuname = slug::unique_in_context[^\n]*\n(?:[^\n]*\n){3}                \}\n}{\n};
