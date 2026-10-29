# editor_new_page_empty: a new page's draft is prepared from item id 0 instead of from nothing.
s{        return \$pageid > 0 \? \$pageid : null;}{        return \$pageid;};
