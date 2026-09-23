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
 * Moodec Capability definitions
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Local page:addpages is RISK_XSS: pages may include trusted editor HTML, raw Content HTML, and optional
// <head> HTML. Archetypes grant CAP_ALLOW to manager and coursecreator at system context—equivalent to a
// site-wide content injection role for this plugin.

$capabilities = [
    'local/page:addpages' => [

        'riskbitmask' => RISK_XSS,

        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'frontpage' => CAP_PREVENT,
            'guest' => CAP_PREVENT,
            'user' => CAP_PREVENT,
            'student' => CAP_PREVENT,
            'teacher' => CAP_PREVENT,
            'editingteacher' => CAP_PREVENT,
            'coursecreator' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    /*
     * Author the pages that belong to one course category. Its own capability rather than
     * addpages checked at a lower context: a site delegating "write the pages of this
     * programme" is not handing over the site-wide pages, and the two are held by different
     * people. RISK_SPAM because the page carries author-written text; captype write because
     * authoring is a write, which also means a guest or an anonymous visitor can never hold it
     * (lib/accesslib.php:481-485).
     *
     * Deliberately WITHOUT clonepermissionsfrom, following the precedent in
     * local_unlistedcourses/db/access.php: no upgrade may back-fill a category authoring right
     * from moodle/category:manage or from local/page:addpages. A site that wants both grants
     * both.
     */
    'local/page:managecategorypages' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    /*
     * Publish a category page to visitors who are not logged in. Split from the authoring
     * capability above for the reason the course side splits publish from update: writing a
     * page is an editing act, putting it in front of the open web is not, and folding the
     * second into the first would grant the larger power silently.
     *
     * DECLARED HERE, ENFORCED IN STAGE 3. Nothing reads it yet: this stage only gives pages a
     * context. It is declared now so that the capability exists — with its strings, its
     * archetype and its risk — before the gate that consults it arrives, rather than appearing
     * in the same upgrade that starts refusing things.
     *
     * Also deliberately WITHOUT clonepermissionsfrom, for the same reason as above.
     */
    'local/page:publishcategorypages' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
