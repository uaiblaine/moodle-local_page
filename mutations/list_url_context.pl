# list_url_context: drop the contextid parameter from the listing address, which is what three
# separate screens use to stay inside the category they were authorised in. The settings node, the
# back link and the cancel redirect then all land on the site-wide listing, where pages.php checks
# local/page:addpages and refuses a category author.
s{        \$params\['contextid'\] = \(int\) \$context->id;\n}{};
