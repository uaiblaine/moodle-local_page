# normalise_recheck: normalise_all() takes the first form a moved page could have without asking
# whether another page holds it. A page moved onto the -<id> form a lower id already holds becomes
# a second live page at that address, and a second run keeps it there.
s{static fn \(string \$candidate\): bool => isset\(\$taken\[\$scope\]\[\$candidate\]\) \|\| isset\(\$held\[\$scope\]\[\$candidate\]\)}{static fn (string \$candidate): bool => false};
