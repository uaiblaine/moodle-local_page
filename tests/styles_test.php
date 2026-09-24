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
 * Tests for the plugin's stylesheet.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests for styles.css, which no linter reads for words.
 *
 * Text a stylesheet draws through a content declaration cannot be translated, and the page's
 * status used to be drawn that way, in English, whatever the viewer's language. The status is
 * markup now ({@see \local_page_status_badge()}); this holds the sheet to drawing no words again.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class styles_test extends \basic_testcase {
    /**
     * The stylesheet draws no text: no status word, and no non-empty content declaration at all.
     *
     * @return void
     */
    public function test_the_stylesheet_draws_no_text(): void {
        $css = file_get_contents(__DIR__ . '/../styles.css');

        // Control: this is the sheet that sizes the status badge, so the assertions below read it.
        $this->assertMatchesRegularExpression('/\.badge\.local-page-status-badge\s*\{[^}]*font-size:/', $css);

        foreach (['Live', 'Draft', 'Archived'] as $word) {
            $this->assertStringNotContainsString('"' . $word . '"', $css);
            $this->assertStringNotContainsString("'" . $word . "'", $css);
        }
        $this->assertDoesNotMatchRegularExpression('/\bcontent\s*:\s*(["\'])(?!\1)/', $css);
    }
}
