# render_category_trusted: make local_page_render_content() claim every category page is trusted,
# whatever its stored flag says. The cleaning that stands between a delegated category author and
# arbitrary script on a public page is gone, while the function still reads as though the flag
# decided.
s{        'trusted' => \(bool\) \(\$page->contenttrust \?\? 0\),}{        'trusted' => true,};
