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
 * Tests for the code the upgrade steps run.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use local_page\local\slug;
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/db/upgradelib.php');

/**
 * Tests for db/upgradelib.php: the frozen slug normalisation and the hidetitle schema repair.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_page_upgrade_normalise_slugs')]
#[CoversFunction('local_page_upgrade_hidetitle_notnull')]
final class upgradelib_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * Every page's stored slug, keyed by page id.
     *
     * @return array
     */
    private function slugs(): array {
        global $DB;

        return $DB->get_records_menu('local_page', null, 'id ASC', 'id, menuname');
    }

    /**
     * Whether the hidetitle column refuses NULL, read from the database rather than the cache.
     *
     * @return bool
     */
    private function hidetitle_is_not_null(): bool {
        global $DB;

        return (bool) $DB->get_columns('local_page', false)['hidetitle']->not_null;
    }

    /**
     * The hidetitle column as db/install.xml declares it, or with NOT NULL dropped.
     *
     * @param bool $notnull Whether the field refuses NULL
     * @return \xmldb_field
     */
    private function hidetitle_field(bool $notnull): \xmldb_field {
        $notnullclause = $notnull ? XMLDB_NOTNULL : null;

        return new \xmldb_field('hidetitle', XMLDB_TYPE_CHAR, '10', null, $notnullclause, null, 'no', 'metarobots');
    }

    /**
     * The frozen normalisation gives exactly the result of the class it was copied from.
     *
     * Both run on the same rows: the class first, then, after every slug is put back, the frozen
     * copy. The fixture exercises every pass: an empty slug, an upper-case one, a deleted row, a
     * duplicate whose -<id> form another page holds, the same slugs in a second category and in the
     * site scope. A copy that drifts from the class in any pass gives a different table.
     *
     * @return void
     */
    public function test_the_frozen_normalisation_matches_the_class(): void {
        global $DB;

        $this->resetAfterTest();

        $category = (int) $this->getDataGenerator()->create_category()->id;
        $other = (int) $this->getDataGenerator()->create_category()->id;

        $first = (int) $this->pages()->create_category_page($category, ['menuname' => 'placeholder'])->id;
        $this->pages()->create_category_page($category, ['menuname' => 'x']);
        $third = (int) $this->pages()->create_category_page($category, ['menuname' => 'x'])->id;
        $DB->set_field('local_page', 'menuname', "x-{$third}", ['id' => $first]);
        $this->pages()->create_category_page($category, ['menuname' => '']);
        $this->pages()->create_category_page($category, ['menuname' => 'Legal']);
        $this->pages()->create_category_page($category, ['menuname' => 'contact', 'deleted' => 1]);
        $this->pages()->create_category_page($other, ['menuname' => 'x']);
        $this->pages()->create_category_page($other, ['menuname' => 'x']);
        $this->pages()->create_page(['menuname' => 'x']);
        $this->pages()->create_page(['menuname' => 'x']);

        $original = $this->slugs();

        $livechanged = slug::normalise_all();
        $live = $this->slugs();

        foreach ($original as $id => $menuname) {
            $DB->set_field('local_page', 'menuname', $menuname, ['id' => $id]);
        }
        $this->assertSame($original, $this->slugs(), 'Precondition: the frozen copy starts from the same rows.');

        $frozenchanged = local_page_upgrade_normalise_slugs();

        $this->assertSame($live, $this->slugs());
        $this->assertSame($livechanged, $frozenchanged);

        // Control: the fixture moved rows, the counter included, so equal tables are not two no-ops.
        $this->assertSame(6, $frozenchanged);
        $this->assertSame("x-{$third}-2", $this->slugs()[$third]);

        // And the frozen copy is idempotent on its own.
        $this->assertSame(0, local_page_upgrade_normalise_slugs());
    }

    /**
     * The hidetitle repair fills the NULLs an upgraded site holds and makes the column NOT NULL.
     *
     * The column is first put in the state step 2025060200 leaves it in on an upgraded site —
     * nullable — and a row is given NULL, both asserted before the repair runs. A row that already
     * holds a value is the control that only the NULLs are rewritten.
     *
     * @return void
     */
    public function test_hidetitle_repair_fills_nulls_and_restores_not_null(): void {
        global $DB;

        $this->resetAfterTest();

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_page');

        try {
            $dbman->change_field_notnull($table, $this->hidetitle_field(false));
            $this->assertFalse($this->hidetitle_is_not_null(), 'Precondition: the column accepts NULL.');

            $blank = (int) $this->pages()->create_page(['hidetitle' => null])->id;
            $shown = (int) $this->pages()->create_page(['hidetitle' => 'yes'])->id;
            $this->assertNull($DB->get_field('local_page', 'hidetitle', ['id' => $blank]), 'Precondition: the row holds NULL.');

            local_page_upgrade_hidetitle_notnull();

            $this->assertSame('no', $DB->get_field('local_page', 'hidetitle', ['id' => $blank]));
            $this->assertSame('yes', $DB->get_field('local_page', 'hidetitle', ['id' => $shown]));
            $this->assertTrue($this->hidetitle_is_not_null());

            // Running it again, as a replayed upgrade would, changes nothing.
            local_page_upgrade_hidetitle_notnull();
            $this->assertSame('no', $DB->get_field('local_page', 'hidetitle', ['id' => $blank]));
            $this->assertTrue($this->hidetitle_is_not_null());
        } finally {
            /*
             * Inside the transaction the test framework opens on PostgreSQL, the DDL is rolled back
             * with the data. Without one, as on MySQL and MariaDB, the column is left as install.xml
             * declares it for every later test.
             */
            if (!$DB->is_transaction_started()) {
                $DB->set_field_select('local_page', 'hidetitle', 'no', 'hidetitle IS NULL');
                $dbman->change_field_notnull($table, $this->hidetitle_field(true));
            }
        }
    }
}
