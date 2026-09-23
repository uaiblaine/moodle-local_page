# trust_requires_setting: make local_page_content_trust() read the capability alone, ignoring
# whether the site has the trusttext feature switched on at all. Core's rule is the conjunction
# (lib/weblib.php:948-951), and the setting is OFF by default on every Moodle site — so with this
# mutation a page written on an ordinary site records its author as trusted, and the renderer then
# hands that author's unclean HTML to every visitor on a site that never enabled the feature.
s{    return trusttext_trusted\(\$context\) \? 1 : 0;}{    return has_capability('moodle/site:trustcontent', \$context) ? 1 : 0;};
