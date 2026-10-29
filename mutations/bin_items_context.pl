# bin_items_context: a bin lists, and empties, every context's deleted pages.
s{            \['contextid' => scope::stored_contextid\(\$context\), 'deleted' => 1\],\n            'timedeleted DESC, id DESC',}{            ['deleted' => 1],\n            'timedeleted DESC, id DESC',};
