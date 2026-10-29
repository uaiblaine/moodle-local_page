# save_own_area: an existing site page's files are saved under item id 0 again.
s{            \$itemid = \(int\) \(\$editable->id \?\? 0\);}{            \$itemid = \$iscategory ? (int) (\$editable->id ?? 0) : 0;\n            \$newpage = \$iscategory && \$itemid <= 0;};
s{            \$newpage = \$itemid <= 0;\n}{};
