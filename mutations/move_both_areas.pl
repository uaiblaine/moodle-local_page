# move_both_areas: lifecycle::category_moved() moves the embedded files only, so the Open Graph image
# is purged with the old context.
s{private const FILEAREAS = \['pagecontent', 'ogimage'\];}{private const FILEAREAS = ['pagecontent'];};
