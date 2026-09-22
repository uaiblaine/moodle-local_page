# head_system_only: let local_page_head_html() emit the stored <head> HTML of a category page too.
# There is no sanitiser for head markup — that is the whole reason the field is system scope — so
# this hands a delegated category author a script element, a meta refresh or a base tag in the
# document head of a public page.
s{    if \(\\local_page\\local\\scope::is_category\(\$page\)\) \{\n        return '';\n    \}\n\n}{}s;
