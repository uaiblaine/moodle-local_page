# og_image_content_route: stop the og:image route asking whether the file is the image its name says.
# The editor still refuses such a file on the way in, which is what makes this call site worth holding
# on its own: a file that reached the area by any other way would be served to anybody.
s{        if \(\$file && !\\local_page\\local\\ogimage::is_image\(\$file\)\) \{\n            return false;\n        \}\n}{}s;
