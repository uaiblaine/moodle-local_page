# og_image_content_advertised: let the head tags name a file without asking whether it is the image its
# name says. The route refuses that file, so every preview built from the tags points at an address that
# answers nothing, under a type and a size the bytes do not have.
s{        if \(\$file === null \|\| !self::is_image\(\$file\)\) \{}{        if (\$file === null) \{}s;
