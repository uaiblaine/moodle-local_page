# publish_gate_context: check the publishing capability at the SYSTEM context instead of the one
# the page is being saved into. The gate then answers the wrong question in both directions: a
# category's own publisher is refused in their own category, and anybody holding the capability
# site-wide publishes in every category including those they have nothing to do with.
s{has_capability\('local/page:publishcategorypages', \$context\)}{has_capability('local/page:publishcategorypages', context_system::instance())};
