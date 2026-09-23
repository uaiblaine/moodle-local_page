# og_single_title: write the page name as a second og:title beside the meta title, which is the shape
# upstream shipped — two titles in one head, and a scraper free to pick the one not written for it.
s{            'robots' => \(string\) self::robots\(\$page\),\n}{            'robots' => (string) self::robots(\$page),\n            'og:title' => self::plain((string) (\$page->pagename ?? ''), \$context),\n}s;
