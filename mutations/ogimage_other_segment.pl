# ogimage_other_segment: stop refusing a path segment that is not a content hash, so the image route
# answers any number of spellings of one address again instead of the two it hands out.
s{        if \(count\(\$args\) > 1 \|\| \(\$args && !preg_match\('/\^\[0-9a-f\]\{40\}\$/', \(string\) reset\(\$args\)\)\)\) \{\n            return false;\n        \}\n\n}{}s;
