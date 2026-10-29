# orphan_recordset: the areas are read keyed by their first column, so areas of one context overwrite each other.
s{        \$rows = \$DB->get_recordset_sql\(}{        \$rows = \$DB->get_records_sql(};
s{        \$rows->close\(\);\n}{};
