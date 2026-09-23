# og_image_content_form: stop the editor's validation asking whether the uploaded og image is the
# picture its name says. The route and the head tags still refuse the file, so nothing is served —
# the author is simply told nothing, and a file the plugin will never use is stored.
s{                if \(!\\local_page\\local\\ogimage::is_image\(\$ogdraftfile\)\) \{}{                if (false) \{}s;
