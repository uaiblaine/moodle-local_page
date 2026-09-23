# og_locale_case: return Moodle's language code unchanged. pt_br is not an Open Graph locale; pt_BR is.
s{            return \$matches\[1\] \. '_' \. strtoupper\(\$matches\[2\]\);}{            return \$language;}s;
