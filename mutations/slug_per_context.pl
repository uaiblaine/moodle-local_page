# slug_per_context: drop the contextid clause from is_taken(), which is the state this stage found
# the query in. Uniqueness goes back to being site-wide, so one category claiming "contato" stops
# every other category — and the site itself — from having a page of that name.
s{        \$select = 'menuname = :menuname AND deleted = 0 AND contextid = :contextid';\n        \$params = \['menuname' => \$menuname, 'contextid' => \$contextid\];}{        \$select = 'menuname = :menuname AND deleted = 0';\n        \$params = ['menuname' => \$menuname];}s;
