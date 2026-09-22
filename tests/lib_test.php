<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the page visibility predicate in local/page/lib.php.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');

/**
 * Tests for local_page_user_can_view_page().
 *
 * This one function is the plugin's whole read-side access rule: the public
 * renderer calls it before showing a page, and the pluginfile callback calls it
 * before serving a file that a page embeds. So a hole here is not a display
 * bug, it is a file served to someone who should not have it.
 *
 * Two facts about core decide how these tests are written, both measured
 * against MOODLE_502_STABLE:
 *
 * - has_capability() returns false for every capability when the visitor is not
 *   logged in and $CFG->forcelogin is on (lib/accesslib.php:476). The predicate
 *   never reads forcelogin itself, so the anonymous cases are asserted with it
 *   both on and off; they must agree.
 * - guests and anonymous visitors can never hold a capability whose captype is
 *   'write' or whose riskbitmask carries RISK_XSS, RISK_CONFIG or RISK_DATALOSS
 *   (lib/accesslib.php:481-485), whatever the role definitions say.
 *   local/page:addpages is RISK_XSS (db/access.php:33), so the editor-preview
 *   branch is unreachable for them by construction.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_page_user_can_view_page')]
#[CoversFunction('local_page_publish_window_is_open')]
#[CoversFunction('local_page_ogimage_is_servable')]
#[CoversFunction('local_page_require_editable_page')]
#[CoversFunction('local_page_pluginfile')]
#[CoversFunction('local_page_pages_referencing_pagecontent_file')]
#[CoversFunction('local_page_user_can_serve_pagecontent_file')]
#[CoversFunction('local_page_page_in_context')]
#[CoversFunction('local_page_save_target_context')]
final class lib_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A fresh user holding exactly one capability at the system context.
     *
     * @param string $capability Capability name, e.g. local/page:addpages.
     * @return \stdClass The user record.
     */
    private function user_holding(string $capability): \stdClass {
        return $this->user_holding_at($capability, \context_system::instance());
    }

    /**
     * A fresh user holding exactly one capability, at one context and nowhere else.
     *
     * The context matters from this stage on: a manager of one category must not be able to
     * preview or edit another category's pages, and the only way to assert that is to grant the
     * capability where it is meant to apply rather than site-wide.
     *
     * @param string $capability Capability name, e.g. local/page:managecategorypages.
     * @param \core\context $context Context to grant and assign the role at.
     * @return \stdClass The user record.
     */
    private function user_holding_at(string $capability, \core\context $context): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->create_role_capability(
            $roleid,
            [$capability => 'allow'],
            $context
        );
        role_assign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $user;
    }

    /**
     * A row that was never persisted is not viewable, whoever is asking.
     *
     * @return void
     */
    public function test_a_page_without_an_id_is_never_viewable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page();

        /*
         * Control: the very same row IS viewable once it carries its id, so the
         * two assertions below are the id check and not some other refusal.
         */
        $this->assertTrue(\local_page_user_can_view_page($page));

        $unsaved = clone $page;
        unset($unsaved->id);
        $this->assertFalse(\local_page_user_can_view_page($unsaved));

        $zeroid = clone $page;
        $zeroid->id = 0;
        $this->assertFalse(\local_page_user_can_view_page($zeroid));
    }

    /**
     * Soft-deleted rows stay invisible even to a site administrator.
     *
     * @return void
     */
    public function test_a_soft_deleted_page_is_not_viewable_even_by_an_admin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $live = $this->pages()->create_page();
        $deleted = $this->pages()->create_page(['deleted' => 1]);

        /*
         * Control: the administrator short-circuit really is in force for this
         * session, so the refusal below is the deleted flag beating it.
         */
        $this->assertTrue(\local_page_user_can_view_page($live));
        $this->assertFalse(\local_page_user_can_view_page($deleted));
    }

    /**
     * Status and publish-window combinations as an ordinary logged-in user sees them.
     *
     * An offset of 0 means the date field is left at 0, which the predicate
     * reads as "no bound"; any other value is an offset from now.
     *
     * @return array[] status, pagedate offset, enddate offset, expected result.
     */
    public static function status_and_window_provider(): array {
        return [
            'live, no window' => ['live', 0, 0, true],
            'draft, no window' => ['draft', 0, 0, false],
            'archived, no window' => ['archived', 0, 0, false],
            'live, already started' => ['live', -HOURSECS, 0, true],
            'live, starts later' => ['live', HOURSECS, 0, false],
            'live, already ended' => ['live', 0, -HOURSECS, false],
            'live, ends later' => ['live', 0, HOURSECS, true],
            'live, inside the window' => ['live', -HOURSECS, HOURSECS, true],
            'live, before the window' => ['live', HOURSECS, 2 * HOURSECS, false],
            'live, after the window' => ['live', -2 * HOURSECS, -HOURSECS, false],
            'draft, inside the window' => ['draft', -HOURSECS, HOURSECS, false],
            'archived, inside the window' => ['archived', -HOURSECS, HOURSECS, false],
        ];
    }

    /**
     * An ordinary user sees live pages inside their publish window and nothing else.
     *
     * @param string $status Stored status value.
     * @param int $startoffset Offset from now for pagedate, or 0 for no bound.
     * @param int $endoffset Offset from now for enddate, or 0 for no bound.
     * @param bool $expected Whether the page should be viewable.
     * @return void
     */
    #[DataProvider('status_and_window_provider')]
    public function test_status_and_publish_window_for_a_plain_user(
        string $status,
        int $startoffset,
        int $endoffset,
        bool $expected
    ): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $now = time();
        $page = $this->pages()->create_page([
            'status' => $status,
            'pagedate' => $startoffset === 0 ? 0 : $now + $startoffset,
            'enddate' => $endoffset === 0 ? 0 : $now + $endoffset,
        ]);

        $this->assertSame($expected, \local_page_user_can_view_page($page));
    }

    /**
     * onlyloggedin shuts out anonymous visitors and the guest account only.
     *
     * @return void
     */
    public function test_onlyloggedin_gates_anonymous_and_guest_visitors(): void {
        global $CFG;

        $this->resetAfterTest();

        $open = $this->pages()->create_page(['onlyloggedin' => 0]);
        $gated = $this->pages()->create_page(['onlyloggedin' => 1]);

        /*
         * forcelogin is the site-wide gate, not this predicate's, and the
         * predicate never reads it — but it does change what has_capability()
         * answers for user id 0, so the anonymous result is pinned with it both
         * off and on rather than in whichever state the test site happened to
         * be left in.
         */
        foreach ([0, 1] as $forcelogin) {
            $CFG->forcelogin = $forcelogin;
            $this->setUser(null);
            $this->assertTrue(
                \local_page_user_can_view_page($open),
                "anonymous, forcelogin={$forcelogin}: open page"
            );
            $this->assertFalse(
                \local_page_user_can_view_page($gated),
                "anonymous, forcelogin={$forcelogin}: gated page"
            );
        }
        $CFG->forcelogin = 0;

        $this->setGuestUser();
        $this->assertTrue(\local_page_user_can_view_page($open), 'guest: open page');
        $this->assertFalse(\local_page_user_can_view_page($gated), 'guest: gated page');

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(\local_page_user_can_view_page($open), 'user: open page');
        $this->assertTrue(\local_page_user_can_view_page($gated), 'user: gated page');
    }

    /**
     * A holder of local/page:addpages previews unpublished content.
     *
     * @return void
     */
    public function test_addpages_holder_previews_draft_archived_and_out_of_window_pages(): void {
        $this->resetAfterTest();

        $now = time();
        $unpublished = [
            'draft' => $this->pages()->create_page(['status' => 'draft']),
            'archived' => $this->pages()->create_page(['status' => 'archived']),
            'not started' => $this->pages()->create_page(['pagedate' => $now + DAYSECS]),
            'expired' => $this->pages()->create_page(['enddate' => $now - DAYSECS]),
        ];

        /*
         * Control: an ordinary user sees none of them, so what the holder sees
         * below is the capability and not a row that was viewable all along.
         */
        $this->setUser($this->getDataGenerator()->create_user());
        foreach ($unpublished as $label => $page) {
            $this->assertFalse(\local_page_user_can_view_page($page), "plain user: {$label}");
        }

        $this->setUser($this->user_holding('local/page:addpages'));
        foreach ($unpublished as $label => $page) {
            $this->assertTrue(\local_page_user_can_view_page($page), "editor: {$label}");
        }

        /*
         * The preview branch still applies the access level: it returns
         * canaccess, it does not bypass it.
         */
        $restricted = $this->pages()->create_page(['accesslevel' => 'moodle/site:config']);
        $this->assertFalse(\local_page_user_can_view_page($restricted), 'editor: restricted page');
    }

    /**
     * A site administrator sees every page that has not been deleted.
     *
     * @return void
     */
    public function test_an_admin_sees_every_page_that_is_not_deleted(): void {
        global $CFG;

        $this->resetAfterTest();

        $now = time();
        $hidden = [
            'draft' => $this->pages()->create_page(['status' => 'draft']),
            'archived' => $this->pages()->create_page(['status' => 'archived']),
            'not started' => $this->pages()->create_page(['pagedate' => $now + DAYSECS]),
            'expired' => $this->pages()->create_page(['enddate' => $now - DAYSECS]),
            'logged in only' => $this->pages()->create_page(['onlyloggedin' => 1]),
            'restricted' => $this->pages()->create_page(['accesslevel' => 'moodle/site:config']),
        ];

        /*
         * Control: every one of these is refused to an anonymous visitor, so the
         * administrator's result is the moodle/site:config short-circuit.
         */
        $CFG->forcelogin = 0;
        $this->setUser(null);
        foreach ($hidden as $label => $page) {
            $this->assertFalse(\local_page_user_can_view_page($page), "anonymous: {$label}");
        }

        $this->setAdminUser();
        foreach ($hidden as $label => $page) {
            $this->assertTrue(\local_page_user_can_view_page($page), "admin: {$label}");
        }
    }

    /**
     * moodle/site:config alone admits the holder, by its own branch.
     *
     * @return void
     */
    public function test_site_config_alone_short_circuits_every_other_rule(): void {
        $this->resetAfterTest();

        $draft = $this->pages()->create_page(['status' => 'draft']);

        /*
         * The holder is given moodle/site:config and NOTHING else - in
         * particular not local/page:addpages. That separation is the whole
         * point: a site administrator satisfies the editor-preview branch as
         * well, so the admin test above passes whether or not the site:config
         * short-circuit is still there. This one goes red if it is removed.
         */
        $this->setUser($this->user_holding('moodle/site:config'));
        $this->assertTrue(\local_page_user_can_view_page($draft), 'site:config holder');

        // Control: the same row is refused to a user without that capability.
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(\local_page_user_can_view_page($draft), 'plain user');
    }

    /**
     * A positive access level admits only holders of the named capability.
     *
     * @return void
     */
    public function test_a_positive_access_level_requires_the_named_capability(): void {
        $this->resetAfterTest();

        $page = $this->pages()->create_page(['accesslevel' => 'moodle/site:viewparticipants']);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(\local_page_user_can_view_page($page), 'user without the capability');

        $this->setUser($this->user_holding('moodle/site:viewparticipants'));
        $this->assertTrue(\local_page_user_can_view_page($page), 'user holding the capability');
    }

    /**
     * Several access levels are an OR list, and each entry is trimmed.
     *
     * @return void
     */
    public function test_access_levels_are_an_or_list_of_trimmed_entries(): void {
        $this->resetAfterTest();

        /*
         * Note the space after the comma: the entries are trimmed before use, so
         * the second one has to match despite it.
         */
        $page = $this->pages()->create_page([
            'accesslevel' => 'moodle/site:config, moodle/site:viewparticipants',
        ]);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(\local_page_user_can_view_page($page), 'user holding neither');

        $this->setUser($this->user_holding('moodle/site:viewparticipants'));
        $this->assertTrue(\local_page_user_can_view_page($page), 'user holding the second');
    }

    /**
     * A negation-only access level currently admits everybody.
     *
     * @return void
     */
    public function test_a_negation_only_access_level_currently_grants_everyone(): void {
        global $CFG;

        $this->resetAfterTest();

        /*
         * '!moodle/site:config' reads as "everyone except site administrators",
         * and that is what the loop does: it starts at $canaccess = false and a
         * negated entry flips it to true for anyone who does NOT hold the
         * capability. Since an administrator already returned true further up,
         * the entry ends up granting the page to every visitor there is,
         * anonymous ones included.
         *
         * These assertions record the CURRENT behaviour, not the desired one.
         * Stage 1 closes this at SAVE time, by refusing an access level made up
         * of negations only; the predicate keeps reading already-stored rows
         * exactly as it does today, so this test must keep passing afterwards.
         */
        $page = $this->pages()->create_page(['accesslevel' => '!moodle/site:config']);

        $CFG->forcelogin = 0;
        $this->setUser(null);
        $this->assertTrue(\local_page_user_can_view_page($page), 'anonymous visitor');

        $this->setGuestUser();
        $this->assertTrue(\local_page_user_can_view_page($page), 'guest');

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(\local_page_user_can_view_page($page), 'plain user');

        /*
         * The administrator gets in by the site:config short-circuit above the
         * access-level loop, not by this rule.
         */
        $this->setAdminUser();
        $this->assertTrue(\local_page_user_can_view_page($page), 'admin');

        /*
         * Control: the same capability written WITHOUT the negation refuses the
         * same anonymous visitor, so the four assertions above are the negation
         * branch and not an access level being ignored altogether.
         */
        $positive = $this->pages()->create_page(['accesslevel' => 'moodle/site:config']);
        $this->setUser(null);
        $this->assertFalse(\local_page_user_can_view_page($positive), 'anonymous, positive entry');
    }

    /**
     * Stores one file in the ogimage area under a page's id.
     *
     * @param int $pageid Page id, which is the itemid of the ogimage area.
     * @param string $filename File name to store.
     * @param int|null $contextid Context to store it in; the system context when omitted.
     * @return void
     */
    private function store_ogimage(int $pageid, string $filename, ?int $contextid = null): void {
        get_file_storage()->create_file_from_string(
            [
                'contextid' => $contextid ?? \context_system::instance()->id,
                'component' => 'local_page',
                'filearea' => 'ogimage',
                'itemid' => $pageid,
                'filepath' => '/',
                'filename' => $filename,
            ],
            'not really a png'
        );
    }

    /**
     * Stores one file in the pagecontent area of a context, under an item id.
     *
     * @param int $contextid Context to store it in.
     * @param int $itemid Item id: 0 for the shared site-wide area, the page id for a category page.
     * @param string $filename File name to store.
     * @return void
     */
    private function store_pagecontent_file(int $contextid, int $itemid, string $filename): void {
        get_file_storage()->create_file_from_string(
            [
                'contextid' => $contextid,
                'component' => 'local_page',
                'filearea' => 'pagecontent',
                'itemid' => $itemid,
                'filepath' => '/',
                'filename' => $filename,
            ],
            'not really a png'
        );
    }

    /**
     * Publication states and whether the og:image of such a page may be served.
     *
     * A date offset of 0 leaves the field at 0, which means "no bound"; any other value is an
     * offset from now.
     *
     * @return array[] status, pagedate offset, enddate offset, deleted flag, expected result.
     */
    public static function ogimage_servability_provider(): array {
        return [
            'live, no window' => ['live', 0, 0, 0, true],
            'live, inside its window' => ['live', -HOURSECS, HOURSECS, 0, true],
            'draft' => ['draft', 0, 0, 0, false],
            'archived' => ['archived', 0, 0, 0, false],
            'deleted' => ['live', 0, 0, 1, false],
            'not yet started' => ['live', HOURSECS, 0, 0, false],
            'expired' => ['live', 0, -HOURSECS, 0, false],
            'draft inside a window' => ['draft', -HOURSECS, HOURSECS, 0, false],
        ];
    }

    /**
     * The og:image gate answers the same to an anonymous scraper and to an administrator.
     *
     * The anonymous leg runs under forcelogin, which is the state a link-preview fetch actually
     * arrives in on a closed site, and which makes every has_capability() call answer false. The
     * two legs must agree, because the gate is not supposed to read a capability at all.
     *
     * @param string $status Stored status value.
     * @param int $startoffset Offset from now for pagedate, or 0 for no bound.
     * @param int $endoffset Offset from now for enddate, or 0 for no bound.
     * @param int $deleted Soft-delete flag.
     * @param bool $expected Whether the image should be servable.
     * @return void
     */
    #[DataProvider('ogimage_servability_provider')]
    public function test_ogimage_servability_by_publication_state(
        string $status,
        int $startoffset,
        int $endoffset,
        int $deleted,
        bool $expected
    ): void {
        global $CFG;

        $this->resetAfterTest();

        $now = time();
        $page = $this->pages()->create_page([
            'status' => $status,
            'pagedate' => $startoffset === 0 ? 0 : $now + $startoffset,
            'enddate' => $endoffset === 0 ? 0 : $now + $endoffset,
            'deleted' => $deleted,
        ]);

        $CFG->forcelogin = 1;
        $this->setUser(null);
        $this->assertSame($expected, \local_page_ogimage_is_servable($page), 'anonymous under forcelogin');

        $CFG->forcelogin = 0;
        $this->setAdminUser();
        $this->assertSame($expected, \local_page_ogimage_is_servable($page), 'administrator');
    }

    /**
     * A row that was never persisted has no servable image.
     *
     * @return void
     */
    public function test_ogimage_servability_needs_a_persisted_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page();

        // Control: the very same row IS servable while it carries its id.
        $this->assertTrue(\local_page_ogimage_is_servable($page));

        $unsaved = clone $page;
        $unsaved->id = 0;
        $this->assertFalse(\local_page_ogimage_is_servable($unsaved));
    }

    /**
     * Restrictions on the READER leave the image servable; the viewer predicate is the control.
     *
     * This is the difference the gate exists to express. A scraper fetching an og:image is
     * anonymous, so gating the image on onlyloggedin or on a capability would break every link
     * preview; and it would protect nothing, because index.php emits the og:image tag only after
     * the viewer has passed local_page_user_can_view_page(). The control asserts exactly that: the
     * same rows are refused by the viewer predicate to the same anonymous visitor.
     *
     * @return void
     */
    public function test_the_ogimage_gate_ignores_restrictions_on_the_reader(): void {
        global $CFG;

        $this->resetAfterTest();

        $restricted = [
            'logged in only' => $this->pages()->create_page(['onlyloggedin' => 1]),
            'capability restricted' => $this->pages()->create_page(['accesslevel' => 'moodle/site:config']),
        ];

        $CFG->forcelogin = 1;
        $this->setUser(null);

        foreach ($restricted as $label => $page) {
            $this->assertTrue(\local_page_ogimage_is_servable($page), "ogimage: {$label}");
            // Control: the reader-facing predicate refuses this very row to this very visitor.
            $this->assertFalse(\local_page_user_can_view_page($page), "viewer: {$label}");
        }
    }

    /**
     * pluginfile.php will not hand out the og:image of a page that is not published.
     *
     * The refusal is asserted rather than the positive serve on purpose: serving a file reaches
     * readfile_accel(), whose `while (ob_get_level())` loop closes every output buffer there is —
     * PHPUnit's included — before writing the bytes to stdout. So the positive direction is held
     * by the predicate tests above, and the control here is that the file really is in the area.
     *
     * @return void
     */
    public function test_the_ogimage_pluginfile_branch_refuses_unpublished_pages(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $context = \context_system::instance();
        $fs = get_file_storage();

        $refused = [
            'draft' => $this->pages()->create_page(['status' => 'draft']),
            'archived' => $this->pages()->create_page(['status' => 'archived']),
            'deleted' => $this->pages()->create_page(['deleted' => 1]),
            'expired' => $this->pages()->create_page(['enddate' => time() - DAYSECS]),
        ];

        foreach ($refused as $label => $page) {
            $this->store_ogimage((int) $page->id, 'og.png');

            /*
             * Control: the file really is stored under that itemid, so what the call below runs
             * into is the servability gate and not a missing file.
             */
            $this->assertNotEmpty(
                $fs->get_file($context->id, 'local_page', 'ogimage', (int) $page->id, '/', 'og.png'),
                "{$label}: the fixture file is in the area"
            );

            $this->assertFalse(
                \local_page_pluginfile(
                    null,
                    null,
                    $context,
                    'ogimage',
                    [(int) $page->id, 'og.png'],
                    false,
                    ['dontdie' => true]
                ),
                "{$label}: pluginfile refuses the image"
            );
        }

        // An itemid naming no row at all is refused before the file store is consulted.
        $this->assertFalse(
            \local_page_pluginfile(
                null,
                null,
                $context,
                'ogimage',
                [(int) $refused['draft']->id + 100000, 'og.png'],
                false,
                ['dontdie' => true]
            ),
            'unknown itemid'
        );
    }

    /**
     * The write-path re-check hands back the stored row for a holder of the editing capability.
     *
     * @return void
     */
    public function test_require_editable_page_returns_the_stored_row(): void {
        $this->resetAfterTest();
        $this->setUser($this->user_holding('local/page:addpages'));

        $page = $this->pages()->create_page();

        $row = \local_page_require_editable_page((int) $page->id);
        $this->assertNotNull($row);
        $this->assertSame((int) $page->id, (int) $row->id);

        // A new page has no row to read, so only the capability is checked.
        $this->assertNull(\local_page_require_editable_page(0));
    }

    /**
     * A missing or soft-deleted id is refused, so a deleted page cannot be edited back to life.
     *
     * @return void
     */
    public function test_require_editable_page_refuses_a_missing_or_deleted_row(): void {
        $this->resetAfterTest();
        $this->setUser($this->user_holding('local/page:addpages'));

        $live = $this->pages()->create_page();
        $deleted = $this->pages()->create_page(['deleted' => 1]);

        /*
         * Control: the same call succeeds for a live row in this very session, so the two refusals
         * below are the row lookup and not the capability check behind it.
         */
        $this->assertNotNull(\local_page_require_editable_page((int) $live->id));

        $cases = [
            'missing' => (int) $live->id + 100000,
            'deleted' => (int) $deleted->id,
        ];

        foreach ($cases as $label => $id) {
            try {
                \local_page_require_editable_page($id);
                $this->fail("{$label}: expected the call to throw");
            } catch (\moodle_exception $e) {
                $this->assertSame('pagenotfound', $e->errorcode, $label);
            }
        }
    }

    /**
     * The write-path re-check demands the editing capability, for an existing and a new page alike.
     *
     * @return void
     */
    public function test_require_editable_page_demands_the_editing_capability(): void {
        $this->resetAfterTest();

        $page = $this->pages()->create_page();

        /*
         * Control: a holder gets the row and a null, so what the plain user meets below is the
         * capability and not something else about the call.
         */
        $this->setUser($this->user_holding('local/page:addpages'));
        $this->assertNotNull(\local_page_require_editable_page((int) $page->id));
        $this->assertNull(\local_page_require_editable_page(0));

        $this->setUser($this->getDataGenerator()->create_user());

        $refusals = 0;
        foreach (['existing page' => (int) $page->id, 'new page' => 0] as $label => $id) {
            try {
                \local_page_require_editable_page($id);
            } catch (\required_capability_exception $e) {
                $refusals++;
                continue;
            }
            $this->fail("{$label}: expected the call to throw");
        }

        $this->assertSame(2, $refusals);
    }
    /**
     * The editor-preview branch is evaluated at the page's OWN context.
     *
     * Previewing unpublished content is the editing right seen from the reader's side, so it has
     * to follow the same split the editing capabilities do: whoever authors a category's pages
     * previews that category's drafts, and nobody else's.
     *
     * @return void
     */
    public function test_the_preview_branch_is_evaluated_at_the_pages_own_context(): void {
        $this->resetAfterTest();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $ctxa = \core\context\coursecat::instance($cata->id);

        $draftina = $this->pages()->create_category_page((int) $cata->id, ['status' => 'draft']);
        $draftinb = $this->pages()->create_category_page((int) $catb->id, ['status' => 'draft']);
        $sitedraft = $this->pages()->create_page(['status' => 'draft']);

        $this->setUser($this->user_holding_at('local/page:managecategorypages', $ctxa));
        $this->assertTrue(\local_page_user_can_view_page($draftina), 'category manager: own category');
        $this->assertFalse(\local_page_user_can_view_page($draftinb), 'category manager: sibling category');
        $this->assertFalse(\local_page_user_can_view_page($sitedraft), 'category manager: site-wide page');

        /*
         * Control, and the mirror image of the three assertions above: the site-wide editor sees
         * the site-wide draft and NOT the category one. Without this half, a predicate that simply
         * refused every category page would pass the first three.
         */
        $this->setUser($this->user_holding('local/page:addpages'));
        $this->assertTrue(\local_page_user_can_view_page($sitedraft), 'site editor: site-wide page');
        $this->assertFalse(\local_page_user_can_view_page($draftina), 'site editor: category page');
    }

    /**
     * The write-path re-check demands the capability of the row's own context.
     *
     * @return void
     */
    public function test_require_editable_page_is_scoped_to_the_pages_own_context(): void {
        $this->resetAfterTest();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $ctxa = \core\context\coursecat::instance($cata->id);

        $pagea = $this->pages()->create_category_page((int) $cata->id);
        $pageb = $this->pages()->create_category_page((int) $catb->id);
        $sitepage = $this->pages()->create_page();

        $this->setUser($this->user_holding_at('local/page:managecategorypages', $ctxa));

        // Control: the manager of category A may edit category A's page.
        $row = \local_page_require_editable_page((int) $pagea->id);
        $this->assertNotNull($row);
        $this->assertSame((int) $pagea->id, (int) $row->id);

        $refused = ['sibling category' => (int) $pageb->id, 'site-wide page' => (int) $sitepage->id];
        foreach ($refused as $label => $pageid) {
            try {
                \local_page_require_editable_page($pageid);
                $this->fail("{$label}: expected the call to throw");
            } catch (\required_capability_exception $e) {
                $this->assertInstanceOf(\required_capability_exception::class, $e, $label);
            }
        }

        // And the site-wide editor is the mirror image: their own page yes, the category one no.
        $this->setUser($this->user_holding('local/page:addpages'));
        $this->assertNotNull(\local_page_require_editable_page((int) $sitepage->id));

        try {
            \local_page_require_editable_page((int) $pagea->id);
            $this->fail('site editor: expected the call to throw for a category page');
        } catch (\required_capability_exception $e) {
            $this->assertInstanceOf(\required_capability_exception::class, $e);
        }
    }

    /**
     * For a new page the re-check reads the context it is to be created in.
     *
     * There is no row to derive it from yet, so this is the one case where the posted value is
     * what is checked — which is exactly why it is checked rather than trusted.
     *
     * @return void
     */
    public function test_require_editable_page_checks_the_posted_context_for_a_new_page(): void {
        $this->resetAfterTest();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $ctxa = \core\context\coursecat::instance($cata->id);
        $ctxb = \core\context\coursecat::instance($catb->id);

        $this->setUser($this->user_holding_at('local/page:managecategorypages', $ctxa));

        // Control: a new page in the category this user manages is allowed.
        $this->assertNull(\local_page_require_editable_page(0, (int) $ctxa->id));

        $refused = ['sibling category' => (int) $ctxb->id, 'site-wide scope' => 0];
        foreach ($refused as $label => $contextid) {
            try {
                \local_page_require_editable_page(0, $contextid);
                $this->fail("{$label}: expected the call to throw");
            } catch (\required_capability_exception $e) {
                $this->assertInstanceOf(\required_capability_exception::class, $e, $label);
            }
        }

        // The site-wide editor, again in mirror image.
        $this->setUser($this->user_holding('local/page:addpages'));
        $this->assertNull(\local_page_require_editable_page(0, 0));

        try {
            \local_page_require_editable_page(0, (int) $ctxa->id);
            $this->fail('site editor: expected the call to throw for a category scope');
        } catch (\required_capability_exception $e) {
            $this->assertInstanceOf(\required_capability_exception::class, $e);
        }
    }

    /**
     * A category page's embedded files are served through its own context and item id, and nowhere else.
     *
     * The fixture deliberately stores the SAME file name under the SAME item id in a second
     * category's context. Nothing in the plugin writes that — a page's files only ever go to the
     * page's own context — but the file table outlives rows, through a restore, a reinstall or a
     * move made by hand, and it is what makes the contextid clause in the lookup load-bearing
     * instead of incidental: without it the row is found by id alone and this context serves it.
     *
     * Only refusals are asserted through local_page_pluginfile(), for the reason the og:image
     * tests give: a successful serve reaches readfile_accel(), which closes every output buffer
     * PHPUnit has. The positive direction is held by the controls, which show the page viewable
     * and each fixture file really present in the area the call is refusing to read from.
     *
     * @return void
     */
    public function test_the_pagecontent_file_route_is_scoped_to_the_pages_own_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $fs = get_file_storage();
        $system = \context_system::instance();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $ctxa = \core\context\coursecat::instance($cata->id);
        $ctxb = \core\context\coursecat::instance($catb->id);

        $page = $this->pages()->create_category_page((int) $cata->id);
        $itemid = (int) $page->id;

        $this->store_pagecontent_file((int) $ctxa->id, $itemid, 'body.png');
        $this->store_pagecontent_file((int) $ctxb->id, $itemid, 'body.png');

        // Controls: the page is viewable, and both fixture files really are where they were put.
        $this->assertTrue(\local_page_user_can_view_page($page));
        $this->assertNotEmpty($fs->get_file($ctxa->id, 'local_page', 'pagecontent', $itemid, '/', 'body.png'));
        $this->assertNotEmpty($fs->get_file($ctxb->id, 'local_page', 'pagecontent', $itemid, '/', 'body.png'));

        // A context the page does not belong to may not serve it, file or no file.
        $this->assertFalse(
            \local_page_pluginfile(null, null, $ctxb, 'pagecontent', [$itemid, 'body.png'], false, ['dontdie' => true]),
            'sibling category context'
        );

        // Nor may the site-wide area, where a category page's files never are.
        $this->assertFalse(
            \local_page_pluginfile(null, null, $system, 'pagecontent', [0, 'body.png'], false, ['dontdie' => true]),
            'system context, legacy item id'
        );

        // An item id naming no page of this context is refused before the file store is consulted.
        $this->assertFalse(
            \local_page_pluginfile(null, null, $ctxa, 'pagecontent', [$itemid + 100000, 'body.png'], false, ['dontdie' => true]),
            'unknown item id'
        );

        // And once the page is soft-deleted its own context stops serving it too.
        $DB->set_field('local_page', 'deleted', 1, ['id' => $itemid]);
        $this->assertFalse(
            \local_page_pluginfile(null, null, $ctxa, 'pagecontent', [$itemid, 'body.png'], false, ['dontdie' => true]),
            'own context, page deleted'
        );
    }

    /**
     * A page of another context can never authorise a file of the site-wide area.
     *
     * The site-wide pagecontent area is shared: every page there writes under item id 0, so who
     * owns a file can only be read out of the page content that names it. That search IS the
     * authorisation decision, and it has to stay inside one context, because the pages of a
     * category are authored by whoever holds local/page:managecategorypages there — which is not
     * a power over the site's own files. Without the context clause such an author pastes a
     * reference to a site-wide file into their own page and the file follows, whatever state the
     * page it really belongs to is in.
     *
     * The second half is the point of the test: the site-wide page holding the file is a DRAFT the
     * category manager may not read, while their own page naming the same file is one they may.
     * Both halves carry a control, because a search that had simply stopped matching anything at
     * all would pass every refusal here just as well as the clause does.
     *
     * @return void
     */
    public function test_a_page_of_another_context_cannot_authorise_a_site_wide_file(): void {
        $this->resetAfterTest();

        $fs = get_file_storage();
        $system = \context_system::instance();
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \core\context\coursecat::instance($category->id);

        $reference = '<p><img src="@@PLUGINFILE@@/secret.png"></p>';

        $this->store_pagecontent_file((int) $system->id, 0, 'secret.png');
        $categorypage = $this->pages()->create_category_page((int) $category->id, ['pagecontent' => $reference]);

        $manager = $this->user_holding_at('local/page:managecategorypages', $categorycontext);
        $this->setUser($manager);

        // Controls: the file really is in the area being asked for, and the reference really is stored.
        $this->assertNotEmpty($fs->get_file($system->id, 'local_page', 'pagecontent', 0, '/', 'secret.png'));
        $this->assertStringContainsString('secret.png', (string) $categorypage->pagecontent);
        $this->assertTrue(\local_page_user_can_view_page($categorypage), 'the manager may read their own page');

        // With no site-wide page naming the file, nothing in that area authorises it.
        $this->assertSame([], \local_page_pages_referencing_pagecontent_file((int) $system->id, '/', 'secret.png'));
        $this->assertFalse(\local_page_user_can_serve_pagecontent_file((int) $system->id, '/', 'secret.png'));
        $this->assertFalse(
            \local_page_pluginfile(null, null, $system, 'pagecontent', [0, 'secret.png'], false, ['dontdie' => true]),
            'a category page may not authorise a site-wide file'
        );

        // Now the page the file belongs to exists, and it is a draft this manager may not read.
        $sitepage = $this->pages()->create_page(['pagecontent' => $reference, 'status' => 'draft']);
        $this->assertFalse(\local_page_user_can_view_page($sitepage), 'the draft is not readable by the manager');

        $matches = \local_page_pages_referencing_pagecontent_file((int) $system->id, '/', 'secret.png');
        $this->assertCount(1, $matches, 'only rows of the context asked for are searched');
        $this->assertSame((int) $sitepage->id, (int) $matches[0]->id);

        $this->assertFalse(\local_page_user_can_serve_pagecontent_file((int) $system->id, '/', 'secret.png'));
        $this->assertFalse(
            \local_page_pluginfile(null, null, $system, 'pagecontent', [0, 'secret.png'], false, ['dontdie' => true]),
            'the draft that owns the file decides, not the category page that names it'
        );

        /*
         * Control: the search itself still finds and authorises the file. An administrator may
         * read the draft, so the same call answers true — which is how the refusals above are
         * known to be the context clause and the draft gate, and not a needle that stopped
         * matching.
         */
        $this->setAdminUser();
        $this->assertTrue(\local_page_user_can_serve_pagecontent_file((int) $system->id, '/', 'secret.png'));
    }

    /**
     * An og:image is served only through the context of the page the item id names.
     *
     * Page ids are unique across the whole table, so the item id alone says nothing about which
     * context may hand the file out. Both directions are asserted, and both fixtures put a real
     * file in the area being asked — otherwise the refusal would only be a missing file.
     *
     * @return void
     */
    public function test_the_ogimage_file_route_refuses_a_row_from_another_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $fs = get_file_storage();
        $system = \context_system::instance();

        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \core\context\coursecat::instance($category->id);

        $sitepage = $this->pages()->create_page();
        $categorypage = $this->pages()->create_category_page((int) $category->id);

        // Each page's image, deliberately stored in the OTHER page's context.
        $this->store_ogimage((int) $sitepage->id, 'og.png', (int) $categorycontext->id);
        $this->store_ogimage((int) $categorypage->id, 'og.png', (int) $system->id);

        // Controls: both rows are published, and both fixture files are in the areas being asked.
        $this->assertTrue(\local_page_ogimage_is_servable($sitepage));
        $this->assertTrue(\local_page_ogimage_is_servable($categorypage));
        $this->assertNotEmpty(
            $fs->get_file($categorycontext->id, 'local_page', 'ogimage', (int) $sitepage->id, '/', 'og.png')
        );
        $this->assertNotEmpty(
            $fs->get_file($system->id, 'local_page', 'ogimage', (int) $categorypage->id, '/', 'og.png')
        );

        $this->assertFalse(
            \local_page_pluginfile(
                null,
                null,
                $categorycontext,
                'ogimage',
                [(int) $sitepage->id, 'og.png'],
                false,
                ['dontdie' => true]
            ),
            'a category context may not serve a site-wide page image'
        );

        $this->assertFalse(
            \local_page_pluginfile(
                null,
                null,
                $system,
                'ogimage',
                [(int) $categorypage->id, 'og.png'],
                false,
                ['dontdie' => true]
            ),
            'the system context may not serve a category page image'
        );
    }

    /**
     * A row belongs to one context, compared on the stored convention.
     *
     * This is the guard the listing screen's delete action applies. pages.php is a script, so the
     * comparison lives here where a test can reach it — an unheld guard is the one that quietly
     * stops working.
     *
     * @return void
     */
    public function test_page_in_context_compares_the_stored_convention(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \core\context\coursecat::instance($category->id);
        $system = \context_system::instance();

        $sitepage = $this->pages()->create_page();
        $categorypage = $this->pages()->create_category_page((int) $category->id);

        $this->assertTrue(\local_page_page_in_context($sitepage, $system));
        $this->assertTrue(\local_page_page_in_context($categorypage, $categorycontext));

        // Neither belongs to the other's context, which is what the delete action has to know.
        $this->assertFalse(\local_page_page_in_context($sitepage, $categorycontext));
        $this->assertFalse(\local_page_page_in_context($categorypage, $system));

        // A row written before contexts existed carries no column at all and is a system page.
        $legacy = clone $sitepage;
        unset($legacy->contextid);
        $this->assertTrue(\local_page_page_in_context($legacy, $system));
        $this->assertFalse(\local_page_page_in_context($legacy, $categorycontext));
    }

    /**
     * A posted context never moves a page that already exists.
     *
     * The hidden field travels through the browser. Honouring it on an existing row would let a
     * page be moved into a context whose capability was never checked when the form was rendered
     * — and out of the reach of the one that was.
     *
     * @return void
     */
    public function test_a_posted_context_never_moves_an_existing_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \core\context\coursecat::instance($category->id);
        $system = \context_system::instance();

        $sitepage = $this->pages()->create_page();
        $categorypage = $this->pages()->create_category_page((int) $category->id);

        // An existing row keeps its own context whatever the form posted.
        $this->assertSame(
            (int) $system->id,
            (int) \local_page_save_target_context($sitepage, (int) $categorycontext->id)->id,
            'a site-wide page posted with a category context'
        );
        $this->assertSame(
            (int) $categorycontext->id,
            (int) \local_page_save_target_context($categorypage, 0)->id,
            'a category page posted with the site-wide scope'
        );

        /*
         * Control: with no row — a new page — the posted value IS what decides, so the two
         * assertions above are the row winning and not the argument being ignored altogether.
         */
        $this->assertSame((int) $categorycontext->id, (int) \local_page_save_target_context(null, (int) $categorycontext->id)->id);
        $this->assertSame((int) $system->id, (int) \local_page_save_target_context(null, 0)->id);
    }

    /**
     * A category page reaches a visitor only when its category is public; nobody else is asked.
     *
     * The page is live and open to visitors by its own rules, so every refusal below can only be the
     * category's. Each one sits beside the same page admitted once the double calls the category
     * public, which is what separates the clause from a predicate that has started refusing
     * everything. Whether the category is public comes from \local_page\tests\public_predicate,
     * because the CI matrix does not install local_unlistedcourses.
     *
     * @return void
     */
    public function test_a_category_page_reaches_a_visitor_only_when_its_category_is_public(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->forcelogin = 1;
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid);
        $sitepage = $this->pages()->create_page();
        $double = \local_page\tests\public_predicate::class;

        foreach (['anonymous', 'guest'] as $label) {
            if ($label === 'guest') {
                $this->setGuestUser();
            } else {
                $this->setUser(null);
            }

            $double::reset([]);
            $this->assertFalse(\local_page_user_can_view_page($page, $double), "{$label}: category not public");
            $this->assertSame(1, $double::calls(), "{$label}: the refusal was the predicate's");

            $double::reset([$categoryid]);
            $this->assertTrue(\local_page_user_can_view_page($page, $double), "{$label}: control, category public");
        }

        // A logged-in user reads the page in a category that is not public, and is never asked about.
        $double::reset([]);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(\local_page_user_can_view_page($page, $double), 'user: category page');
        $this->assertTrue(\local_page_user_can_view_page($sitepage, $double), 'user: site-wide page');
        $this->assertSame(0, $double::calls(), 'user: never asked');

        // A site-wide page belongs to no category, so a visitor reads it without the question.
        $this->setUser(null);
        $this->assertTrue(\local_page_user_can_view_page($sitepage, $double), 'anonymous: site-wide page');
        $this->assertSame(0, $double::calls(), 'anonymous: never asked about a site-wide page');
    }

    /**
     * A category page's embedded file is refused to a visitor while its category is not public.
     *
     * local_page_pluginfile() cannot take a predicate, so this runs the REAL one: on a site without
     * local_unlistedcourses — every CI leg — the adapter fails closed, and where the plugin is
     * installed a fresh category is simply not public. Either way the refusal is the category's, and
     * the controls say so: the file really is in the area, and the page's own rules admit a visitor
     * once a double calls the category public. Only the refusal goes through the route itself, for
     * the reason the og:image tests give — a served file reaches readfile_accel(), which closes
     * PHPUnit's output buffers. Where local_unlistedcourses is installed, the category is then made
     * public for real and the same visitor may read the page through the default predicate, which
     * is the call the route makes.
     *
     * @return void
     */
    public function test_a_category_pages_file_is_refused_to_a_visitor_outside_a_public_category(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->forcelogin = 1;
        $predicate = \local_page\local\publicaccess::PREDICATE;
        if (class_exists($predicate)) {
            // Memoised per category id for the request, and ids repeat between tests.
            $predicate::reset_caches();
        }

        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);
        $page = $this->pages()->create_category_page($categoryid, ['pagecontent' => '<p><img src="@@PLUGINFILE@@/body.png"></p>']);
        $this->store_pagecontent_file((int) $context->id, (int) $page->id, 'body.png');

        $this->setUser(null);

        // Controls: the file is there, and nothing but the category stands between the visitor and the page.
        $fs = get_file_storage();
        $this->assertNotEmpty($fs->get_file($context->id, 'local_page', 'pagecontent', (int) $page->id, '/', 'body.png'));
        \local_page\tests\public_predicate::reset([$categoryid]);
        $this->assertTrue(\local_page_user_can_view_page($page, \local_page\tests\public_predicate::class));

        $this->assertFalse(\local_page_user_can_view_page($page), 'the real predicate: not public');
        $this->assertFalse(
            \local_page_pluginfile(null, null, $context, 'pagecontent', [(int) $page->id, 'body.png'], false, ['dontdie' => true]),
            'the route refuses the file'
        );

        if (!class_exists($predicate)) {
            return;
        }

        $this->setAdminUser();
        $predicate::set_state($categoryid, $predicate::STATE_PUBLIC);
        $predicate::reset_caches();
        $this->setUser(null);
        $this->assertTrue(\local_page_user_can_view_page($page), 'made public for real, the visitor may read it');
    }
}
