# ogimage_gate: delete the servability call from the ogimage branch of local_page_pluginfile(),
# leaving the area world-readable by itemid again — the state this stage found it in.
#
# Anchored on the two lines of the guard itself, not on the branch around it, so the mutation
# survives anything else moving inside that branch and fails loudly if the guard is rewritten.
s{        \$ogimagepage = \$DB->get_record\('local_page', \['id' => \(int\) \$itemid\]\);\n        if \(!\$ogimagepage \|\| !local_page_ogimage_is_servable\(\$ogimagepage\)\) \{\n            return false;\n        \}\n\n}{}s;
