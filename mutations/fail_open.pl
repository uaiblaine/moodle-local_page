# fail_open: answer "public" when the predicate class is missing — local_unlistedcourses uninstalled,
# which is the state of every CI leg — so every category's pages go on the internet at once.
s{(if \(!class_exists\(\$predicate\)\) \{\n            // Fail closed: no predicate, no public category\.\n            return )false;}{${1}true;}s;
