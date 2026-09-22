# Claude instructions for `local_page`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin.

Plugin context: a Moodle **local** plugin ("Custom pages") that lets an
administrator author standalone site pages — HTML body, optional raw Content
HTML, SEO/Open Graph metadata, a publish window, a status and an access level —
and serve them at `/local/page/?id=N` or at a friendly URL built from the page's
`menuname`. It owns one table, `{local_page}`, and two system-context file
areas, `pagecontent` and `ogimage`. It depends on no sibling plugin. Supports
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
  consolidators, critics, estimators.
- the session model — only for work done inline in the main loop, never for a
  subagent.

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
index.php            Public viewer. Resolves ?id= or a friendly menuname, then renders.
pages.php            Admin list of pages (cards), and the delete action.
edit.php             Admin add/edit screen; wraps forms/edit.php.
forms/edit.php       The whole edit form — the largest file in the plugin.
lib.php              Function-only library: the access predicate, pluginfile serving,
                     the anchored needle matcher used to authorise embedded files.
renderer.php         showpage() and the placeholder substitution for page content.
classes/custompage.php      Row wrapper used by the viewer.
classes/url_rewriter.php    Friendly-URL rewriting (pairs with .htaccess).
classes/output/             page_card, page_content, pages_list renderables.
templates/                  Their Mustache counterparts.
db/                         install.xml, upgrade.php, access.php, uninstall.php.
.htaccess            SHIPS in the release zip — it is the friendly-URL feature,
                     not development scaffolding. Never export-ignore it.
```

## Architecture gotchas

- **`local_page_user_can_view_page()` (lib.php) is the whole read-side access
  rule.** Both the renderer (`renderer.php:134`) and the pluginfile callback
  (`lib.php`, via `local_page_user_can_serve_pagecontent_file()`) go through it,
  so a hole there is a file served to someone who should not have it, not a
  display bug. Change it only with `tests/lib_test.php` in front of you.
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
  (`db/access.php:33`), so the editor-preview branch is unreachable for them
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

## Testing notes

- `tests/generator/lib.php` (`local_page_generator::create_page()`) writes the
  row with `insert_record()` rather than through the plugin's save path: the
  save path applies editor and file handling that access-rule tests do not want,
  and the access rules read the stored row. Every column has a default, so a
  test names only the fields it is about.
- `tests/lib_test.php` requires `lib.php` at the top of the file, because
  nothing in a PHPUnit run loads a local plugin's `lib.php` for you.
- There are no Behat features yet. `mdl ci --behat` therefore proves nothing
  here; the fleet's "Behat collected no scenarios" guard is conditional on the
  plugin shipping `.feature` files, so it stays green either way.

## When in doubt

Follow the patterns in existing files, and remember which side of the fork line
a file is on. The codebase is internally consistent — if a new file feels like
it matches no existing shape, re-examine the approach.
