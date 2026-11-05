# reach_fail_open: refuse every category where local_unlistedcourses is missing. That is every CI
# leg, so category pages would stop reaching logged-in users on exactly the sites that never
# unlisted anything.
s{(if \(!class_exists\(\$predicate\)\) \{\n            return )true;}{${1}false;}s;
