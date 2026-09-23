# context_switch_ignored: make local_page_save_target_context() take the context from the POSTED
# field even when the page already exists, which is what renderer::save_page() then writes. A page
# could be moved from the site scope into a category, or between categories, by editing a hidden
# input — into a context whose capability was never checked, and out of the reach of the one that
# was.
#
# Anchored on the early return for an existing row, so the new-page branch below it still answers
# and the mutation expresses exactly one claim.
s{    if \(\$editable !== null\) \{\n        return \\local_page\\local\\scope::context\(\$editable\);\n    \}\n\n}{}s;
