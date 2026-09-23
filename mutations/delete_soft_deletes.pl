# delete_soft_deletes: lifecycle::category_deleted() stops marking the category's pages deleted. The
# rows stay live in a context core is about to delete, pointing at a context that no longer exists.
s{                    'deleted' => 1,\n                    'menuname' => slug::deleted_name}{                    'deleted' => 0,\n                    'menuname' => slug::deleted_name};
