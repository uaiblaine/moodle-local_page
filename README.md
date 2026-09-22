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
