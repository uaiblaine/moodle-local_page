# og_image_content: answer "is this our image" from the file's NAME alone, dropping the check that the
# type read from its content is the type its name says. An SVG saved as cover.png — markup, able to
# carry a script — is then accepted by the editor, named in the head tags and served to anybody under
# image/png, which is exactly what the upload's extension check let through before this guard.
s{        return \$file->is_valid_image\(\);\n}{        return true;\n}s;
