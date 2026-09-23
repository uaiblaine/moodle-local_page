# og_image_unservable: advertise the image without asking whether the file route will serve it. A draft,
# archived or expired page previewed by its editor would then name an image whose address answers 404.
s{        if \(!local_page_ogimage_is_servable\(\$page\)\) \{\n            return null;\n        \}\n\n}{}s;
