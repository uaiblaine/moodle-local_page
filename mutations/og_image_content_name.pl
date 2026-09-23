# og_image_content_name: drop the extension check from ogimage::is_image(), leaving only core's
# is_valid_image(). Core counts an SVG as a web image, so a genuine SVG under its own name — the
# format the file manager's accepted types exclude because it can carry a script — passes again
# wherever it reaches the area without the file manager.
s{        if \(!\$types->is_allowed_file_type\(\$file->get_filename\(\), \$accepted\)\) \{\n            return false;\n        \}\n\n}{}s;
