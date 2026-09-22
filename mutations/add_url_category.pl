# add_url_category: drop the category from the editor address of a NEW page. The "Add New Page"
# button on a category's listing then opens the site-wide editor, which checks local/page:addpages
# and refuses the very author whose own screen offered the button.
s{        \$params\['category'\] = \(int\) \$context->instanceid;\n}{};
