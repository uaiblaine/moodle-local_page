# delete_url_context: drop the contextid from a category card's delete link. pages.php then
# resolves the system context from the URL, checks local/page:addpages there and refuses a
# category manager outright — and refuses even an administrator at the row comparison, because the
# row does not belong to the context the screen was authorised for.
s{            \$deleteparams\['contextid'\] = \(int\) \$context->id;\n}{};
