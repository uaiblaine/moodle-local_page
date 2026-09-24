# normalise_held: normalise_all() only avoids the forms it has handed out earlier in the pass, not
# the ones later pages already hold, so a moved page takes a later page's slug and pushes that
# page along in turn.
s{ \|\| isset\(\$held\[\$scope\]\[\$candidate\]\)}{};
