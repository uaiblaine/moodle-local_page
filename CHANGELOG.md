# CHANGELOG

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