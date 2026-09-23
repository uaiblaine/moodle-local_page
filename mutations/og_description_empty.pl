# og_description_empty: hand the template an empty description instead of none, so a page without a
# meta description writes an empty og:description — worse for a preview card than no tag at all.
s{            \(\$description === ''\) \? null : \$description,\n}{            \$description,\n}s;
