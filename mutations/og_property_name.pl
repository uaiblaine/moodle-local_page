# og_property_name: spell the og:title as a name meta, the way upstream spelled its meta title. A
# scraper reads Open Graph from property attributes only, so the page would share with no title.
s{<meta property="og:title" content="\{\{title\}\}">}{<meta name="og:title" content="{{title}}">}s;
