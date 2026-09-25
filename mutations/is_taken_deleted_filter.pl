# is_taken_deleted_filter: drop the deleted flag from is_taken(), so a deleted row still holding its
# slug keeps it taken. deleted_name() renames a row on delete, which hides this from every test that
# deletes through it; only a deleted row that kept its slug shows what the filter does.
s|'menuname = :menuname AND deleted = 0 AND contextid = :contextid'|'menuname = :menuname AND contextid = :contextid'|;
