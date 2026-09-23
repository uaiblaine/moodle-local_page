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
 * Writes a custom page's head tags into the document head.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local\hook\output;

use local_page\output\opengraph;

/**
 * Writes a custom page's head tags into the document head.
 *
 * The hook is dispatched from core_renderer::standard_head_html(), so it runs for every page the
 * site renders — an error page included; the callback does nothing unless local_page_render_view()
 * registered tags for the page being rendered, and that function registers them last, after
 * everything that could throw, so an error page never carries a page's tags.
 *
 * This replaces upstream's $CFG->additionalhtmlhead, which the plugin no longer touches: appending
 * to a site setting mid-request put the tags wherever the theme printed that setting and left them
 * in $CFG for anything rendered later in the same process.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_standard_head_html_generation {
    /**
     * Append the tags, when the page being rendered is one of this plugin's pages.
     *
     * In order: the name metas (description, keywords, author, robots), the canonical link, the Open
     * Graph metas from the template, and last the page's own raw head HTML, which only a site-wide
     * page can carry (local_page_head_html() decided that when the tags were built).
     *
     * The name metas and the canonical link are literal strings here, the way core's own
     * standard_head_html() writes its metas, because the template lint validates a fragment as body
     * content and rejects both there. One escaping pass each: s() for the attribute here, the double
     * stashes in the template.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook The hook
     * @return void
     */
    public static function callback(\core\hook\output\before_standard_head_html_generation $hook): void {
        $tags = opengraph::current();
        if ($tags === null) {
            return;
        }

        foreach ($tags->get_metas() as $name => $content) {
            $hook->add_html('<meta name="' . s($name) . '" content="' . s($content) . '">' . "\n");
        }
        $hook->add_html('<link rel="canonical" href="' . s($tags->get_url()) . '">' . "\n");
        $hook->add_html($hook->renderer->render($tags) . "\n");
        $hook->add_html($tags->get_headhtml());
    }
}
