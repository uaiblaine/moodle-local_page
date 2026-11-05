# reach_core_hidden: stop asking core whether the user may see the category, so a hidden one reaches
# every logged-in user.
s{        if \(\\core_course_category::get\(\$categoryid, IGNORE_MISSING\) === null\) \{\n            return false;\n        \}\n}{}s;
