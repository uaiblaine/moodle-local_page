# og_image_hash: drop the content hash from the image address. A replaced image then keeps the old
# address, and every messaging app goes on showing the preview it cached for the previous picture.
s{            '/' \. \$file->get_contenthash\(\) \. '/',\n}{            '/',\n}s;
