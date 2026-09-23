# publish_gate: make local_page_apply_publish_gate() hand the record back untouched, so the posted
# value decides. Publishing to visitors who are not logged in stops being a capability of its own
# and becomes something every category author has, silently — the form still freezes the field,
# which is exactly why the enforcement cannot live there.
s{    \$record->onlyloggedin = 1;\n\n    return \$record;}{    return \$record;}s;
