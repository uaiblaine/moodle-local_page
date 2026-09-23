# legacy_slug_lookup: look the page up before the legacy script's slug form is moved to the route. The
# redirect stays the same for everybody, which is what makes this look harmless; what changes is that
# an anonymous request reaches the database before the route's guard order has refused it.
s{(        if \(\$legacy && \$slug !== '' && links::routing_enabled\(\)\) \{\n)}{$1            custompage::load_by_menuname(\$slug);\n}s;
