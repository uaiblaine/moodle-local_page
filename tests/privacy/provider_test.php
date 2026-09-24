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
 * Tests for the privacy provider of local_page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\privacy;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \local_page\privacy\provider: the plugin stores nobody's personal data, and says so.
 *
 * A null provider is a claim about the schema, so the claim is checked against the schema rather
 * than restated: the one table the plugin owns carries no column naming a user. Core's compliance
 * test (privacy/tests/privacy/provider_test.php) asks the same of every component — table_coverage
 * fails any table with a userid column or a key to {user} that no metadata provider declares — and
 * this test keeps that answer next to the plugin, where a new column would be added.
 *
 * The rows the plugin writes into core's {shortlink} table are not personal data either: every one
 * is a public code, written with userid 0 by \local_page\local\links::share(), so it belongs to
 * nobody, and core's own table is core's to declare. Nor is metaauthor, which is published content:
 * the text an editor types for the page's author meta tag, tied to no account.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \advanced_testcase {
    /**
     * The provider is a null provider, and its reason is a string both language packs carry.
     *
     * @return void
     */
    public function test_the_provider_declares_no_personal_data_with_a_reason_in_both_packs(): void {
        global $CFG;

        $this->assertTrue(is_subclass_of(provider::class, \core_privacy\local\metadata\null_provider::class));
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('local_page'));

        $reason = provider::get_reason();
        $this->assertTrue(get_string_manager()->string_exists($reason, 'local_page'), 'The reason is a string.');

        foreach (['en', 'pt_br'] as $lang) {
            $string = [];
            require($CFG->dirroot . "/local/page/lang/{$lang}/local_page.php");
            $this->assertArrayHasKey($reason, $string, "The {$lang} pack carries the reason.");
            $this->assertNotSame('', trim($string[$reason]), "The {$lang} reason is not empty.");
        }
    }

    /**
     * No column of the plugin's tables names a user, which is what a null provider promises.
     *
     * The shapes looked for are the ones core's compliance test looks for — a field called userid,
     * and a key referencing {user}.id — plus the other names such a column is usually given here.
     * The control is that the schema really was read: the plugin's table and its columns are found.
     *
     * @return void
     */
    public function test_the_schema_holds_no_column_naming_a_user(): void {
        global $CFG, $DB;

        $DB->get_manager();
        $file = new \xmldb_file($CFG->dirroot . '/local/page/db/install.xml');
        $this->assertTrue($file->loadXMLStructure());
        $tables = $file->getStructure()->getTables();

        // Control: the table and the columns the check walks over are really there.
        $names = array_map(static fn (\xmldb_table $table): string => $table->getName(), $tables);
        $this->assertContains('local_page', $names);

        $userish = ['userid', 'usermodified', 'createdby', 'modifiedby', 'authorid', 'author'];
        foreach ($tables as $table) {
            $fields = array_map(static fn (\xmldb_field $field): string => $field->getName(), $table->getFields());
            $this->assertContains('id', $fields, "{$table->getName()}: its columns were read");
            $this->assertSame([], array_values(array_intersect($fields, $userish)), "{$table->getName()}: no user column");

            foreach ($table->getKeys() as $key) {
                $this->assertNotSame('user', $key->getRefTable(), "{$table->getName()}: no key to the user table");
            }
        }
    }
}
