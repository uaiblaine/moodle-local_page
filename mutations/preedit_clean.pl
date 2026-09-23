# preedit_clean: make local_page_editable_content() hand a category page's stored text straight
# back, which is the half of core's trusttext contract that is easiest to leave out. An untrusted
# editor opening a page a trusted colleague wrote then receives the script in their form and
# re-saves it under their own flag — the content crosses the trust boundary while every render
# still looks correct.
#
# Anchored on the early return for a site-wide page together with its body, because the same
# is_category() test opens local_page_render_content() a few lines above.
s{    if \(!\\local_page\\local\\scope::is_category\(\$page\)\) \{\n        return \$text;\n    \}}{    if (true) {\n        return \$text;\n    }}s;
