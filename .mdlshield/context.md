# Review context for local_page

`local_page` ("Custom pages") lets an authorised user write standalone pages (an HTML body, an optional raw
Content HTML block, SEO and Open Graph metadata, a publish window, a status and an access level) and serves
them at `/local/page/?id=N`, at a friendly URL built from the page's `menuname`, and, for category pages, at a
routed address. It is a local plugin for Moodle 5.2 with one table, `{local_page}`, and two file areas
(`pagecontent`, `ogimage`) in the system context or in a course category's. It declares no web services, no
scheduled or adhoc tasks and no events, and no dependency on another plugin.

## Fork: upstream versus additions

This repository is a fork of `mczaja/moodle-local_page`, and the README and CHANGELOG say which side is which.
**Site-wide pages are upstream's behaviour**, kept deliberately (their addresses, their trusted and uncleaned
rendering, their `<head>` field), apart from a few security fixes recorded in the CHANGELOG. **Category pages
are this fork's addition**: pages that belong to a course category, their capabilities, trusttext-based
cleaning, the public-category rule for visitors, the routed address and the `/p/<code>` short address, Open Graph
tags rebuilt per page, and a read API (`local\catalogue::for_viewer()`) for other plugins. Upstream files keep
their upstream headers and older style (for example `html_writer`); files added by the fork follow Moodle's
current conventions. Style findings on upstream files are not useful; behaviour findings on either side are.

## Who is trusted

- Site administrators (`moodle/site:config`) are fully trusted and may view any page.
- Three capabilities (`db/access.php`). `local/page:addpages`: system context, `RISK_XSS`, allowed for manager and
  course creator, prevented for the other archetypes; it governs site-wide pages and is, by its own description,
  site-wide content injection. `local/page:managecategorypages` and `local/page:publishcategorypages`: course
  category context, `RISK_SPAM`, manager only, with no clone-permissions, so no upgrade copies them from another
  capability. Authoring a category page and putting it in front of visitors are deliberately separate rights.
- Untrusted: visitors who are not logged in, the guest account, every logged-in user, and any HTML a category
  author writes unless core's trusttext rules say that author was trusted when saving.

## Surfaces

- No web services. Page scripts: `index.php` (the public viewer, reads `id`, `menuname`, or `category` and `page`),
  `pages.php` (admin list and delete: login, guest refused, the capability of the page's context, `require_sesskey`
  on delete) and `edit.php` (login, guest refused, then `local_page_require_editable_page()` checks the capability
  of the page's own context; the form is a moodleform).
- The routed address `/local_page/category/{category}/{slug}` (GET only, no `requirelogin`, so visitors of a public
  category can read) and the `/p/<code>` short address through core's shortlink route.
- `local_page_user_can_view_page()` in `lib.php` is the whole read rule: soft-deleted rows refused, then the
  `moodle/site:config` shortcut, then (category pages, visitors only) the public-category rule, the access level
  (capability names, with `!` for negation, evaluated at the page's own context), the logged-in-only flag, and the
  publish window and status. The viewer and the file route both call it.
- File serving: `local_page_pluginfile()` serves `pagecontent` (a category page's file is authorised by one row lookup
  scoped to the context; the site-wide area, which all sits under item 0, is authorised by searching the pages'
  content for the file's reference) and `ogimage` (gated on publication state only, since scrapers fetch it).
- Content that is rendered rather than escaped: a site-wide page's body and raw Content HTML are written to the
  page uncleaned; a category page's both blocks go through `format_text()` with core's trusttext rules
  and the category's filters. The `<head>` field is emitted only for site-wide pages and only when the
  `additionalhead` setting is on. User placeholders in content (`{firstname}`, `{lastname}`, `{email}`, `{username}`,
  `{idnumber}`, `{city}`, `{country}`, `{fullname}`) come from an allow-list and are escaped with `s()`; a visitor
  or guest gets the site guest account's values.
- Hooks: `before_standard_head_html_generation` (writes the head tags a page registered); callbacks in `lib.php`
  for category deletion (pages are handled by `local\lifecycle`) and for the category settings navigation node.
- Stored data: `{local_page}` has no user column and the privacy provider is a `null_provider`; page text is
  author-written content. A category page's short code is deleted with the page.

## Facts that look like findings but are by design

- **Site-wide pages are uncleaned on purpose.** Their author holds an `RISK_XSS` capability; cleaning would break
  pages a site already serves. A category page being uncleaned for an untrusted author would be a finding.
- **`local_page_ogimage_is_servable()` does not apply the public-category rule** and must not: an Open Graph image is
  fetched anonymously and is gated on publication state alone. The page and its embedded files do apply it.
- **The viewer's guard order is fixed**: a visitor is refused before any lookup unless the category is public, so
  an anonymous request cannot tell an existing id from a missing one. The `?category=&page=` form answers every
  category and slug alike with the same 303 while the router is configured. Reordering it is a finding.
- **"Public" is not this plugin's decision.** `local\publicaccess` asks `local_unlistedcourses` when installed and
  fails closed when it is not, so category pages then reach logged-in users only.
- **Saving without the publish capability forces the page to logged-in only** on the server
  (`local_page_apply_publish_gate()`), whatever the frozen form field posted.
- **The upgrade steps call frozen code in `db/upgradelib.php`**, not live classes, so an old step keeps its meaning.

## De-emphasise

- `docs/**`, `mutations/**`, `lang/**` and `tests/**` carry no production behaviour.
- `README_OUTPUT_API.md` and `styles.css` are documentation and a single stylesheet.
- The optional integration with another theme's content builder (`local_page_xy_simple_content_builder_is_available()`)
  only adds editor snippets.
