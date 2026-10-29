# bin_delete_keeps_code: deleting forgets the page's /p/ code, so a restored page has lost its address.
s{        \\local_page\\event\\page_deleted::create}{        links::forget(\$pageid);\n        \\local_page\\event\\page_deleted::create};
