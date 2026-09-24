# frozen_drift: the frozen upgrade copy of the slug normalisation skips its fourth pass, so it no
# longer separates duplicates while the class it was copied from still does.
s~        if \(isset\(\$taken\[\$scope\]\[\$wanted\[\$id\]\]\)\) \{\n            \$idsuffix~        if (false) {\n            \$idsuffix~;
