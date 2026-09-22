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
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->create_role_capability(
            $roleid,
            [$capability => 'allow'],
            \context_system::instance()
        );
        role_assign($roleid, $user->id, \context_system::instance()->id);
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
}
