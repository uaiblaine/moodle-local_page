# hidetitle_fill: the hidetitle repair makes the column NOT NULL without filling the NULLs first.
# PostgreSQL refuses the change while a row holds NULL; MySQL and MariaDB either refuse it or turn
# the NULL into an empty string, which is not the 'no' the column declares.
s{    \$DB->set_field_select\('local_page', 'hidetitle', 'no', 'hidetitle IS NULL'\);\n}{};
