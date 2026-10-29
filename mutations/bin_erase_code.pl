# bin_erase_code: permanent deletion leaves the page's /p/ code in core's table.
s{        links::forget\(\$pageid\);\n        \$DB->delete_records}{        \$DB->delete_records};
