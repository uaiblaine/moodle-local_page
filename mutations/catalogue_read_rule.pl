# Skip the read-side rule: a logged-in-only or an access-levelled page is listed to whoever asks.
s{ \|\| !local_page_user_can_view_page\(\$row, \$predicate\)}{};
