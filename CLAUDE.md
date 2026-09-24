# Claude instructions for `local_page`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin.

Plugin context: a Moodle **local** plugin ("Custom pages") that lets an
administrator author standalone site pages — HTML body, optional raw Content
HTML, SEO/Open Graph metadata, a publish window, a status and an access level —
and serve them at `/local/page/?id=N` or at a friendly URL built from the page's
`menuname`. It owns one table, `{local_page}`, and two file areas,
`pagecontent` and `ogimage`, in the system context or a course category's. It
declares no dependency on any sibling plugin; whether a category's pages reach
visitors is asked of `local_unlistedcourses` when it is installed, and the
answer is "no" when it is not (see the viewer gotcha below). Supports
Moodle **5.2 only** (`$plugin->requires = 2026042000`,
`$plugin->supported = [502, 502]`). CI is the moodle-an-hochschulen reusable
workflow, one job per supported branch in `.github/workflows/ci.yml` — **update
that job list when `supported` changes**. Development happens on **m502**; this
repo is mounted into **m502** and **m502b** at `local/page` (see
`~/dev/moodle-dev/plugins.conf`).

## This is a fork, and the fork policy is the first thing to know

Upstream is **`mczaja/moodle-local_page`**, and this repo started at parity with
it. The owner decided (2026-09-22) on a **single patched `main`**, synced from
upstream by **merge, never rebase**:

```sh
git -C ~/dev/moodle-local_page merge upstream/main
```

A rebase would rewrite every one of our commits on top of each upstream release
and make the next merge conflict with itself; the merge history is what keeps
the patches identifiable.

**Fixed conflict resolutions** — decide these the same way every time:

| File | Resolution |
|---|---|
| `version.php` | Keep the **higher** `$plugin->version`, and keep **our** `$plugin->requires` / `$plugin->supported` lines. Append `+uai.N` to upstream's `$plugin->release`. |
| `.github/workflows/ci.yml` | Keep **ours** entirely. Upstream's is a hand-rolled matrix on Moodle 5.01. |
| `.github/workflows/moodle-release.yml` | Stays **deleted**. This fork does not publish to moodle.org; that is the upstream author's job, and the workflow fires on any push touching `version.php`. Delete it again if a merge brings it back. |
| `CHANGELOG.md` | Keep **both**, ours above upstream's, under our own `+uai.N` heading. |
| `lang/en/local_page.php` | Keep both sides' keys, re-sorted alphabetically, and mirror every addition into `lang/pt_br`. |

**Headers follow the file, not the repo.** A file upstream wrote keeps its
upstream header (`@author Marcin Czaja RoseaThemes`, `@copyright 2025 Marcin
Czaja RoseaThemes`) and its existing `html_writer` calls; a file **we** add
carries the fleet header (`@copyright 2026 Anderson Blaine`, no `@author`) and
zero `html_writer`. Keep every edit inside an upstream file minimal and local,
so the next merge has the smallest possible surface to conflict on.

## Agent orchestration budget (fleet rule, repeated here on purpose)

This is section 6 of `~/dev/CLAUDE.md`, mirrored into every repo of the fleet.
It is the one fleet rule these files are allowed to duplicate: a session opened
inside a plugin directory does not always carry the fleet file in context, and
the cost of missing this rule is paid immediately, in tokens, before anyone
notices it was missing.

**Every `Agent` call and every `agent()` inside a Workflow sets `model`
explicitly.** An omitted `model` runs that subagent on the session model — the
most expensive one — and is a defect, not a default:

- `sonnet` — readers, graders, refuters, verifiers, measurers, stale-reference
  sweeps, mechanical renames, test files written against a stated contract.
- `opus` — implementers of non-trivial code, ADR and documentation drafters,
  consolidators, critics, estimators. The alias means the **newest Opus**: since
  2026-09-22 that is Claude Opus 5.5 (`claude-opus-5-5`), measured by asking a
  subagent launched with `model: 'opus'` which model it runs on. Never pin
  `claude-opus-5` or any older Opus id. The `Agent` tool accepts aliases only
  (`sonnet`, `opus`, `haiku`, `fable`); `agent()` in a Workflow accepts an explicit
  id as well, but the alias is what to write — it follows the newest Opus without
  an edit here.
- the session model — only for work done inline in the main loop, never for a
  subagent.
- `effort` is set beside `model` on every call, never inherited: `high` for
  verifiers, readers and refuters, `xhigh` for implementers and fixers (the
  owner's rule of 2026-09-17). An omitted effort inherits the session's, and on
  Opus 5.5 an explicit one matters twice over — that model's own default is
  `medium`, one level below Opus 5.

Multi-agent workflows stay opt-in and lean whatever mode is on: size the fan-out
to the question (roughly 10 to 25 agents), one refuter per finding and only for
blocking findings, no open-ended "investigate every gap" rounds. Stop and resume
with `resumeFromRunId` rather than relaunching, so completed agents stay cached.
State which model each role got when reporting a launch.

Measured 2026-09-02 on the hub category-context gap analysis: 7 lenses x 2
refuters x 2 measurers plus a critic round, every one of them on the session
model, had to be interrupted for cost — 36 agents with the refuters on Sonnet
produced the same verified result. The rule has been restated three times
(2026-09-01, 2026-09-02, 2026-09-04), the last time over implementers launched
without `model` while the reviewers around them were correctly downgraded.

## Commands

```sh
mdl ci moodle-local_page --branch MOODLE_502_STABLE   # the one CI leg this plugin has
mdl ci moodle-local_page --matrix                     # every leg, derived from ci.yml
mdl phpunit m502 local_page                           # targeted tests
mdl purge m502                                        # after PHP changes that affect output
```

There is no `amd/` directory and no build step: the plugin ships no JavaScript
of its own, so `mdl grunt` has nothing to do here. Its only client-side asset is
`styles.css`, which Moodle serves through the theme sheet — and that sheet lags
one request behind an edit on a dev stack, so reload twice before concluding a
rule did not apply.

## Code layout

```
index.php            Public viewer. Hands ?id=, ?menuname= or ?category=&page= to the
                     request class, translates its answer, then renders through
                     local_page_render_view() (lib.php), the body the route renders too.
pages.php            Admin list of pages (cards), and the delete action.
edit.php             Admin add/edit screen; wraps forms/edit.php.
forms/edit.php       The whole edit form — the largest file in the plugin.
lib.php              Function-only library: the access predicate, pluginfile serving,
                     the anchored needle matcher used to authorise embedded files.
renderer.php         showpage() and the placeholder substitution for page content.
classes/custompage.php      Row wrapper used by the viewer.
classes/local/request.php   The viewer's decision, in one order, for every address.
classes/local/links.php     The one place a page's addresses are spelled: script, route, /p/ code.
classes/route/controller/page.php  The routed address /local_page/category/{category}/{slug}.
classes/shortlink_handler.php      Resolves the plugin's /p/ codes for core's shortlink route.
classes/local/publicaccess.php  Fail-closed adapter: is this category public; who is a visitor.
classes/local/scope.php     Which context a page lives in, and which capability governs it.
classes/local/ogimage.php   A page's Open Graph image: which file, its hashed address, its size.
classes/local/lifecycle.php What a category's deletion does to its pages; lib.php's two
                            pre_course_category_* callbacks delegate to it.
classes/local/hook/output/before_standard_head_html_generation.php
                            Writes the head tags a page registered (db/hooks.php).
classes/url_rewriter.php    Friendly-URL rewriting (pairs with .htaccess).
classes/output/             page_card, page_content, pages_list renderables; opengraph, the head
                            tags of a page (Open Graph, SEO name metas, robots, canonical).
templates/                  Their Mustache counterparts; opengraph.mustache is property metas only.
db/                         install.xml, upgrade.php, upgradelib.php (the frozen code the upgrade
                            steps call), access.php, uninstall.php, hooks.php.
.htaccess            SHIPS in the release zip — it is the friendly-URL feature,
                     not development scaffolding. Never export-ignore it.
docs/nginx-runbook.md  The optional production NGINX steps (router fallback, root slugs,
                     category alias, source-disclosure check), each with its check. The
                     README's Friendly URLs section stays the canonical text; keep the two
                     in step. docs/ is export-ignored.
```

## Architecture gotchas

- **`local_page_user_can_view_page()` (lib.php) is the whole read-side access
  rule.** The request class, the renderer (`showpage()`) and the pluginfile
  callback (directly for a category page's files, via
  `local_page_user_can_serve_pagecontent_file()` for the shared site-wide area)
  all go through it, so a hole there is a file served to someone who should not
  have it, not a display bug. Since stage 5 it carries the **public-category
  clause**: a category page is refused to a visitor (nobody logged in, or the
  guest account) unless `\local_page\local\publicaccess` says its category is
  public. That clause is what keeps a private category's embedded files away
  from visitors — the request class refuses them the page, but the file route
  asks this function alone. It is asked after the `moodle/site:config` shortcut
  and never for a logged-in user or a site-wide page. Its optional `$predicate`
  argument exists for tests only. `local_page_ogimage_is_servable()` does NOT
  carry the clause and must not: an og:image is fetched by anonymous scrapers
  and is gated on publication state alone (decision D8). Change either only
  with `tests/lib_test.php` in front of you.
- **The viewer's guard order is one block, and both entries keep it.**
  `\local_page\local\request::category()` refuses a visitor BEFORE any lookup
  unless the category is public, then reads the category's context, then looks
  the page up scoped to it, then applies the page's own rules, then sets up
  `$PAGE` with `set_category_by_id()` first (it throws once a course or a
  category is already set). Never reorder it: the answers a visitor gets would look identical,
  and what would change is that an anonymous request reaches the database
  before it is refused, doing work that differs between ids that exist and ids
  that do not. `request_test` holds the order by counting database statements on
  the refusal path (zero), with a public category as the control that the
  meter sees a lookup. `request::legacy()` (the upstream `?id=` and
  `?menuname=` addresses) can only apply the predicate AFTER its lookup,
  because an id names no category until the row is read. `redirect()` throws
  under PHPUnit with no URL in the message, which is why the class RETURNS its
  target and `index.php` calls `redirect()`; the upstream `require_login()` for
  a page with an access level runs in `local_page_render_view()`, after that
  redirect, on both the script and the route.
- **`\local_page\local\links` is the only place an address is spelled.** The request class, the
  canonical tag, the listing card, the shortlink handler and the Behat step all ask it; a
  `new moodle_url('/local/page/index.php', ...)` written anywhere else is how two parts of the plugin
  come to disagree about where a page lives. It answers the route only while
  `$CFG->routerconfigured` is set — the fleet stacks set it in `config.php`, so PHPUnit and Behat see
  it; `mdl ci` (PHP's built-in server) does not — and every test whose answer depends on it sets it
  explicitly, then empties the DI container, because the router memoises its base path when built.
- **Codes are minted at save, never on a GET.** `renderer::save_page()` calls `links::share()` for a
  category page while the router is configured; the card only reads `links::existing_share()`. Minting
  goes through core's `\core\shortlink::create_public_shortlink()` with no retry around it: core's
  delegated transaction is left undisposed when its insert fails, and catching that on PostgreSQL would
  poison the rest of the request. `pages.php` forgets a page's codes on delete, `db/uninstall.php` on
  uninstall; core deletes nothing from `{shortlink}`.
- **The legacy script's two redirects sit on opposite sides of the rules, on purpose.** The slug form
  (`request::category(..., legacy: true)`) answers a 303 to the route BEFORE any lookup — the same
  redirect for every id and slug, so it tells nobody anything. The `?id=` form (`request::legacy()`)
  answers its 303 only AFTER the page's rules and the predicate: the redirect spells the page's
  category and slug, and handing that to a visitor the page is withheld from names the page. The id
  form `?category=N&id=M` has no route and is still served. index.php cannot choose the status — core's
  `redirect()` always sends 303 — so the answer carries it (`request::$status`) for the controller.
- **In a `route_testcase` the harness's router must be the only one the process builds.** Spelling a
  routed address (the builder, `get_path_for_callable()`, `links::share()`) BEFORE `process_request()`
  builds a second router whose application maps every route onto the first one's collector, and the
  request answers 500. Seed `{shortlink}` rows directly in route tests and spell expected addresses
  after the request. A second request in the same test works once `\core\di::reset_container()` has
  emptied the container (measured here on `page_test`'s three-request oracle test).
- **`publicaccess` fails closed, and nothing declares a dependency.** Without
  `\local_unlistedcourses\category_discoverability` nothing is public, which is
  the state of every CI leg — the plugin is not installed there. Tests that
  need a public category pass `\local_page\tests\public_predicate`
  (`tests/classes/`, autoloaded under PHPUnit only) through the `$predicate`
  argument; the tests that read the real predicate skip themselves, or assert
  only the refusal, where it is missing. It memoises per category id for the
  request and ids repeat between tests, so call its `reset_caches()` first.
- **An access level made only of negations grants the page to everybody.** The
  loop starts at `$canaccess = false`, and `!moodle/site:config` flips it to
  true for anyone who does not hold the capability — which is every visitor,
  anonymous ones included, because administrators already returned true higher
  up. `tests/lib_test.php` asserts this as the *current* behaviour on purpose;
  it is closed at save time, not here.
- **Two `has_capability()` facts from core decide how this predicate behaves for
  visitors who are not logged in.** With `$CFG->forcelogin` on, every capability
  check returns false for user id 0 (`lib/accesslib.php:476`); and guests and
  anonymous visitors can never hold a capability whose captype is `write` or
  whose riskbitmask carries `RISK_XSS`, `RISK_CONFIG` or `RISK_DATALOSS`
  (`lib/accesslib.php:481-485`). `local/page:addpages` is `RISK_XSS`
  (`riskbitmask` at `db/access.php:35`), so the editor-preview branch is unreachable for them
  whatever the role definitions say.
- **`lib.php` and `db/uninstall.php` must NOT carry a `MOODLE_INTERNAL` guard.**
  Both declare functions and nothing else, so the guard is exactly what
  `moodle.Files.MoodleInternal.MoodleInternalNotNeeded` objects to, and under
  `--max-warnings 0` that one warning fails the build. A `phpcs:ignore` to
  silence it was the previous shape; do not bring it back.
- **`mt-6` is a real class on Moodle 5.2 and is used on purpose.** Boost extends
  Bootstrap's spacer map with a sixth step
  (`theme/boost/scss/preset/default.scss:95-103`, `3rem`), so `mt-6` resolves
  and `mt-5` would silently shrink the gap between the status sections to
  `2rem`. Do not "fix" it towards Bootstrap's stock scale.
- **Every `bg-*` on a badge carries a text utility.** Bootstrap 5 defaults badge
  text to white, so `bg-warning` measured 1.95:1 against the 4.5:1 AA floor
  before the pairing was added. Both the PHP (`classes/output/page_card.php`)
  and the Mustache (`templates/page_card.mustache`) emit badges — fix both.
- **Dark mode is `:root[data-bs-theme="dark"]` and nothing else.** Moodle 5.2
  emits no `.theme-dark`, so a rule keyed on it matches nothing. `styles.css`
  reads `--bs-*` tokens with literal fallbacks; never reintroduce a hard-coded
  palette, and never declare a `--mds-*` name (core's namespace).
- **`.htaccess` ships.** It is listed in neither `.gitattributes`'
  `export-ignore` block nor `.gitignore` for that reason.
- **`moodleform` throws the query string away when it picks its own action.**
  With no action argument it posts to `strip_querystring($FULLME)`
  (`lib/formslib.php:201`), which is harmless while every screen is site-wide
  and fatal once a page's context travels only in the URL: a NEW category page's
  editor posted back to a bare `edit.php` and was refused on the wrong capability
  (an existing page carries its id in a hidden field, so it was unaffected). `forms/edit.php`
  names its action, and `MoodleQuickForm` turns a `moodle_url` into hidden
  inputs (`lib/formslib.php:1746`). No unit test of the save path can see this
  class of defect — they call the save path directly, and the request that never
  arrived is the whole bug; `tests/behat/category_pages.feature` is what caught
  it.
- **`db/install.xml` hides an ordering defect in `db/upgrade.php`, and no gate in
  the pipeline can see one.** A fresh install reads the XMLDB file and never
  walks `xmldb_local_page_upgrade()`, so the PHPUnit site, the Behat site and
  every `mdl ci` leg are provisioned straight past whatever order the steps are
  in. The only thing that walks it is a real site coming from an earlier
  release. An upgrade step that READS a column therefore has to be numbered
  above the step that ADDS it: the slug normalisation reads `contextid` and was
  numbered one version below the step adding it, which killed the whole site's
  upgrade with a fatal and left it half applied — underneath a green suite, six
  green static gates and two clean mutation sweeps. Verify any change to the upgrade
  path by running `mdl upgrade` against a stack whose stored version is older
  (`select value from m_config_plugins where plugin='local_page' and
  name='version'`), never by a green test run.
- **Upgrade steps call frozen code in `db/upgradelib.php`, never the plugin's classes.** A step runs
  against the schema of its own version whatever release the site is heading for, so a class method
  that later learns to read a newer column kills every upgrade that starts below the step calling it.
  `local_page_upgrade_normalise_slugs()` is a copy of `slug::normalise_all()` that reads only `id`,
  `menuname`, `deleted` and `contextid`; steps 2026092202 and 2026092210 call it. Never edit a
  function a step calls: a change of behaviour is a new function and a new step, and
  `tests/upgradelib_test.php` (which runs the frozen copy and the class on the same rows and requires
  the same table) then compares the class with the new function. `db/upgrade.php` requires the file
  inside `xmldb_local_page_upgrade()`, so neither file has top-level code or a `MOODLE_INTERNAL` guard.
- **Friendly URLs have one collision rule, `slug::first_free()`, and both paths that rename use it.**
  `normalise_all()` (the upgrade) and `unique_in_context()` (a category move, and the save path naming
  a page saved with no slug) move a page to the first of `-<id>`, `-<id>-2`, `-<id>-3` that is free;
  a slug already ending in `-<id>` gains only the counter. In `normalise_all()` "free" means no row
  claimed it earlier in the pass AND no other live row of the context holds it. Checking only the
  first moved page 3 onto the `x-3` a lower id held, and a second run kept it there: the idempotence
  test passed over a duplicate, because a value ending in the page's own id is the form that page
  moves to. Prove a change here on a fixture where the `-<id>` form is already held, and assert the
  table after the second run, not only its return value.
- **`edit.php` checks the login before it reads a row or a category.** `scope::for_category()` is a
  MUST_EXIST lookup, so an anonymous request reaching it answered an exception for a missing category
  id and the login page for an existing one. `require_login()` with no course sets no course or
  context on `$PAGE`, which is what keeps `set_category_by_id()` the first `set_*()` call after it.
  The last scenario of `tests/behat/category_pages.feature` holds the order.
- **A new `lib.php` callback is invisible on the web until the plugin function
  cache is rebuilt.** `get_plugins_with_function()` — which is how core finds
  `local_page_extend_navigation_category_settings()` — is memoised in MUC, so the
  category node simply does not appear after the function is written. A
  `version.php` bump plus `mdl upgrade m502`, or `mdl purge m502`, is what makes
  it visible. PHPUnit never reads that cache, so the tests pass while the browser
  shows nothing, which is exactly the wrong way round to debug it.
- **The head tags are a renderable registered for a hook, and `$CFG->additionalhtmlhead` is never
  touched.** `local_page_render_view()` ends with `opengraph::set(opengraph::for_page(...))` when the
  viewer may read the page and `opengraph::set(null)` when not, and registers LAST: the hook
  (`before_standard_head_html_generation`, dispatched from `core_renderer::standard_head_html()` for
  every page, error pages included) writes whatever is registered, so nothing that can still throw may
  come after the registration. Upstream appended its tags to `$CFG->additionalhtmlhead` mid-request —
  a site setting — and that must not come back. A change to `db/hooks.php` registers only after a
  `version.php` bump and `mdl upgrade`; PHPUnit tests reach the callback directly and would pass while
  the web shows nothing, so `test_the_callback_is_registered_for_the_head_hook` dispatches through
  `\core\hook\manager::phpunit_get_instance()` on this plugin's own `db/hooks.php`.
- **The mustache lint validates a template as body content**, where `<meta name=...>` and
  `<link rel="canonical">` are invalid and RDFa `<meta property=...>` is not. So the template renders
  the Open Graph property metas only, and the callback writes the name metas (description, keywords,
  author, robots) and the canonical link as literal strings through `s()`. Every value is held in the
  plain spelling (`format_string()` with escape off) and escaped exactly once where it is written; a
  page name with a bare `&` is the fixture that proves it.
- **An og image is only ever a raster whose content is the type its name says.** The file manager's
  `accepted_types` (and the upload repository) judge a file by its NAME, and the file store types it
  by that name, so an SVG saved as `cover.png` arrives typed `image/png`. `ogimage::is_image()` — one
  of the accepted extensions AND core's `stored_file::is_valid_image()` (content type equals stored
  type) — is asked by the editor's `validation()`, the file route and `ogimage::file()`; each call
  site has its own gate. Neither half alone suffices: core counts a genuine SVG as a web image, and
  the name check alone was the original hole. A route test must ask through `lib_test`'s
  `ask_ogimage_route()`, which carries the ETag of the file the route would send: a mutated route
  then answers 304, where it would otherwise write a PNG's binary bytes into the PHPUnit log, which
  BSD grep reads as a binary file — and `mdl mutate` then finds no test count and aborts the sweep.
- **The image's size comes from core's cache, keyed by content hash.** `stored_file::get_imageinfo()`
  caches in `core/file_imageinfo` (`lib/filestorage/file_system.php`), so the plugin keeps no cache of
  its own, and `save_page()` measures nothing: the draft is measured on the way in, under the same
  content hash the stored file gets, by core's validation of a restricted file manager
  (`file_get_all_files_in_draftarea()`) and by the form's content check (`is_valid_image()`). A
  save-time measurement was written, swept silent (the `og_measure_at_save` gate reddened nothing) and
  removed; `save_page_test` holds the property. A render after a purge measures on the spot. The image ADDRESS carries the content hash as a directory segment; the file
  route accepts it or upstream's flat address, never compares it with the file (a cache key, not a
  credential) and refuses any other segment. Only a refusal can be asserted through
  `local_page_pluginfile()` in general — a served file reaches `readfile_accel()` — except with the
  file's own ETag in `If-None-Match`, which answers 304 before a byte is written; `lib_test` uses that,
  with the CLI's header warnings swallowed for the duration of the call.

- **Core deletes the category's CONTEXT in both of its delete paths, so the files move inside the
  callback or not at all.** `delete_full()` and `delete_move()` (`course/classes/category.php`) call
  `local_page_pre_course_category_delete()` / `..._delete_move()` before anything else and end with
  `$context->delete()`, which purges every component's files there. `lifecycle::category_moved()`
  therefore moves both areas (`move_area_files_to_new_context()`, item id = page id) BEFORE it
  re-points the row, and re-checks the slug in the new context under the save path's `menuname`
  lock. A child category's pages are never the parent callback's business: `delete_full()` recurses
  and calls the callback for each child itself, and `delete_move()` re-parents children with their own
  contexts. A move to the root is refused (`movecatcontentstoroot`, core's own WS error) while the
  category holds a live page — core cannot complete that move anyway: it dies resolving the root's
  category context after the callbacks. The callbacks are found through `get_plugins_with_function()`,
  so a change to them needs the version bump and `mdl upgrade` like any other `lib.php` callback.
- **A move that fails part-way is not rolled back, and that is the accepted behaviour** (the one
  minor finding of the stage-8 review, documented in `lifecycle::category_moved()`). The loop opens
  no transaction and `course/management.php` calls `delete_move()` outside one, so a database failure
  between pages leaves the pages already handled in the new parent with their files, the rest in the
  old category with theirs, and the category itself in place (the exception stops `delete_move()`
  before core changes anything). Running the move again completes it — a carried page no longer
  names the old context, and an area whose files already moved is empty. One window is narrower, and
  it is the one a claim of "re-running always completes it" misses: `move_area_files_to_new_context()`
  copies every file and only then deletes the originals, so a failure inside that copy leaves copies
  in the new context beside intact originals, and the next run stops on that page with a
  `stored_file_creation_exception` (duplicate `pathnamehash`). Deleting that page's copies from the
  new context — `get_file_storage()->delete_area_files(<new context id>, 'local_page', <area>,
  <page id>)`, the originals are still in the old one — lets the next run complete. Both cases were
  measured on m502 on 2026-09-23 by calling the class directly on probe categories. Core's web
  service `core_course_delete_categories` wraps the whole deletion in one delegated transaction, so
  none of this arises through it. Making the copy idempotent (skip a file the target already holds)
  would close the window; it was left out because stage 9 changes no code.
- **Other plugins declare the same category callbacks and run first.** `local_dimensions`
  (alphabetically before `local_page`) implements `pre_course_category_delete_move()` on the fleet
  stacks and dies on a root target with a database error of its own. `lib_test`'s root test therefore
  deletes through a category built like core's `category_hooks_test` mock — real in everything but
  `get_plugins_callback_function()`, which names this plugin's callbacks alone. The other lifecycle
  tests go through the unmocked class, so they also prove the plugin coexists with whatever else the
  stack has installed.

## Testing notes

- `tests/generator/lib.php` (`local_page_generator::create_page()`) writes the
  row with `insert_record()` rather than through the plugin's save path: the
  save path applies editor and file handling that access-rule tests do not want,
  and the access rules read the stored row. Every column has a default, so a
  test names only the fields it is about.
- `tests/lib_test.php` requires `lib.php` at the top of the file, because
  nothing in a PHPUnit run loads a local plugin's `lib.php` for you.
- Two Behat features, both tagged `@local @local_page` and run with
  `mdl behat m502 @local_page`, all scenarios deliberately non-JavaScript.
  `tests/behat/category_pages.feature` walks a category manager from the
  category page to the pages screen and back, which is the one path no unit
  test can assert because it is made of links, and sends a visitor to the
  editor of a category that does not exist and of one that does (the login
  page both times). `tests/behat/anonymous_viewer.feature`
  runs with `forcelogin` switched on in its Background — the production state,
  stated rather than assumed — and holds three things: a visitor still reads a
  site-wide page, a visitor asking for a category page meets the login page and
  is brought back to the page after logging in, and an administrator reads that
  same page — then the last two again at the routed address. There is no
  "public category renders for a visitor" scenario on purpose: "public" comes
  from `local_unlistedcourses`, which the CI matrix does not install, so that
  control is PHPUnit's (`request_test` with the predicate double, and
  `route/controller/page_test` with the real predicate, skipped where it is
  absent). `tests/generator/behat_local_page_generator.php` creates
  `"local_page > pages"` (a `category` column takes a category idnumber), and
  `tests/behat/behat_local_page.php` visits a page at its category address,
  whose category id Behat cannot compute. Its "routed page" step asks
  `links::category_page()` in the Behat process rather than spelling
  `/local_page/category/...`: the fleet stacks' Behat site has the router, the
  matrix's `php -S` site has neither a rewrite nor `routerconfigured`, and the
  builder answers each with the address it serves. That context file must never
  carry a `MOODLE_INTERNAL` guard. `mdl ci --behat` therefore proves something here, and
  the fleet's "Behat collected no scenarios" guard is live for this plugin.

## When in doubt

Follow the patterns in existing files, and remember which side of the fork line
a file is on. The codebase is internally consistent — if a new file feels like
it matches no existing shape, re-examine the approach.

## State of the fork (2026-09-23)

The category-pages series (`1.0.10+uai.1` to `uai.9`) is merged: pull request #1
took the stacked branches `stage-0-fleet-onboarding` (`90e75b3`) ->
`stage-1-security` (`909f8b4`) -> `stage-2-data-model` (`afc026f`) ->
`stage-3-trust-publish` (`0360cf9`) -> `stage-4-authoring` (`961505b`, then
`30f2fa8`, the fleet-rule mirror) -> `stage-5-viewer` (`2028da4`) ->
`stage-6-addresses` (`12d88df`) -> `stage-7-opengraph` (`ba36f38`) ->
`stage-8-lifecycle` (`10e3011`) -> `stage-9-adoption` (`f225a45`) into `main`
(merge `8d7392c`, `[skip ci]`; the gate was the local matrix). The stage branches
stay on the remote as the record of the series; `stage-2` and `stage-3` carry the
broken upgrade order fixed in stage 4, so never deploy one of them to a real site.

Two branches follow it:

- `comments-audit`: the comment audit (`89440e5`, comment lines only) and the
  five code findings it raised, fixed as `1.0.10+uai.10` (two upgrade steps, the
  frozen `db/upgradelib.php`, the shared slug collision rule, the editor's
  login-before-lookup order). It is the tip of the fork.
- `upstream-security-fixes` (on `cf3df54`, upstream's own `main`, unchanged since
  the fork): the seven security fixes worth offering upstream, one commit each,
  release `v1.0.11` unreleased, green on the legs upstream's own `ci.yml` runs.
  The upstream pull request is opened only when the owner says so (decision D10).

There is never a release tag (D11): installs come from git, syncs from upstream
by merge. The theme follow-ups T1-T3 were deferred by decision D13. Production
adoption follows the README's *Adopting category pages* checklist; the plugin is
not installed at FUNDASEG yet.
