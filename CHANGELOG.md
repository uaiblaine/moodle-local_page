# CHANGELOG

## [1.0.10+uai.12] - 2026-09-25

The oracle the editor had closed, closed in the listing too, and one unused string removed. No table,
column or upgrade step changes.

### Fixed
- **The pages list told a visitor and a guest which context ids exist.** `pages.php` looked up the
  context named by `?contextid=` before the login check, so a visitor who was not logged in met an
  error for a context id that does not exist and the login page for a category context that does (and
  `invalidcontext` for any other context), and the guest account met a missing-record error against a
  refusal (`nopermissions`). The listing now checks the login and refuses the guest account before any
  lookup, as the editor does, and no longer logs a visitor in as the guest: both answers are the login
  page, for a visitor and for a guest. The capability check stays after the lookup, since it is read in
  the context the URL names.

### Removed
- The string `none` (English and Brazilian Portuguese), which no code, template or form read.

### Added
- Two Behat scenarios in `tests/behat/category_pages.feature`: a visitor and then a guest opening the
  pages list of a context that does not exist and of a category that does, and the step
  `I visit the pages list of the category "<idnumber>"` in `tests/behat/behat_local_page.php`.

## [1.0.10+uai.11] - 2026-09-24

The improvements the comment audit left open: one oracle closed for the guest account, the page's
status shown in the viewer's language, accessible names on the listing's icon buttons, a corrected
error message, and dead code removed. No table, column or upgrade step changes; the upgrade the
version bump asks for purges the caches, which a site caching its templates needs for the new one.

### Fixed
- **A guest could still tell which category ids exist through the editor.** `edit.php` checked the
  login before any lookup, but a guest session passes that check (the guest login button, or
  `autologinguests`, which logged a visitor in as the guest on the spot), so a guest opening
  `edit.php?category=<id>` met an error for an id that does not exist and a refusal for one that does.
  The editor now refuses the guest account before any lookup with the answer a visitor who is not
  logged in gets, the login page, and no longer logs a visitor in as the guest.
- **The page's status was never shown on Moodle 5.2, and could only ever say it in English.** Upstream
  drew *Live*, *Draft* or *Archived* above the heading from `styles.css`, on a selector
  (`h1.page-header-headings`) that names no element Moodle 5.2 renders: the class sits on the block
  around the heading. The status is now a badge inside the heading, built from the plugin's own status
  strings, for whoever may edit the page; everybody else reads the title alone, as before. The page
  name is still formatted exactly once.
- **The error for an access level made only of negations offered a remedy that does not clear it.**
  It suggested setting "Only logged in" to Yes, but the editor refuses such a list whatever that field
  says, which is deliberate. The message now names the one remedy, a positive capability (English and
  Brazilian Portuguese).
- **The listing's view and delete buttons had no accessible name.** Each shows only an icon, so a screen
  reader announced a bare link; each now carries an `aria-label` and a `title` (*View* and *Remove*).

### Changed
- `db/uninstall.php` removes only the plugin's rows from core's shortlink table. Its loop over every
  context deleting the two file areas duplicated core: `uninstall_plugin()` calls
  `file_storage::delete_component_files()` after the plugin's own uninstall function, which deletes
  every file of the component, in every context. The file's description, which said the files were not
  purged automatically, said the opposite of what core does.
- `local_page_render_view()` no longer asks the database whether the page exists before adding its
  `local-page-id-<id>` body class: a viewer allowed to read the page already implies a stored row that
  is not deleted.

### Removed
- Dead code: the unused `$download` and `$title` in `edit.php`; the list of every page (one query per
  editor load) and the list of theme layouts that `forms/edit.php` built and never used; an unused
  import in `classes/output/pages_list.php`; the status text rules in `styles.css`.

### Added
- `templates/status_badge.mustache` and `local_page_status_badge()` in `lib.php`.
- Tests: `tests/status_badge_test.php` (the heading for an editor and for a reader, each status, the
  site's own wording of the strings), `tests/styles_test.php` (the stylesheet draws no text), a deleted
  row in `test_is_taken_counts_only_pages_that_are_not_deleted`, a page whose context is gone in
  `tests/shortlink_handler_test.php`, the accessible names in `tests/output/pages_list_test.php`, and
  two Behat scenarios in `tests/behat/category_pages.feature`: a guest opening the editor, and the
  badge seen by a category's manager and not by a manager of another category.
- Gates in `mutations/gates.conf` for the badge's strings and its capability check, the two
  accessible names, the deleted filter of `slug::is_taken()` and the handler's missing context.

## [1.0.10+uai.10] - 2026-09-23

Five fixes found in review of the category-pages series, and two upgrade steps. `db/install.xml` is
unchanged: it already declares the schema the new steps bring an upgraded site to.

### Fixed
- **The upgrade's friendly-URL normalisation could leave two live pages at one address.** A duplicate
  was moved to `<slug>-<id>` without that value being checked, so where a lower id already held it —
  `x-3` beside two pages called `x`, the second of them page 3 — page 3 was moved onto `x-3`, and a
  second run kept it there, which is how the claimed idempotence hid the duplicate;
  `custompage::load_by_menuname()` then served only the higher id. `slug::normalise_all()` now moves a
  page to the first of `-<id>`, `-<id>-2`, `-<id>-3` that no earlier row claimed and no other live
  page of its context holds, so it never takes a later page's slug either. This is the rule
  `slug::unique_in_context()` already applied to a page arriving in a category, now one method both
  call. A slug that already ends in the page's id gains only the counter: `page-12` of page 12 moves
  to `page-12-2`, where `unique_in_context()` used to answer `page-12-12-2`.
- **A page saved with no friendly URL could take one another page held.** The save path named it
  `page-<id>` without asking whether a live page of its context already held that name, which an
  author may type (`page-` is not reserved and the field accepts it). It now goes through
  `slug::unique_in_context()`, and the page becomes `page-<id>-2` in that case.
- **The editor looked the category up before the login check.** An anonymous request for
  `edit.php?category=<id>` died on a missing record for an id that does not exist and met the login
  page for one that does, which told a visitor which category ids exist, and did database work for
  requests nobody had logged in to make. `require_login()` now comes first, and both answers are the
  login page.
- **Upgrade steps no longer call live code.** Step 2026092202 called
  `\local_page\local\slug::normalise_all()`, so a later change making that method read a column added
  by a later step would have killed every upgrade from below it, part-way. The step now calls
  `local_page_upgrade_normalise_slugs()` in the new `db/upgradelib.php`, a frozen copy that reads only
  `id`, `menuname`, `deleted` and `contextid` and is never edited once a step calls it.
- **Upstream defect: `hidetitle` was nullable on upgraded sites.** Upstream's step 2025060200 adds the
  column without NOT NULL while `db/install.xml` declares it NOT NULL with the default `no`, so a site
  upgraded through that step and a fresh install had different schemas, and
  `admin/cli/check_database_schema.php` reported it. Step 2026092209 fills NULLs with `no` and makes
  the column NOT NULL; the schema check now passes. A page that held NULL showed no title (the viewer
  shows it only for `no`) and shows it from now on, as the editor already said it would. Worth
  offering upstream.

### Upgrade
- Step 2026092209 runs `local_page_upgrade_hidetitle_notnull()`.
- Step 2026092210 runs the corrected normalisation again, so a site that passed step 2026092202 with
  the defect above loses any duplicate it was left with; on a site with none it changes nothing. List
  the live slugs before and after, as the README's upgrade notes describe.

### Added
- Tests: three cases in `tests/local/slug_test.php` (the fixture above, a page skipping the forms
  later pages hold, a slug already ending in its page id), one in `tests/save_page_test.php`, and
  `tests/upgradelib_test.php`, which runs the frozen copy and the class on the same rows and requires
  the same table, and repairs a `hidetitle` column made nullable first. A Behat scenario in
  `tests/behat/category_pages.feature` opens the editor for a category that does not exist and for one
  that does, with the new step `I visit the editor of a new page of the category "IDNUMBER"`.
- Seven gates in `mutations/gates.conf`; `slug_unique_counter` now anchors on the shared loop.

## The category-pages series (1.0.10+uai.1 to uai.9) — 2026-09-23

A summary for administrators of the nine releases below; it is not a release of its own, and the
version stays at 2026092208 (1.0.10+uai.9). The README's *Adopting category pages* section is the
guide to taking it into production, and `docs/nginx-runbook.md` orders the optional web server work.

**What the series adds.** A custom page may now belong to a course category as well as to the site.
Holders of `local/page:managecategorypages` in a category write that category's pages from its own
administration menu; putting one in front of visitors who are not logged in takes a second
capability, `local/page:publishcategorypages`. Both are assigned per category and held by the manager
archetype by default. A category page's HTML is cleaned by Moodle unless its author was trusted with
unclean markup under core's trusted-content rules, and its `<head>` field does not exist. Visitors
read a category's pages only while `local_unlistedcourses` says the category is public; without that
plugin nobody does, and the refusal is the same whether a category or a page exists or not. Each
category page has a routed address, `/local_page/category/<id>/<slug>`, and a public short address,
`/p/<code>`, minted when it is saved. Its Open Graph tags are rebuilt: one title, a description, the
locale, a canonical link, a content-checked image of up to 600 KB at an address that changes with the
picture, and `noindex` unless the site is open to search engines. When core deletes a category its
pages are deleted with it, or move with their files to wherever its content moves.

**What changes for site-wide pages.** Their addresses, rules and body are upstream's, apart from the
security fixes listed below; friendly URLs are unique among live pages, which the upgrade enforces
once by renaming duplicates. Their head changes with every other page's: the rebuilt Open Graph tags
and the canonical link apply to them too, and the plugin no longer writes to
`$CFG->additionalhtmlhead`. The plugin requires Moodle 5.2 and declares no other branch.

**Fixes worth offering upstream as a pull request** (decision D10; a list, not a date):

- The Open Graph image area was served to anybody by item id, a draft's or a deleted page's image
  included; it is now served only for a live page inside its publish window (1.0.10+uai.2).
- The save path trusted the posted page id; it now re-reads the row, refuses a deleted one and
  checks the capability against it (1.0.10+uai.2).
- An access level made only of negated capabilities grants the page to every visitor while reading
  like a restriction; the form now refuses it, and any capability the site does not define
  (1.0.10+uai.2).
- Friendly URLs had no uniqueness at all, so a duplicate silently shadowed an older page; they are now
  unique among live pages, normalised once on upgrade and released on delete (1.0.10+uai.2, per
  context from 1.0.10+uai.3).
- An SVG, or any file, saved under a raster image's name was stored as that image and served
  anonymously; an Open Graph image must now be the picture its name says (1.0.10+uai.8).
- Upstream wrote the meta title as an invalid `<meta name="og:title">` beside the real `og:title`,
  so a shared link never showed the title written for sharing; there is now one `og:title`
  (1.0.10+uai.8).
- The upgrade-order repair (1.0.10+uai.5) belongs to the pull request only together with the two
  steps it orders, the slug normalisation and the context columns; upstream has neither on its own.
- The README's NGINX advice to add a dot to the slug class, which can serve PHP source, was removed
  in 1.0.10+uai.2. The same release also replaced upstream's `&$query_string` with `$is_args$args`,
  on the belief that the former was invalid; it was not, and the replacement appends a second `?`
  whenever the visitor's address carries a query. Measured on NGINX on 2026-09-23, the README is
  back to upstream's spelling, with the reason beside it, so only the removal of the dot belongs in
  the pull request.

**No release.** This fork is never tagged and never publishes to moodle.org (decision D11): releases
there are the upstream author's, and the fork follows them by `git merge upstream/main`.

## [1.0.10+uai.9] - 2026-09-23

What becomes of a category's pages when the category is deleted. Core deletes a category in two
ways and, either way, ends by deleting the category's context and every file stored in it; until now
the pages of that category were left behind, pointing at a context that no longer existed. No schema
change and no upgrade step; the version bump is what makes core find the two new callbacks.

### Added
- **`local_page_pre_course_category_delete()`** (`lib.php`), which core calls from
  `core_course_category::delete_full()` before deleting anything: every live page of the category is
  soft-deleted the way the pages screen deletes one — `deleted = 1`, its friendly URL released as
  `<slug>-deleted-<id>` — and its public short codes are removed. Files are left to core, which purges
  them with the context. A subcategory's pages are handled by the same callback, which core's own
  recursion calls for each subcategory in turn.
- **`local_page_pre_course_category_delete_move()`** (`lib.php`), which core calls from
  `core_course_category::delete_move()` before moving anything. For each page of the category, in
  this order: both file areas (`pagecontent` and `ogimage`, under the page's id) move to the new
  parent's context, as core relocates the content bank; then the row names the new context and
  category; then its friendly URL is checked in the new category, and a slug a live page there already
  holds gains the page's id (`contato` becomes `contato-42`) while the resident keeps its own — under
  the lock the editor's save takes. Deleted rows move with their files and keep their names. Short
  codes need no change: the handler answers a page's current address.
- **A move to the root is refused** while the category holds a live page, with the error core's own
  web service gives for that move (`movecatcontentstoroot`), before core has changed anything: a page
  belongs to a category or to the site, and core cannot complete that move anyway.
- **`\local_page\local\lifecycle`**, the class both callbacks delegate to, and
  **`\local_page\local\slug::unique_in_context()`**, the slug a page may carry in a context it
  arrives in.
- The lang string `categorymovebusy`, in both packs, for the one failure left: another page being
  saved while the category's pages are moved.
- Tests: `tests/local/lifecycle_test.php`; four cases in `tests/lib_test.php` that delete and move
  categories through `core_course_category` itself, with a page in a sibling category as the control
  that must still serve its image afterwards; a case in `tests/local/slug_test.php`; and
  `tests/privacy/provider_test.php`, which holds the null provider to the schema (no column naming a
  user). Core's own privacy compliance test passes for the component. Ten more entries in
  `mutations/gates.conf`.

### Changed
- Nothing else. `db/uninstall.php` already visits every context a page names and removes the
  plugin's short codes (1.0.10+uai.3 and 1.0.10+uai.7).

## [1.0.10+uai.8] - 2026-09-22

The Open Graph port. What a page puts in the document head when its link is shared — the preview a
messaging app draws — is now built by a renderable, rendered by a template and written by a hook,
instead of being appended to `$CFG->additionalhtmlhead` with `html_writer`. No schema change and no
upgrade step; the version bump is what registers the hook.

### Added
- **`\local_page\output\opengraph`** and **`templates/opengraph.mustache`**: the Open Graph metas
  as RDFa property metas — `og:type`, `og:title`, `og:description`, `og:url`, `og:site_name`,
  `og:locale` and `og:image` with `og:image:type`, `og:image:alt`, `og:image:width` and
  `og:image:height`. Every value is held in the plain spelling and escaped once, by the template.
- **`db/hooks.php`** and **`\local_page\local\hook\output\before_standard_head_html_generation`**:
  the callback writes the tags into the head when, and only when, `local_page_render_view()`
  registered them — with the name metas (description, keywords, author, robots) and a
  `<link rel="canonical">` as literal escaped strings beside the template's output, then the per-page
  head HTML of a site-wide page, raw, as before.
- **`og:description`**, from the page's meta description, and no tag at all when it is empty.
- **`og:locale`**, the language the page is rendered in, with its territory upper-cased (`pt_BR`).
- **`og:image:type`** and, when the file can be measured, **`og:image:width`** and
  **`og:image:height`**, read through core's own cache of image sizes (`core/file_imageinfo`, keyed by
  content hash), which the editor's file manager already fills when it validates the upload.
- **The canonical link**, `<link rel="canonical">`, on every page the viewer may read, at the address
  `og:url` carries.
- **The hashed image address** `…/ogimage/<page id>/<content hash>/<file name>`, spelled by the new
  `\local_page\local\ogimage`: a replaced image is a new address, so a messaging app's cached
  preview of the old picture never stands in for it.
- Tests: `tests/output/opengraph_test.php`,
  `tests/local/hook/output/before_standard_head_html_generation_test.php`, the fixture
  `tests/classes/ogimage_fixture.php`, and new cases in `tests/lib_test.php`,
  `tests/save_page_test.php` and `tests/forms/edit_form_test.php`; fourteen more entries in
  `mutations/gates.conf`.

### Changed
- **One `og:title`**: the page's meta title when it has one, its name otherwise.
- **The robots directive of a category page**: its own when its author wrote one, otherwise `noindex`
  unless the site is open to search engines (`$CFG->opentowebcrawlers`). A site-wide page without a
  directive still carries none.
- **The Open Graph image may be up to 600 KB** (it was 200 KB), what WhatsApp was measured to accept.
- **`$CFG->additionalhtmlhead` is no longer touched.** The plugin appended its tags to the site setting
  mid-request; the site's own additional head HTML is now left exactly as the administrator wrote it.
- **The image route accepts the hashed address beside upstream's flat one**, behind the same
  publication and context gates. The hash is a cache key, not a credential, and is not compared with
  the file; a path segment that is not a content hash is refused rather than ignored.
- The meta description, keywords, author and titles go through `format_string()` in the page's
  context before they are written, so a multilang span in them reads as the reader's language.

### Fixed
- **An Open Graph image must be the picture its name says.** The file manager's accepted types
  (JPEG, PNG, WebP) were applied to the file name only, so an SVG saved as `cover.png` — markup, able
  to carry a script — was stored, typed `image/png`, advertised with the 800 × 600 or stated size
  core's SVG branch reports, and served to anybody at the anonymous image address. The new
  `\local_page\local\ogimage::is_image()` requires one of those extensions and core's
  `stored_file::is_valid_image()`, whose type read from the content must equal the stored one; the
  editor refuses such a file with a message (new string `edit_ogimage_notimage`), and the file route
  and the head tags refuse one that reached the area any other way. Present since upstream; five more
  entries in `mutations/gates.conf`, and `tests/lib_test.php` asks the image route through a helper
  carrying the file's ETag, so a mutated route answers 304 instead of writing binary bytes into the
  PHPUnit log.
- **The duplicate and invalid `og:title` upstream emitted.** The meta title was written as
  `<meta name="og:title">` — which no Open Graph reader looks at — and the page name as the real
  `og:title` property, so a shared link showed the page name and never the title written for sharing.

## [1.0.10+uai.7] - 2026-09-22

The addresses of category pages. A category page answered only at the script's
`?category=N&page=slug` address; from this release it has a routed address and a public short address
as well, and every address the plugin prints, stores or redirects to is spelled in one place. Site-wide
pages are untouched: their addresses, their canonical tags and what every viewer sees on them are what
upstream produced. No schema change and no upgrade step; the plugin writes rows to core's `{shortlink}`
table and removes them again on delete and on uninstall.

### Added
- **The routed address `/local_page/category/<category id>/<slug>`**, answered by
  `\local_page\route\controller\page` on Moodle's routing engine. It makes no decision of its own: it
  asks `\local_page\local\request::category()`, the call the script makes, and renders the body with
  `local_page_render_view()`, the function the script renders with. No login requirement on the route,
  because the page serves visitors of a public category on a site that forces login; plain integer and
  ALPHANUMEXT parameters, never a category path type, whose not-found before the controller would tell
  an anonymous client which categories exist.
- **The public short address `/p/<code>`**, core's own shortlink route. A category page's code is
  minted by core's `\core\shortlink` manager the first time the page is saved while the router is
  configured, reused on every later save, and never minted on a GET; `\local_page\shortlink_handler`
  resolves it to the page's canonical address, and refuses the code of a deleted page.
- **`\local_page\local\links`, the address builder**: the script's forms, the route (only while
  `$CFG->routerconfigured` is set, the script otherwise — never the longer `/r.php/` spelling), a page's
  canonical address, and its short address. It never sets `$CFG->urlrewriteclass`, a single global slot.
- `local_page_render_view()` in `lib.php`: what `index.php` did after the request's decision, moved so
  both addresses render the same page.
- Tests: `tests/local/links_test.php`, `tests/shortlink_handler_test.php`,
  `tests/shortlink_route_test.php`, `tests/route/controller/page_test.php` and
  `tests/save_page_test.php`; two Behat scenarios walking the routed address; ten more entries in
  `mutations/gates.conf`.

### Changed
- **The legacy script redirects while the router is configured.** Its slug form answers a `303` to
  the routed address before anything is looked up, for every category and every slug alike. Upstream's
  `?id=` address answers a `303` for a category page too, but only once the page's rules and the public
  predicate have let the viewer read it — a visitor the page is withheld from gets the login page, never
  an address naming the page. The `?category=N&id=M` form has no routed twin and is still served.
- **A category page's canonical tag, `og:url`, `$PAGE` address and the visitor's way back after
  logging in are its routed address** where the router is configured, and its script address elsewhere.
- **A category card on the pages screen shows the page's address**, and its short address once one
  was minted, labelled "Short address to share". The card's friendly URL is now printed through a
  double stash instead of upstream's triple one: without the router that address is the script's
  query string, and its ampersand reached the page unescaped.
- Upstream's `require_login()` for a page with an access level moved from `index.php` into
  `local_page_render_view()`, so it applies at the routed address as well.

### Documentation
- README: the routed address, the short address and what happens without the router; and an optional,
  unshipped web server alias for category pages (NGINX and Apache), mapped onto the script's query-string
  address because the router reads the original request URI.

## [1.0.10+uai.6] - 2026-09-22

The viewer for category pages. A category's pages could be authored since the previous release, and
a visitor who was not logged in could read any of them that its own rules allowed; from this release
a category page reaches a visitor only when the category itself is public. Site-wide pages are
untouched: their addresses, their rules and what a visitor sees on them are what upstream produced.
No schema change and no upgrade step.

### Added
- **`\local_page\local\request`, the viewer's decision in one order.** `index.php` used to load the
  row, set up `$PAGE` and ask the access predicate itself; the request class now does all three and
  the script only translates its answer — a redirect target, or a page and whether the viewer may
  read it. For a category address the order is a security property: a visitor is refused before
  anything is looked up unless the category is public, then the category's context, then the lookup
  scoped to it, then the page's own rules, then `$PAGE` with `set_category_by_id()` first.
- **`\local_page\local\publicaccess`**, a fail-closed adapter over
  `local_unlistedcourses`' `category_discoverability::is_public()`, and the one definition of a
  visitor: nobody logged in, or the guest account.
- **The category address `/local/page/index.php?category=N&page=slug`** (or `&id=M`), answered by the
  existing script. Stage 6 adds a routed address beside it.
- `tests/local/request_test.php`, `tests/local/publicaccess_test.php`, a shared predicate double in
  `tests/classes/`, the plugin's first Behat generator and step context, the feature
  `tests/behat/anonymous_viewer.feature` run under `forcelogin`, and six more entries in
  `mutations/gates.conf`.

### Changed
- **A category page is served to visitors only when its category is public** as
  `local_unlistedcourses` defines it — the public state, a visible category and a visible path above
  it. Without that plugin nothing is public and a visitor is sent to the login page: the adapter
  fails closed, and neither plugin declares a dependency on the other. The rule lives in
  `local_page_user_can_view_page()`, so it holds for a category page's **embedded files** as well
  as for the page.
- **The guest account counts as a visitor.** Core treats a guest session as logged in; here it is
  refused and admitted on exactly the terms of somebody not logged in at all.
- A visitor meets **one refusal** at a category address — the login page, bringing them back to the
  page after they log in — whether the category is private, hidden or not there at all, so the
  address cannot be used to learn which categories exist; the same refusal answers an address that
  names no page of the category, and a page the category holds but withholds from visitors by its
  own rules — a draft, a page for logged-in readers, one outside its publish window — so that inside
  a public category the slugs that exist cannot be told from the ones that do not, and a reader of a
  page kept for logged-in readers is taken to the login form rather than left on a page that says
  nothing. A logged-in user asking for a page that is not there, or for a draft, still gets the
  "no access" page.

## [1.0.10+uai.5] - 2026-09-22

The authoring UI for category pages. Until this release a category's pages could be listed, but
every control on that screen was built for the site-wide context, so the screen led nowhere: three
separate links took a category author out of their own category and into a screen that refuses
them. Site-wide pages are untouched — every address the site-wide listing renders is byte for byte
what upstream produced.

### Added
- **A category's administration menu now leads to its pages.** `local_page_extend_navigation_category_settings()`
  hangs a "Custom pages" node on the node core builds in
  `settings_navigation::load_category_settings()`, for holders of `local/page:managecategorypages`
  **in that category** and nobody else. This was the missing half of the delegation: the admin
  tree's "Manage pages" entry is gated on `local/page:addpages`, which a category manager does not
  hold, so before this the category screens existed and nothing linked to them.
- **`local_page_list_url()` and `local_page_edit_url()`**, the two addresses every screen is built
  from. A listing address names the category through `contextid`; an editor address names an
  existing page by id alone, and a new one by the category it will belong to.
- `tests/navigation_test.php` and `tests/output/pages_list_test.php`, the plugin's first
  `tests/behat/category_pages.feature`, and five more entries in `mutations/gates.conf`.

### Changed
- The listing screen of a category is headed with that category's name, so it says whose pages it
  is showing. The site-wide screen keeps its own heading string.
- A category page's card no longer prints a friendly URL. `wwwroot/<slug>` is the site-wide
  address, answered by a web server rewrite for site pages only; printing it for a category page
  would advertise an address that serves a different page.

### Fixed
- **The upgrade path died before it reached any of this.** The step that normalises friendly URLs
  selects `contextid`, and it was numbered one version *below* the step that adds that column, so
  every upgrade from an earlier release stopped there with a fatal and left the site's whole
  upgrade half applied. The two steps have swapped savepoint numbers and the column is now added
  first. A fresh install reads `db/install.xml`, where the column has always been declared, which
  is why every test site and every CI leg was provisioned straight past the defect without ever
  walking it.
- **"Add New Page" on a category's listing opened the site-wide editor**, which checks
  `local/page:addpages` and refused the very author whose screen had offered the button.
- **Cancelling an edit redirected to the site-wide listing**, which refuses a category author for
  the same reason. The cancel now returns to the listing of the context being edited.
- **A category page could not be saved at all.** With no action given, `moodleform` posts to
  `strip_querystring($FULLME)`, so the editor posted back to a bare `edit.php`, which resolved the
  system context from a URL naming nothing and refused the author on `local/page:addpages` before
  the save path ever read the hidden `contextid`. The form now names its own address, and
  `MoodleQuickForm` carries the parameters across as hidden inputs. Every unit test of the save
  path had stayed green through this, because they call the save path directly; only the Behat
  walk-through could see it.
- **A category card's delete link carried no context**, so the action resolved the system context,
  refused a category manager on the capability, and refused even an administrator at the row
  comparison, which rejects a row that does not belong to the context the screen was authorised
  for. The link now names its category.

## [1.0.10+uai.4] - 2026-09-22

What a category page may contain, and who may put it in front of the open web. Site-wide pages are
untouched in every respect: they keep upstream's trusted, uncleaned rendering, their raw Content
HTML block and their `<head>` field, byte for byte.

### Added
- **Category pages follow core's trusttext rules.** `{local_page}` gains `contenttrust`, written on
  every save from `trusttext_trusted()` at the page's own context — `$CFG->enabletrusttext` on AND
  the author holding `moodle/site:trustcontent` there. A category author may write raw HTML; whether
  it survives is core's decision, not this plugin's, and it is taken about the person who **wrote**
  the markup rather than the person reading it later. Two consequences worth stating: the setting is
  off by default on every Moodle site, so until an administrator turns it on every category page is
  cleaned whoever wrote it; and `$CFG->forceclean` overrides everything, site-wide pages included.
- **Trust is honoured on the way back into the editor too**, through core's own
  `trusttext_pre_edit()`. Without that half the other half undoes itself: an untrusted editor opens
  a page a trusted colleague wrote, the script the viewer never sees arrives in their form, and
  saving it unchanged carries it across the trust boundary.
- **`local/page:publishcategorypages` is now read.** Declared in the previous release and enforced
  from this one: a category page saved by somebody who does not hold it at that category is stored
  with `onlyloggedin = 1`, whatever was posted.
- `tests/trust_test.php` for the five new library functions, three form tests, a lockstep assertion
  over the two language packs, and six more entries in `mutations/gates.conf`.

### Changed
- **The Content HTML block of a category page is no longer raw.** It goes through the same
  `format_text()` call as the editor content, so it is cleaned on the same terms and filtered as
  well. In a category there is no second, unfiltered channel; on a site-wide page the block is
  concatenated exactly as before.
- **The per-page `<head>` field is system scope only.** The form omits it for a category, the save
  path never writes it there, and a row that holds one from elsewhere is ignored rather than
  emitted. Everything else a page stores is body HTML, which `clean_text()` can judge; a `<head>`
  fragment can carry a script element, a meta refresh or a base tag, and no sanitiser is written
  for that.
- **The "Only logged in" select is frozen at Yes** for a category editor without the publishing
  capability, with a line saying why. The freeze is explanation, not enforcement — a frozen select
  stops nothing that is posted by hand, which is why the save path corrects the record regardless.
- The editor of a category page declares `trusttext` instead of `noclean`; a site-wide page keeps
  `noclean`.

### Security
- **The split matters most on a site running `forcelogin` off.** A category page reaches anonymous
  visitors, so "may author a programme's pages" and "may publish to the open web" are different
  powers and are now different capabilities — a delegated author can no longer put a page in front
  of the internet by leaving a select at its default. And because that page is public, the HTML in
  it is cleaned unless the site has deliberately said otherwise, twice: once by enabling trusttext
  at all, and once by granting `moodle/site:trustcontent` in that category.

## [1.0.10+uai.3] - 2026-09-22

The data model learns where a page lives. Nothing an author can see changes yet: no screen offers
to create a page in a category, and every existing page keeps its address, its files and its
permissions exactly as they were.

### Added
- **Pages have a context.** `{local_page}` gains `contextid` and `categoryid`, plus a
  `(contextid, menuname)` index, and a new `\local_page\local\scope` is the only code that reads
  them. **A stored `contextid` of 0 means the system context**, resolved in code because a context
  id is a row id assigned at install time and no XMLDB default can name one — which is what makes
  the change additive: every row written before this release reads as a site-wide page and the
  upgrade migrates nothing.
- **Two capabilities, declared now.** `local/page:managecategorypages` authors the pages of one
  course category; `local/page:publishcategorypages` will gate putting one in front of visitors who
  are not logged in. Both are `CONTEXT_COURSECAT`, `RISK_SPAM`, manager by archetype, and both are
  deliberately **without `clonepermissionsfrom`**: no upgrade may back-fill a category right from
  `moodle/category:manage` or from `local/page:addpages`. The publishing one is declared and not yet
  read — the gate that consults it is the next release — so that the capability, its strings and its
  archetype exist before anything starts refusing on them.
- **Reserved friendly URLs.** `slug::is_reserved()` refuses the names Moodle answers on itself:
  every top-level entry of the 5.2 webroot, the router's own segments (`p`, `s`, `esm`, `check`,
  `templates`, `api`), and anything shaped like a frankenstyle component, because a plugin
  installed tomorrow may claim its own name. A slug already stored is never renamed — see the note
  below.
- Tests for the whole context dimension (`tests/local/scope_test.php`, and additions to the lib,
  slug and form tests), including an assertion that every capability declared in `db/access.php`
  has its language string, and seven more entries in `mutations/gates.conf`.

### Changed
- **Friendly URLs are unique per context, not per site.** Two categories may each own a page called
  `contato`, and neither collides with a site-wide page of that name. `slug::is_taken()` takes the
  scope, `slug::normalise_all()` groups duplicates by it, and `custompage::load_by_menuname()` takes
  a context to look in (defaulting to the site-wide scope, which is what the site-root viewer means).
- **Nine places that said "the system context" now ask the row.** The viewer predicate, the write-path
  re-check, the `pluginfile` callback, the save path, the edit form, `edit.php`, `pages.php`,
  `index.php` and `custompage` all resolve the context through `scope`, so the site configuration
  override, the `accesslevel` entries and the editor-preview branch are evaluated where the page
  actually lives. A manager of one category previews that category's drafts and nobody else's.
- **A category page keeps its embedded files under its own id**, in its own context, which lets the
  file route authorise a file with one row lookup — the row must be in the requested context and not
  deleted — instead of the `LIKE` search the shared site-wide area needs. The site-wide area is
  untouched at item id 0, because every URL stored in every existing page names that item id, and
  that `LIKE` search is now scoped to the context the file was asked for: a page of another context
  naming a site-wide file does not make it servable, which matters from this release on because a
  category's pages are written by people who hold no right over the site's own files. The
  `ogimage` route additionally refuses a row whose own context is not the one being asked, since page
  ids are unique table-wide and the item id alone says nothing about who may serve the file.
- The listing screen, the editor and the delete action are per context: `pages.php` takes a
  `contextid`, checks the capability of that context and refuses to delete a row belonging to
  another one, and `edit.php` takes a `category` for a new page and reads the row's own context for
  an existing one.
- `db/uninstall.php` empties both file areas in every context named by a row, not only in the system
  context.

### Notes
- **No user interface creates a category page yet.** This release is the data model and the
  permissions; the editor entry point, the publishing gate, the anonymous viewer and the addresses
  that reach a category page are the releases that follow it.
- `slug::normalise_all()` does **not** rename a stored slug that `is_reserved()` would now refuse. A
  rename at upgrade time breaks an address that has been published and working; the form refuses new
  ones instead, where nothing is lost.

## [1.0.10+uai.2] - 2026-09-22

Security prerequisites. Each fix is self-contained and commented as such, so it can be offered
upstream on its own.

### Security
- Open Graph images are no longer world-readable by item id. `local_page_pluginfile()` now loads
  the page the `ogimage` item id belongs to and serves the file only when
  `local_page_ogimage_is_servable()` agrees: not deleted, status `live`, and inside the publish
  window. The gate deliberately reads **no** capability, `accesslevel` or `onlyloggedin` — the
  fetch that matters is an anonymous scraper's, and `index.php` emits the `og:image` tag only
  after the viewer has already passed the read-side predicate, and only when the same gate says
  the file will be served — an editor previewing a draft page gets no `og:image` tag at all,
  because a draft page has no published image for anybody.
- The write path re-checks the page it is about. `local_page_require_editable_page()` loads the
  posted id with `deleted = 0`, raises `pagenotfound` when the row is missing or soft-deleted, and
  then requires `local/page:addpages`. `renderer::save_page()` calls it before building the record
  and takes the id from the row it returns rather than from the hidden field; `edit.php` calls it
  after `custompage::load($id, true)`, which skips the deleted filter on purpose. A page deleted
  between rendering the form and posting it can no longer be saved back into existence.
- The edit form validates the "Required capability" field, which had no server-side validation at
  all. An entry naming a capability this site does not define is refused, and so is a list made up
  of negations only — such a rule grants the page to every visitor, anonymous ones included, while
  reading like a restriction. The read-side predicate is unchanged and keeps evaluating
  already-stored rows exactly as before.

### Changed
- Friendly URLs (`menuname`) are unique among pages that have not been deleted. The form refuses a
  slug another live page holds, and `renderer::save_page()` re-checks inside a `local_page` core
  lock so two editors cannot take the same one at once. A page saved with no slug is named
  `page-<id>`, so every page is addressable.
- **Deleting a page releases its friendly URL** — the delete in `pages.php` writes
  `slug::deleted_name()` in the same update that sets `deleted = 1`. A page restored by hand
  therefore needs a new slug, which is the deliberate trade for a delete that stops holding a
  public address nobody can reach.
- New `\local_page\local\slug` holds the rules, and the upgrade to `2026092201` runs
  `slug::normalise_all()` once over existing rows: empty live slugs are named, duplicates below
  the lowest id gain `-<id>`, deleted rows are mangled, and every value is trimmed, lower-cased
  and truncated to the column width. The routine is idempotent.
- `README.md`, "Friendly URLs": the advice to add `.` to the slug character class is gone —
  `PARAM_ALPHANUMEXT` strips dots, so no saved slug can contain one; the NGINX example uses
  `$is_args$args` instead of the invalid `&$query_string`; and a new paragraph covers Moodle 5.1+,
  where the routing engine's own fallback to `r.php` must be out-ranked by the slug rule, and
  where a regex `location` declared before the PHP one would serve `/index.php` as source.

### Added
- Tests for the og:image gate (including the viewer predicate as its control, and the refusal
  through `local_page_pluginfile()` itself), the editable re-check, the new form validation and
  the slug rules, plus `mutations/gates.conf` with six gates pairing each guard to the tests that
  must redden when it is broken.

## [1.0.10+uai.1] - 2026-09-22

Fork onboarding onto the fleet standards. No behaviour change on a running site
beyond the badge contrast and dark-mode fixes below.

### Added
- `.github/workflows/ci.yml` now calls the moodle-an-hochschulen reusable workflow with a single Moodle 5.02 job, plus the branch filter and concurrency block the fleet uses.
- `phpcs.xml`, `.moodle-plugin-ci.yml`, `.stylelintrc.json`, `.github/PULL_REQUEST_TEMPLATE.md` and a plugin `CLAUDE.md`.
- First PHPUnit coverage: `tests/coverage.php`, a `local_page_generator` data generator, and `tests/lib_test.php` covering `local_page_user_can_view_page()` across status, publish window, `onlyloggedin`, access levels, the `local/page:addpages` preview branch and the administrator short-circuit.

### Changed
- `$plugin->supported` is `[502, 502]` and `$plugin->requires` is Moodle 5.2; the CI job list matches.
- Status badges pair every `bg-*` utility with a text utility (`text-white` / `text-dark`). Bootstrap 5 defaults badge text to white, which left the draft badge at 1.95:1 against the 4.5:1 AA floor.
- The status badge string id is a literal per status instead of `get_string('status_' . $status, ...)`.
- Dark-mode rules are keyed on `:root[data-bs-theme="dark"]`, the only dark mechanism Moodle 5.2 emits; the previous `.theme-dark` rules matched nothing. Hard-coded colours now read `--bs-*` theme tokens with a literal fallback, so a site's own palette applies.
- Templates use live Bootstrap 5 class names: `fw-medium` replaces the undefined `font-weight-medium`, and the undefined `badge-sq` is dropped.
- `db/install.xml` carries `VERSION="20251008"`, matching the last schema-changing savepoint.
- `lang/en` was pruned of strings no code references, and a `lang/pt_br` pack was added in lockstep.

### Removed
- `.github/workflows/moodle-release.yml`. This fork does not publish to moodle.org — releases there stay with the upstream author — and the workflow fired on every `version.php` push.
- The `defined('MOODLE_INTERNAL')` guard in `lib.php` and `db/uninstall.php`, and the `phpcs:ignore` that existed only to silence the resulting warning: both files declare functions and nothing else, so the guard is what the sniff objects to.
- `.DS_Store` and `templates/.DS_Store` are untracked and ignored; development-only paths are `export-ignore`d so they stay out of the install zip.

## [1.0.10] - 2026-05-08

### Security
- Viewer no longer loads soft-deleted rows by id or leaks page titles and SEO/meta before access checks resolve.
- `local_page_pages_referencing_pagecontent_file()` uses anchored substring matching so prefix filename collisions cannot widen pluginfile access.

### Added
- `db/uninstall.php` purges `pagecontent` and `ogimage` file areas on uninstall.

### Changed
- The **Simple Content Builder** helper on the page edit screen loads only when **theme_xy** is both **installed** (component and `contentbuilder/builder` Mustache present) and **active** for the current page (`$PAGE->theme` name is `xy`). If xy is installed but another theme is in use, the builder is not injected and the standard editor flow applies.
- Unauthenticated placeholders use core `guest_user()` instead of a hard-coded user id.
- Delete confirmation uses Mustache `#str`; edit link and layout metadata use loaded page id (`menuname` routes).
- `menuname` request param aligned with `PARAM_ALPHANUMEXT`; clarified `.htaccess` and README routing notes.

## [1.0.9] - 2026-05-07

### Added
- On the page edit screen, when the **theme_xy** component is present, the **Simple Content Builder** UI and scripts are loaded so the **Content HTML** field (`id_contenthtml`) can be edited with the same builder as in theme admin (overlay, snippets). Without **theme_xy**, behaviour is unchanged.

### Fixed
- Users with the **local/page:addpages** capability can **preview** pages that are **draft** or **archived**, or outside the public **page date / end date** window, without needing **moodle/site:config**. Public visitors still only see **live** pages within the configured dates.
- **Content HTML** output now runs **pluginfile** URL rewriting for viewers, so embedded files from the page content file area resolve correctly on the public page (same behaviour as the main HTML editor field).
- **Content HTML** is no longer dropped when the stored value is the string **"0"** (PHP `empty()` edge case).
- Saving and viewing **Content HTML** remains reliable when the main Moodle editor field is empty (pages that use only the raw HTML block).

## [1.0.8] - 2026-04-29

### Security
- Page visibility and file access aligned so hidden or draft content is not leaked.
- User placeholders in page content only use safe profile fields.
- Open Graph images limited to common raster formats.

### Fixed
- Site “Additional HTML” and meta tags on the public page index behave correctly.
- Admin pages list, delete flow, SQL portability, and capability checks cleaned up.
- Form parameter types tightened; removed unused settings and dead code paths.

### Added
- Optional friendly outgoing URLs via `local_page\url_rewriter` and docs.

### Improved
- Edit form strings localised; clearer admin help for access levels and URLs.

## [1.0.7] - 2026-02-17
### Improved
- Enhanced Moodle code precheck compliance for better code quality and maintainability.

## [1.0.7] - 2026-02-09
### Fixed
- Minor bug fixes and improvements

## [1.0.6] - 2025-10-09
### Fixed
- Additional HTML Content

## [1.0.5] - 2025-10-07
### Fixed
- Resolved issue with the Delete Page button not functioning as expected.

## [1.0.4] - 2025-07-21
### Added
- Enhanced compatibility with Moodle 5.0.

### Improved
- Improved support for friendly URLs in custom pages.

## [1.0.3] - 2025-06-24
### Added
- Option to hide the page title.

### Fixed
- Added missing language string for capability definition.

## [1.0.2] - 2025-06-03
### Added
- Option to hide the page title.

### Fixed
- Added missing language string for capability definition.

## [1.0.1] - 2025-04-30
### Added
- Modal confirmation dialog for page deletion to prevent accidental removal.
- Enhanced user experience with clear confirmation messages and action buttons.

### Fixed
- Added missing Open Graph image file.
- Replaced hard-coded language strings with language file references.
- Added missing language string for capability definition.

## [1.0.0] - 2025-04-02
### Added
- Initial release.