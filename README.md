# Custom Pages Documentation

## Overview
This module allows users to create and manage custom pages within Moodle. It provides a user-friendly interface for adding, editing, and deleting pages, as well as configuring various settings related to each page.

## Features
- **Add Pages**: Users can create new pages with specific content and settings.
- **Edit Pages**: Existing pages can be modified to update content or settings.
- **Delete Pages**: Users can remove pages that are no longer needed.
- **Access Control**: Define access levels for each page based on user capabilities.

## Usage
1. Navigate to **Site Administration** > **Plugins** > **Local Plugins** > **Page** > **Manage Pages** in the Moodle admin panel.
2. Click on **Add Page** to create a new page.
3. Fill in the required fields, including page name, content, and access level.
4. Save the page to make it available to users.

## Configuration
- **Access Level**: Specify the capabilities required to view the page. Use commas to separate multiple capabilities.
- **Additional HTML**: Optionally add custom HTML to the `<head>` section of the page for additional styling or scripts.

## Category pages (this fork)

A page may belong to a course category instead of to the site, and the two are governed
differently. Its HTML is cleaned by Moodle unless the author was trusted with unclean markup when
they saved it — that is, unless the site has `$CFG->enabletrusttext` on **and** the author holds
`moodle/site:trustcontent` in that category — while a site-wide page keeps the trusted, uncleaned
rendering it has always had; the per-page `<head>` field stays with site-wide pages, because no
sanitiser exists for head markup. Showing a category page to visitors who are not logged in is a
separate capability, `local/page:publishcategorypages`: a page saved by somebody without it in that
category is stored for logged-in users only, whatever the form posted.

**Reaching them.** A manager of the category opens the category page, then its administration menu
(`Category` in the secondary navigation) and the **Custom pages** entry. The node appears only for
holders of `local/page:managecategorypages` in that category, so a manager of one category never
sees another's. Everything the screen then offers stays inside the category: the list, the
**Add New Page** button, the editor and its back link, cancelling an edit, and deleting a page.
Site administrators keep the site-wide screen under *Site administration > Plugins > Local plugins >
Custom pages > Manage pages*, which lists the site's own pages and not any category's.

A category page has no `wwwroot/<slug>` friendly URL: that address is the site-wide convention,
answered by the web server rewrite rules below for site pages only. Its addresses are:

- **The routed address** `/local_page/category/<category id>/<slug>`, answered by Moodle's routing
  engine. It is the page's canonical address wherever the site's router is configured
  (`$CFG->routerconfigured = true`, Moodle 5.1 and later): the one its canonical tag and `og:url`
  carry, and the one a visitor is brought back to after logging in. The `local_page/` prefix is
  Moodle's rule for a plugin's routes, not a choice of this plugin. It needs no web server change
  beyond the router's own fallback to `r.php`.
- **The public short address** `/p/<code>`, answered by Moodle's own shortlink route and sent on to
  the routed address. A category page gets its code the first time it is saved while the router is
  configured, keeps it on every later save, and shows it on its card on the pages screen as
  **Short address to share**; deleting the page deletes its code. The code names the page, not an
  address, so it keeps working whatever address the page is served at.
- **The script's address** `/local/page/index.php?category=<category id>&page=<slug>`, or
  `&id=<page id>`. It answers on every site. Where the router is configured, the slug form answers
  with a `303 See Other` to the routed address before anything is looked up — every category and every
  slug get the same redirect, so it tells nobody anything — and the page's rules are applied where it
  lands. The id form has no routed twin and is served where it is asked for.

Without the router, nothing is minted and the script's address is the canonical one: the plugin never
spells a routed address the site does not answer. Upstream's own `?id=<page id>` address still reaches a
category page; where the router is configured it answers somebody the page's rules let read it with a
`303` to the routed address, and only then — a visitor the page is withheld from gets the login page,
never an address that would name the page.

**Visitors.** A visitor — somebody not logged in, or the guest account — may read a category page
only when the category is **public**, and "public" is not this plugin's decision: it is the public
state of the optional plugin `local_unlistedcourses`, which also requires the category and every
category above it to be visible. Without that plugin nothing is public, and every visitor asking for
a category page is sent to the login page and brought back to the page afterwards; the site's own
pages are served to visitors exactly as before, `forcelogin` or not. The refusal is the same whether
the category is private, hidden or does not exist, so the address cannot be used to find out which
categories exist; in a public category, an address naming no page of that category gets the same
refusal, and so does a page the category holds but withholds from visitors by its own rules — a
draft, a page for logged-in users, one outside its publish window — so the slugs that exist cannot be
told from the ones that do not, and a reader of a page kept for logged-in users is taken to the login
form and back to the page. The same rule governs the files a category page embeds. A logged-in user never meets it: what they may read is decided by the page's own status,
publish window, "only logged-in users" flag and access level, in the category's context.

## Friendly URLs (`menuname`)

Pages can use a **Friendly URL** slug (`menuname`) so viewers can open  
`https://yourmoodlesite.example/path/to/moodle/about-us`  
instead of  
`https://yourmoodlesite.example/path/to/moodle/local/page/index.php?menuname=about-us`.

### Incoming URL routing

- **This plugin’s `.htaccess`** (under `local/page/`) asks Apache to rewrite  
  `…/local/page/<slug>` → `…/local/page/index.php?menuname=<slug>` when `mod_rewrite` and `AllowOverride` allow it. Slugs use the same character set as the form (`PARAM_ALPHANUMEXT`). It does **not** handle **root-level** paths like `…/about-us` (those never hit this directory).
- Moodle’s optional **`$CFG->urlrewriteclass`** only rewrites **outgoing** URLs from `moodle_url::out()`; it does **not** accept incoming requests by itself.

So for **short** URLs like `/about-us` at the site root, add **web-root** rewrite rules (below). For **only** `/local/page/about-us`, configuring the server to honour this plugin’s `.htaccess` is enough.

**Slug uniqueness (this fork, from 1.0.10+uai.2)**: a slug is unique among pages that have not been deleted — the edit form refuses one that is already in use, and the save is serialised so two editors cannot take the same one at once. Deleting a page **releases** its slug, so the address becomes available to another page immediately; a page restored by hand therefore needs a new slug. Pages that existed before this rule are brought into line once, on upgrade.

### Web server: rewrite at the Moodle web root

Place rules where your **Moodle installation’s URL root** is served (same vhost as `$CFG->wwwroot`). Adjust the path prefix if Moodle lives in a subdirectory (e.g. `/moodle/`).

**Apache** (`mod_rewrite`), inside the `<Directory>` for your Moodle docroot or in the vhost:

```apache
RewriteEngine On
# If the request is not a real file/dir and looks like a single slug segment, send to local_page.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9_-]+)$ /local/page/index.php?menuname=$1 [L,QSA]
```

`[a-zA-Z0-9_-]+` already matches the edit form’s slug rules exactly: `menuname` is cleaned with `PARAM_ALPHANUMEXT`, which keeps only letters, digits, `_` and `-`, so no saved slug can contain a dot and widening the pattern to admit one would only capture requests this plugin can never answer.

If Moodle is under `/moodle/`, use `RewriteRule ^([a-zA-Z0-9_-]+)$ /moodle/local/page/index.php?menuname=$1 [L,QSA]` (or `RewriteBase /moodle/` and a relative target—match your layout).

**Nginx** (illustrative `location`):

```nginx
location ~ ^/([a-zA-Z0-9_-]+)$ {
    try_files $uri $uri/ /local/page/index.php?menuname=$1$is_args$args;
}
```

Again, prefix with your Moodle base path if not at domain root.

**Conflicts**: A catch-all slug rule can shadow other single-segment routes. Restrict slugs in the rule, reserve paths, or place this rule after more specific locations.

**Moodle 5.1 and later**: with the routing engine configured (`$CFG->routerconfigured = true`), the server already has a fallback that sends every unmatched path to `public/r.php`. A root-level slug rule must therefore be declared so that it answers *before* that fallback, or the router replies first and the slug never reaches this plugin. On NGINX there is a second ordering rule on top of that one: the server takes the **first matching regex `location` in declaration order**, so a regex slug location must come **after** the `location ~ \.php$` block (or exclude `.php` by pattern). Declared before it, a pattern as broad as `^/([a-zA-Z0-9_-]+)$` swallows `/index.php` and NGINX serves the PHP source instead of executing it.

### Optional: a shorter alias for category pages (not required)

The plugin ships **nothing** for this, and nothing in it depends on it: the routed address and the
`/p/<code>` short address above need no web server change beyond the router's own fallback, and they
are the addresses the plugin prints. A site that wants a vanity prefix of its own — `/paginas/12/handbook`,
say — can map it in the web server onto the script's category address:

```nginx
# Declared AFTER the `location ~ \.php$` block: NGINX takes the first matching regex location in
# declaration order, and this one must never capture a PHP script.
location ~ ^/paginas/(\d+)/([a-z0-9_-]+)$ {
    rewrite ^/paginas/(\d+)/([a-z0-9_-]+)$ /local/page/index.php?category=$1&page=$2 last;
}
```

```apache
RewriteEngine On
RewriteRule ^paginas/([0-9]+)/([a-z0-9_-]+)$ /local/page/index.php?category=$1&page=$2 [L,QSA]
```

The class has no dot, for the reason given above. Map onto the **query-string** address, never onto the
routed path: Moodle's router reads the original request URI, so an internal rewrite to
`/local_page/category/...` is invisible to it, and the request reaches it as `/paginas/...`, a path it
has no route for. The Apache rule belongs where the slug rule above goes, in the same context. Where
the router is configured, the script then answers the alias with a `303` to the routed address, so the
alias works as a short redirect and the address bar settles on the canonical address; where it is not, the
script serves the page at the alias. Adjust the prefix if Moodle lives in a subdirectory.

### Optional: shorten links Moodle prints (`urlrewriteclass`)

To rewrite **generated** links from `…/local/page/index.php?menuname=slug` to `…/slug` (so emails and UI match your rewrite), add to **`config.php`** (only one rewriter class is supported site-wide):

```php
$CFG->urlrewriteclass = '\local_page\url_rewriter';
```

You still need the **web server** rules above so that `/slug` actually runs the viewer.

## Documentation
https://rosea.gitbook.io/page-by-roseathemes

## Help
For more information, refer to the support@rosea.io
