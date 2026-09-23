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

### When a category is deleted or moved

Moodle offers two ways to delete a course category, and its pages follow each of them.

- **Deleting it with all its content.** Its pages are deleted with it, the way the pages screen
  deletes one: they stop answering, their friendly URLs are released and their short codes removed,
  and their files go with the category. A subcategory deleted along with it gives the same treatment
  to its own pages.
- **Deleting it and moving its content to another category.** Its pages move to that category with
  their files — the image and everything the text embeds — and answer at the new category's address.
  A short code keeps working and now leads to the new address. A page whose friendly URL the new
  category already uses gets its id appended (`contato` becomes `contato-42`); the page that was
  already there keeps its address. Subcategories move as they are, pages included.

Moving the content to the top level, outside any category, is refused while the category holds a
page: a page belongs to a category or to the site. Moodle's own screens never offer that target, and
Moodle cannot complete that move anyway.

### Sharing a page: Open Graph

When a link to a page is pasted into a messaging app or a social network, the app fetches the page
anonymously and builds its preview card from the page's head. Every page the viewer may read carries:

- **one `og:title`**: the page's *Meta Title* when it has one, its name otherwise;
- **`og:description`**: the page's *Meta Description*, and no tag at all when it is empty;
- **`og:url`** and a **`<link rel="canonical">`**: the page's canonical address — for a category page,
  the routed address where the router is configured;
- **`og:site_name`**, **`og:type`** (`website`) and **`og:locale`**, the language the page was
  rendered in, spelled the Open Graph way (`pt_BR`, not Moodle's `pt_br`);
- **`og:image`**, with **`og:image:type`**, **`og:image:alt`** (the title) and, when the file can be
  measured, **`og:image:width`** and **`og:image:height`**.

The page's *Meta Description*, *Meta Keywords* and *Meta Author* are written as the ordinary
`description`, `keywords` and `author` metas as well. A page the viewer may not read — a draft, a
page outside its publish window, a category page withheld from a visitor — puts none of this in the
head.

**The image.** Upload it in the page's *Open Graph Image File* field: JPEG, PNG or WebP, up to
**600 KB**, ideally **1200 × 630** pixels, the size preview cards are drawn at. 600 KB is what
WhatsApp was measured to accept; a larger image is dropped from the card without an error. SVG is
refused, because the image is served to anybody — and so is any file whose content is not the picture
its name says, such as an SVG renamed to `.png`: the editor reports it when the page is saved. It is served to anybody on purpose: the app fetching
it carries no Moodle session, so it is gated only on the page being published — never on who is
asking — and a draft, archived or expired page has no image, whoever asks. Its address carries the
file's content hash (`…/ogimage/<page id>/<hash>/<file name>`): messaging apps keep a preview for as
long as they like, keyed by the image address, so replacing the picture changes the address and the
new one is fetched. The old address keeps working and serves the new picture.

**Search engines.** A page whose author filled in *Meta Robots* carries that directive. Otherwise a
**category page** is marked `noindex` unless the site is open to search engines
(`$CFG->opentowebcrawlers`, *Site administration > Security > Site security settings > Open to
search engines*): a category page exists so that a shared link unfurls, not so that a search engine
lists it, and that is the site's decision, not each author's. A site-wide page without a directive
carries none, as before.

## Adopting category pages

This section is for the administrator taking category pages into production. It was written for the
FUNDASEG site (Moodle 5.2, `forcelogin` on, NGINX, `local_unlistedcourses` installed), where the
plugin is not installed yet, so there is nothing to migrate: step 1 of the checklist confirms that
before anything else is done. Every expected value below was measured on a development copy of
Moodle 5.2 on 2026-09-23, with one exception said where it appears: that copy shows developer
debugging, so a refused file answers `500` there where a production site answers `404`.

Run every `php` command from the Moodle directory that holds `config.php`, as the web server's user
(`sudo -u www-data php ...` on Debian and Ubuntu).

### Prerequisites

- **Moodle 5.2.** The plugin requires it and declares no other branch.
- **This fork, from its git repository.** The plugin page on moodle.org publishes upstream's
  releases, which have none of this; see *What this fork does not ship* below.
- **The routing engine, `$CFG->routerconfigured = true`**, with the web server fallback that hands
  unknown paths to `r.php` ([NGINX runbook](docs/nginx-runbook.md), step 1). It is what gives a
  category page its routed address and its `/p/<code>` short address. Without it category pages
  still work at the script's address, and no code is minted.
- **`local_unlistedcourses`, for visitors.** Optional, and the plugin fails closed without it: no
  category page reaches a visitor who is not logged in, whatever its settings. With it, a category's
  pages reach visitors only while that plugin says the category is **public** (public state, visible
  category, visible categories above it).
- **`$CFG->enabletrusttext`** (*Site administration > Security > Site security settings > Enable
  trusted content*) decides what happens to raw HTML in a category page. Off, the default: every
  category page is cleaned by Moodle, whoever wrote it. On: a page saved by an author holding
  `moodle/site:trustcontent` in that category keeps its markup, scripts and iframes included. Site-wide
  pages are not affected either way, and `$CFG->forceclean` overrides both.
- **`$CFG->opentowebcrawlers`** (*Open to search engines*, same settings page) decides the robots
  directive of a category page whose author left *Meta Robots* empty. Off: `noindex`. On: none.

### Roles: who writes and who publishes

| Capability | Context | What it grants | Default holders |
|---|---|---|---|
| `local/page:addpages` | system | The site-wide pages and their screen, as upstream. Unchanged. | manager, course creator |
| `local/page:managecategorypages` | course category | List, create, edit and delete the pages of that category, reached from the category's own menu. | manager |
| `local/page:publishcategorypages` | course category | Save a category page that visitors who are not logged in can read. Without it a page is stored for logged-in users only, whatever the form says. | manager |

**Assign them at the category, never at system.** A capability applies to the context where its role
is assigned and to everything below it: a role assigned at system level grants these capabilities in
every category of the site, and one assigned at a parent category grants them in all its
subcategories. Neither capability copies itself from another one on upgrade, so nobody holds them
until you decide who does, apart from the Manager role.

**Prefer a dedicated role to Manager.** Manager holds both capabilities by default, and a Manager
assigned at a category can already write and publish its pages. It also holds everything else a
category manager can do, `moodle/site:trustcontent` included, so on a site with trusted content
switched on, a Manager's HTML is never cleaned. A role that carries exactly the one right keeps the two
decisions separate:

1. *Site administration > Users > Permissions > Define roles > Add a new role*, archetype *No role*,
   context type **Category** only. Name it, say, *Category page author*, and allow
   `local/page:managecategorypages`.
2. Create a second role the same way, *Category page publisher*, allowing
   `local/page:publishcategorypages`, for the people who may put a page in front of the open web.
   Holding both is what an author needs to publish their own pages.
3. In each category that should have pages, open the category, then its administration menu
   (*Category* in the secondary navigation) > **Permissions**, and use the selector at the top of that
   page to reach **Assign roles**. Assign the roles there.
4. On the same page, **Permissions** lists both capabilities under the *Custom Pages* heading, and
   **Check permissions** confirms what a given person holds in that category.

Keep `moodle/site:trustcontent` away from category authors unless their raw HTML is meant to reach
visitors unfiltered. `local/page:addpages` stays with administrators and course creators: it governs
the site-wide pages, which keep upstream's trusted rendering and their `<head>` field.

### Upgrade notes

- **Rehearse on a labelled copy first.** Restore a backup of the database and `moodledata` to a
  staging site, run `php admin/cli/upgrade.php --non-interactive` there, and only then on production.
- **The upgrade path was repaired in 1.0.10+uai.5.** An earlier build of this series numbered the
  step that normalises friendly URLs below the step that adds the column it reads, so an upgrade from
  any release before 2026092201 died half applied. A fresh install never walks those steps, which is
  why no test caught it. Deploy the tip of the series, never one of its intermediate branches.
- **Upstream's pages keep working.** A site-wide page keeps its addresses, its rules and its body,
  apart from the security fixes: an Open Graph image is served only for a live page inside its
  publish window, a saved access level must name real capabilities and not only negations, and
  friendly URLs are unique. What does change is its head: the Open Graph tags are rebuilt for every
  page (see *Sharing a page*), and the plugin no longer appends them to the site's
  `$CFG->additionalhtmlhead`.
- **That uniqueness rewrites stored friendly URLs once**, in step 2026092202: every slug is trimmed,
  lower-cased and cut to the column width, an empty one becomes `page-<id>`, a duplicate keeps its
  address on the lowest id and gains `-<id>` elsewhere, and a deleted page's becomes
  `<slug>-deleted-<id>`. On a site that already runs upstream's plugin, list the live slugs on the copy
  before and after the upgrade; every line that changed is a published address that changed.
  ```sh
  php -r 'define("CLI_SCRIPT", true); require("config.php");
  foreach ($DB->get_records("local_page", ["deleted" => 0], "id", "id, menuname") as $page) {
      echo $page->id, " ", $page->menuname, "\n";
  }'
  ```
- **Releases 1.0.10+uai.5 to uai.9 add no table and no column, and still need the upgrade.** Their
  version bump is what makes Moodle register what they add: the category menu node and the two
  category deletion callbacks in `lib.php`, and the Open Graph hook in `db/hooks.php`. Until the
  upgrade has run none of them is registered; step 2 of the checklist is how to tell.

### Verification checklist

Nine steps, in this order. Each step gives the expected answer and what a wrong one means.

**1. Is the plugin installed, and what does it hold?**

```sh
php -r 'define("CLI_SCRIPT", true); require("config.php");
$v = get_config("local_page", "version");
if (!$v) { exit("local_page is not installed\n"); }
echo "local_page version ", $v, "\n";
echo "pages: ", $DB->count_records("local_page"), " (not deleted: ", $DB->count_records("local_page", ["deleted" => 0]), ")\n";
echo "files: ", $DB->count_records_select("files", "component = ? AND filename <> ?", ["local_page", "."]), "\n";'
```

Expected at FUNDASEG today: `local_page is not installed`. A version of `2026050805` or lower means
upstream's plugin is already there, with the pages and files counted: follow the upgrade notes above,
the slug listing included, before going further.

**2. Upgrade, and confirm nothing is pending.**

```sh
php admin/cli/upgrade.php --non-interactive
php admin/cli/upgrade.php --is-pending; echo "exit $?"
```

Expected: `No upgrade needed ...` and `exit 0`, and *Site administration > Plugins > Plugins overview*
lists `local_page` at version `2026092208`. Exit code `2` means the upgrade has not run, and nothing the
series registers (hook, menu node, callbacks) is active yet.

**3 and 4. Who holds the category capabilities, and who holds trusted content.** Assign the roles as
described above, then print the holders for a category you assigned (`12` here) and for one where you
assigned nobody (`13`):

```sh
php -r 'define("CLI_SCRIPT", true); require("config.php");
$context = context_coursecat::instance((int) $argv[1]);
foreach (["local/page:managecategorypages", "local/page:publishcategorypages", "moodle/site:trustcontent"] as $cap) {
    $users = get_users_by_capability($context, $cap, "u.id, u.username");
    echo $cap, ": ", implode(", ", array_column($users, "username")) ?: "(nobody)", "\n";
}' 12
```

Run it a second time with `13` in place of the final `12`. Expected: the category nobody was
assigned in lists only the people who hold the capabilities
site-wide (system-level managers), and the assigned category lists those same people plus exactly the
ones you assigned. Site administrators hold everything and are not listed. An author who appears in the
unassigned category was given the role at system level or in a parent category. On the
`moodle/site:trustcontent` line, anyone listed beyond your system-level managers keeps raw HTML when
trusted content is on — usually a Manager assigned at the category. Then open the category's
**Permissions** page: both capabilities must be listed under *Custom Pages*.

**5. Is `local_unlistedcourses` installed, and are the target categories public?**

```sh
php -r 'define("CLI_SCRIPT", true); require("config.php");
if (!class_exists(\local_unlistedcourses\category_discoverability::class)) {
    exit("local_unlistedcourses is not installed: no category page reaches a visitor\n");
}
foreach (array_slice($argv, 1) as $id) {
    echo $id, ": ", \local_unlistedcourses\category_discoverability::is_public((int) $id) ? "public" : "NOT public", "\n";
}' 12 13
```

Expected: `public` for every category whose pages visitors must read. `NOT public` means those
visitors are sent to the login page, which is the correct answer for a category that is meant to stay
private. The category's *Discoverability* entry, in the same menu as *Custom pages*, changes it.

**6. The two site settings.**

```sh
php -r 'define("CLI_SCRIPT", true); require("config.php");
echo "routerconfigured:  ", empty($CFG->routerconfigured) ? "no" : "yes", "\n";
echo "opentowebcrawlers: ", empty($CFG->opentowebcrawlers) ? "no" : "yes", "\n";
echo "enabletrusttext:   ", empty($CFG->enabletrusttext) ? "no" : "yes", "\n";
echo "forcelogin:        ", empty($CFG->forcelogin) ? "no" : "yes", "\n";'
```

Expected: `routerconfigured: yes`; `opentowebcrawlers` and `enabletrusttext` at the values you chose
(both `no` keeps category pages out of search engines and cleans all their HTML). *Site administration
> Reports > System status* runs Moodle's own *Router configuration* check, which requests the site
from the server itself.

**7. The addresses, from outside the VPN.** Create a test page in a public category, with an Open
Graph image and status *Live*, and a second one left as *Draft* with an image too. Print their
addresses (the category id is the argument):

```sh
php -r 'define("CLI_SCRIPT", true); require("config.php");
foreach ($DB->get_records("local_page", ["categoryid" => (int) $argv[1], "deleted" => 0], "id") as $page) {
    $image = \local_page\local\ogimage::stored(\local_page\local\scope::context($page), (int) $page->id);
    echo $page->status, " ", $page->menuname, "\n",
        "  page:  ", \local_page\local\links::page($page)->out(false), "\n",
        "  short: ", \local_page\local\links::existing_share((int) $page->id)?->out(false) ?? "(none)", "\n",
        "  image: ", $image ? \local_page\local\ogimage::url($image)->out(false) : "(none)", "\n";
}' 12
```

Then, from a machine outside the VPN and without any session, fill in the variables and run:

```sh
SITE=https://moodle.example.org    # $CFG->wwwroot, no trailing slash
CAT=12                             # the public category holding the test pages
SLUG=adoption-check                # the live test page's friendly URL
PRIV=13                            # a category that is not public
CODE=AbC12                         # the live test page's short code, after /p/
DRAFTIMG='https://...'             # the draft test page's image address, as printed above

curl -s -o /dev/null -w '%{http_code}\n' "$SITE/local_page/category/$CAT/$SLUG"
for url in "$SITE/local_page/category/$PRIV/$SLUG" \
           "$SITE/local_page/category/999999999/$SLUG" \
           "$SITE/local_page/category/$CAT/no-such-page"; do
    curl -s -o /dev/null -D - "$url" | grep -iv '^date:\|^set-cookie:' | cksum
done
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$SITE/local_page/category/$PRIV/$SLUG"
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$SITE/local/page/index.php?category=$CAT&page=$SLUG"
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' "$SITE/p/$CODE"
curl -s -o /dev/null -w '%{http_code}\n' "$DRAFTIMG"
for path in index.php local/page/index.php r.php; do
    printf '%s: ' "$path"; curl -s "$SITE/$path" | head -c 5; echo
done
```

| Line | Expected | A wrong answer means |
|---|---|---|
| The live page | `200` | A `302` to the login page: the category is not public (step 5), or the page is a draft, outside its window, or stored for logged-in users because its author could not publish. A web server `404`: the router fallback is missing ([runbook](docs/nginx-runbook.md), step 1). |
| The three refusals (private category, missing category, missing slug) | three identical lines | The site answers them differently, so an anonymous client can tell which categories and pages exist. |
| The private category, again | `302 $SITE/login/index.php` | A `200` means that category is public. A visitor's refusal on the routed address is always a `302`. |
| The script's address | `303 $SITE/local_page/category/$CAT/$SLUG` | A `200`: `$CFG->routerconfigured` is off and the script serves the page itself. The script's redirects are always `303`. |
| `/p/$CODE` | `302 $SITE/local_page/category/$CAT/$SLUG` | A `404`: the code is wrong, or its page was deleted, which deletes its codes. |
| The draft's image | `404` | A `200` means a draft's picture is public, so this fork's image gate is not the code running. A development site with developer debugging answers `500` (its error page), which is also a refusal; without it, Moodle's error page sends `404` (`core_renderer::fatal_error()`). |
| `index.php`, `local/page/index.php`, `r.php` | anything but `<?php` (measured: `<!DOC` or `<!doc`) | `<?php` is the source of Moodle served as text: a regex location captures `.php` before the PHP handler does. Fix the location order at once ([runbook](docs/nginx-runbook.md), steps 2 and 3). |

**8. Paste a short address into WhatsApp and Telegram.** This one is by hand. Paste
`$SITE/p/$CODE` into a chat in each app: the preview card must show the page's *Meta Title* (its name
when that is empty), its *Meta Description* and its image. Then replace the image in the editor, save,
and paste the address again: the image address carries the file's content hash, so the new picture
must appear. If an app still shows the old card, it has cached the page itself; Telegram refreshes a
link's preview through its `@WebpageBot`.

**9. Every capability has its name.** A capability without its language string shows as
`[[name:capability]]` on a production site and, on a site with developer debugging, stops the
permissions table part-way down the page. Print where to look for a course (course id `2` here) and
how many rows to expect:

```sh
php -r 'define("CLI_SCRIPT", true); require("config.php");
$context = context_course::instance((int) $argv[1]);
echo "open   ", (new moodle_url("/admin/roles/permissions.php", ["contextid" => $context->id]))->out(false), "\n",
    "expect ", count($context->get_capabilities()), " capability rows\n";' 2
```

Open that address as an administrator and count the rows in the browser's developer console with
`document.querySelectorAll('#permissions tr[data-name]').length`; the number must equal the one
printed. Then run the sweep, which names every installed component whose capability lacks its string;
empty output is clean:

```sh
php -r 'define("CLI_SCRIPT", true); require("config.php");
foreach ($DB->get_records("capabilities", null, "component, name") as $c) {
    list($t, $n, $cn) = preg_split("|[/:]|", $c->name);
    $comp = $t === "moodle" ? "core_role" : ($t === "quizreport" ? "quiz_$n" : "{$t}_{$n}");
    if ($comp !== "core_role" && !get_string_manager()->string_exists("$n:$cn", $comp)
            && file_exists((string) core_component::get_component_directory($comp))) {
        echo "$comp  $n:$cn  ($c->name)\n";
    }
}'
```

### What this fork does not ship

- **No web server alias.** The routed address and `/p/<code>` need nothing beyond the router's own
  fallback. A shorter vanity prefix is optional and described below and, as ordered steps, in the
  [NGINX runbook](docs/nginx-runbook.md).
- **No release tag and no moodle.org package.** This fork is never tagged and never publishes to
  moodle.org; that is the upstream author's channel, and the release workflow that would publish on a
  tag was removed. Install from the fork's git repository. Upstream releases arrive by
  `git merge upstream/main` into the fork's `main`, never by rebase.
- **No `$CFG->urlrewriteclass`.** The plugin never sets it: Moodle has one slot for it, with no
  chaining, and the site may need it for something else. The optional rewriter of site-wide links
  described under *Friendly URLs* is upstream's and stays optional.

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
    try_files $uri $uri/ /local/page/index.php?menuname=$1&$query_string;
}
```

Again, prefix with your Moodle base path if not at domain root. The target already carries a query of
its own, so the visitor's query string is appended with `&` (`$query_string` is NGINX's other name for
`$args`). `$is_args$args` is the spelling for a target without a query, such as the router's
`/r.php$is_args$args`; here it would add a second `?`, and a shared link carrying `?fbclid=...` would
reach the viewer as the slug `about-usfbclid...`, a page that does not exist.

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

The [NGINX runbook](docs/nginx-runbook.md) puts this alias and the rest of the web server guidance
above into ordered steps for a production site, each with the check that proves it.

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
