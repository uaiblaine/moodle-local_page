# nav_context: read the capability at the SYSTEM context instead of the category whose menu is
# being built. The delegation then means nothing in either direction: a manager of a category is
# offered no node in their own category, and anybody holding the capability site-wide is offered
# one in every category on the site.
s{has_capability\(\\local_page\\local\\scope::capability\(\$context\), \$context\)}{has_capability(\\local_page\\local\\scope::capability(\$context), \\core\\context\\system::instance())};
