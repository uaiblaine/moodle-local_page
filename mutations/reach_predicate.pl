# reach_predicate: ignore local_unlistedcourses' answer, so an unlisted category reaches every
# logged-in user, which is the unlisted half of the finding.
s{return \(bool\) \$predicate::is_category_discoverable\(\$categoryid\);}{return true;}s;
