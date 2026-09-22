# CHANGELOG

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