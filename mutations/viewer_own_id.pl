# viewer_own_id: the viewer spells a site page's file URLs under item id 0.
s{        \$itemid = \(int\) \(\$data->id \?\? 0\);}{        \$itemid = \\local_page\\local\\scope::is_category(\$data) ? (int) \$data->id : 0;};
