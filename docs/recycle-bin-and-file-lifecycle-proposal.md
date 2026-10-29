# Recycle bin and file lifecycle — proposal

Status: proposal, not implemented. Written 2026-10-28 against `main` at `7e048a5`
(`v1.0.10+uai.14`, `supported = [502, 503]`).

## 1. Why core's recycle bin is not an option

`tool_recyclebin` has exactly two bins, chosen by a `switch` on the context level in its
`index.php` (`course_bin` for activities, `category_bin` for courses), with tables shaped for those
two (`courseid/section/module`, `categoryid/shortname/fullname`) and storage that is a backup file
restored through the backup API. There is no hook, callback or registry for a third-party item
type, and no bin in the system context. Moodle 5.3 is identical. Integrating would need a core
patch. What is worth copying is the model: a retention setting, a scheduled cleanup task, and the
three actions restore / delete permanently / empty.

## 2. What happens to files today

The plugin owns two file areas:

| Area | Context | itemid | Written by |
|---|---|---|---|
| `ogimage` | system or category | page id | `file_postupdate_standard_filemanager()` in `renderer::save_page()` |
| `pagecontent` (category page) | category | page id | `file_save_draft_area_files()` in `renderer::save_page()` |
| `pagecontent` (site page) | system | **0, shared by every site page** (upstream's layout) | same |

Deleting a page is a soft delete (`pages.php`): the row and every file stay, and the file route
refuses them because it reads the row (or, for the shared area, searches live rows' text).

### Orphan classes

| # | Orphan | Cause | Today |
|---|---|---|---|
| O1 | `ogimage` and category `pagecontent` of a deleted page | soft delete never purges | kept forever, unservable (12 files on m502 now) |
| O2 | Shared site `pagecontent` file of a deleted site page | ownership is not recorded, only inferable from text | kept forever, unservable |
| O3 | File removed from a page's text while editing | the editor's draft area still holds it, and nothing prunes it | kept forever; category: unservable only if the page is not viewable; site: unservable |
| O4 | Shared-area file never referenced by any row | same as O3, for every site page at once: each edit's draft contains every site page's files | kept forever, unservable |
| O5 | `{local_page}` rows whose context no longer exists (category deleted; e.g. contextid 999999999 on m502) | core deletes the context and its files; the plugin soft-deletes the rows | row kept forever |
| O6 | `{h5p}` deployment row of a deleted `.h5p` file | core does not remove deployments when a file is deleted | kept forever |

Draft areas of abandoned forms are not orphans: core's cron removes user drafts.
File content is removed from `filedir` by core once no `{files}` row references the hash, so
deleting `{files}` rows is enough.

### A defect found on the way: lost updates in the shared area

`forms/edit.php::set_data()` copies the **whole** itemid-0 area into the editor's draft, and
`file_save_draft_area_files()` makes the target area equal to the draft, deleting what the draft
lacks (`lib/filelib.php`, "delete files not needed any more"). With two editors:

1. A opens site page 1 (draft = files X).
2. B opens site page 2, uploads Y, saves (area = X + Y).
3. A saves page 1 (area = X): **Y is deleted**, and page 2 shows a broken image.

This is upstream's behaviour, and it is a data-loss bug independent of the bin. Any design that
keeps the shared area keeps this bug. It also rules out the core prune helper
(`file_remove_editor_orphaned_files()`) on site pages: it would delete every other page's files.

## 3. Options for the site-wide area

**A. Keep itemid 0, add a garbage collector.** A task deletes shared-area files that no row
references (live or in the bin), using the existing needle matcher, with a grace window so a file
saved a moment before its row is not taken. Upstream-compatible and no URL change. But it keeps the
lost-update bug, keeps the `LIKE` authoriser on every file request, and it is a heuristic: a
reference spelled in a way the matcher does not know is collected.

**B. One page, one area: itemid = page id in both contexts (recommended).** The site-wide layout
becomes the category layout that stages 2–8 already built and tested.

- Upgrade step: for each site page (live or deleted), copy into itemid = id every shared-area file
  its `pagecontent` or `contenthtml` names through the `@@PLUGINFILE@@` placeholder. A file used
  by several pages is copied to each: `create_file_from_storedfile()` adds a `{files}` row with the
  same content hash, no new bytes on disk. Stored text needs no rewrite, because the placeholder
  carries no itemid.
- The shared itemid-0 files are not deleted by the upgrade; nothing serves them any longer, and the
  task in section 4 collects them.
- `pluginfile`: a request is authorised by one row lookup (the page of that id, in the context the
  file is asked through, not deleted, readable by the viewer), the same code in both contexts.
  Itemid 0 names no page and is refused. **Decided by the owner (2026-10-28): no compatibility
  path for old `/0/` URLs.** A literal `.../pagecontent/0/...` URL pasted into a page, a theme or
  another site stops resolving; the editor never stores one. The `LIKE` matcher is removed.
- `pagecontent_itemid()` and `custompage::rewrite_file_urls()` lose their context branch.
- Removes the lost-update bug by construction, and makes O2 and O4 per-page problems.

Cost: a migration that touches stored files, and divergence from upstream in three functions the
fork already rewrote (form, row wrapper, `pluginfile`), so a future upstream merge conflicts only
where it already would.

**C. A reference table (`local_page_file`: pageid ↔ file).** Records ownership without moving files.
More schema, still a shared area that one save overwrites, and the lost-update bug stays. Rejected.

**D. Per-page contexts.** Pages are not context instances; inventing a context level is out of
scope. Rejected.

## 4. Proposed design (B + bin)

### Data

- `{local_page}.timedeleted` (int, default 0). No `deletedby`: a user id would make the plugin a
  personal-data plugin; who deleted is recorded by an event in the log store instead.
- Upgrade: rows with `deleted = 1` get `timedeleted` = upgrade time, so retention starts counting
  at the upgrade and nothing is purged on the first cron.

### Lifecycle

| Action | Row | Files | `/p/` code |
|---|---|---|---|
| Delete | `deleted = 1`, `timedeleted = now`, slug → `<slug>-deleted-<id>` (unchanged) | kept | **kept** (the handler already refuses deleted rows) |
| Restore | `deleted = 0`, `timedeleted = 0`, slug = suffix stripped; if a live page took it, `slug::unique_in_context()` and a notice; under the `local_page/menuname` lock | kept | kept, so printed codes work again |
| Purge (by hand, "empty", or the task) | deleted | the page's `pagecontent` and `ogimage` areas (`delete_area_files`), `.h5p` deployments through `\core_h5p\api::delete_content_from_pluginfile_url()` first | `links::forget()` |
| Save | — | prune the page's area: delete draft files referenced by neither `pagecontent` nor `contenthtml` (own helper; core's prune reads only the editor text and would drop files used by `contenthtml`) | — |

A page whose context no longer exists (O5) is not listed in the bin (it cannot be restored: core
deleted its files with the context) and the task purges it at once.

### Tasks and settings

- `local_page/binexpiry` (`admin_setting_configduration`): **an administrator's setting, default 7
  days (decided by the owner, 2026-10-28)**; 0 keeps deleted pages forever, today's behaviour.
- `\local_page\task\purge_deleted_pages` (daily): purges rows past the expiry and O5 rows.
- `\local_page\task\collect_orphan_files` (weekly): deletes `local_page` file areas whose itemid
  names no row of that context (any context). Item id 0 names no page, so the files the split
  left in the old shared area are collected by the same rule. Exact SQL over `{files}` and
  `{local_page}`, no text heuristics.
- `cli/orphan_files.php --dry-run|--run`: the same collection on demand, printing what it would
  delete. The first run on a production site is a dry run read by the administrator.

### UI and permissions

- `pages.php?contextid=N&view=bin`: deleted pages of that context, with restore, delete
  permanently and empty. Link "Recycle bin (N)" on the list, shown only when N > 0.
- Capability: the one that governs the list (`scope::capability($context)`); the same
  `local_page_page_in_context()` check as delete, `sesskey` on every action, confirmation modal on
  the irreversible two.
- Events: `page_deleted`, `page_restored`, `page_purged` (the plugin has none today).

### Tests and gates

PHPUnit for each lifecycle row above, the migration (shared file used by two pages, by a deleted
page, unreferenced, a name that is a strict prefix of another), item id 0 refused, the prune helper (file kept by `contenthtml` only), and a reproduction of the lost-update race that
is red on today's code. Mutation gates for each guard. One Behat scenario: delete, restore, purge.

## 5. Stages

| Stage | Content | Estimate |
|---|---|---|
| F1 | Unify itemids (option B): upgrade migration, one `pluginfile` branch, form and row wrapper, race test | 1.5 days |
| F2 | Bin: `timedeleted`, restore, purge, UI, events, keep `/p/` | 3 days |
| F3 | Prune on save, retention task, orphan collection task, CLI dry run, H5P cleanup | 1.5 days |

F1 stands alone (it fixes a data-loss bug) and goes first, so the bin and the collector are built
on one file layout. Each stage touches file serving or a state machine: `security-diff-read`
before the push.

## 6. Decisions

Taken by the owner on 2026-10-28:

1. Option B: one area per page in both contexts. F1 starts first.
2. No compatibility path for old itemid-0 URLs.
3. Retention is an administrator's setting, default 7 days.

Still open: whether the lost-update fix also goes upstream (it changes upstream's file layout, so
it is a larger request than the D10 fixes).
