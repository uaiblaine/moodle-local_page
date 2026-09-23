# delete_own_context_only: lifecycle::category_deleted() drops the context filter and soft-deletes
# every page of the site, the sibling category's and the site-wide ones included.
s{\$rows = \$DB->get_records\('local_page', \['contextid' => \(int\) \$context->id\], 'id ASC'}{\$rows = \$DB->get_records('local_page', null, 'id ASC'};
