# delete_forgets_codes: lifecycle::category_deleted() leaves the pages' public short codes in core's
# table, which core never cleans on its own.
s{\n            links::forget\(\$pageid\);\n}{\n};
