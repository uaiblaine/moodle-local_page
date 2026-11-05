# withheld_pagetype: name the row in the page type whatever the viewer may read. The page type is the
# body's id, so a visitor asking for a site-wide draft by id or slug learns that it exists and its id.
s{\(\$canview \? max\(0, \(int\) \$custompage->id\) : 0\)}{max(0, (int) \$custompage->id)}s;
