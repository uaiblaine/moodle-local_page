# Ignore the publish window: a scheduled or an expired page is listed.
s{!local_page_publish_window_is_open\(\$row, \$now\) \|\| }{};
