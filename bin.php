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
 * The recycle bin of the site's pages or of one course category's.
 *
 * Restoring posts the restore form. Deleting permanently and emptying answer a GET with core's
 * confirmation, whose continue button posts back with the session key and confirm=1. Every action
 * acts on this context's bin only ({@see \local_page\local\bin}).
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/page/lib.php');

use local_page\local\bin;

$contextid = optional_param('contextid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$pageid = optional_param('pageid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// The login check and the guest's refusal come before the context is looked up, as in edit.php,
// whose comment says why; pages.php keeps the same order.
require_login(null, false);
if (isguestuser()) {
    $SESSION->wantsurl = qualified_me();
    redirect(get_login_url());
}

if ($contextid > 0) {
    $context = \core\context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
        throw new moodle_exception('invalidcontext');
    }
} else {
    $context = \core\context\system::instance();
}
$binurl = local_page_bin_url($context);

if ($context->contextlevel == CONTEXT_COURSECAT) {
    // Sets the course, the category and the context, and must come before every other set_*() call.
    $PAGE->set_category_by_id((int) $context->instanceid);
    $PAGE->set_heading(core_course_category::get((int) $context->instanceid, MUST_EXIST, true)->get_formatted_name());
} else {
    $PAGE->set_context($context);
    $PAGE->set_heading(get_string('pagesetup_heading', 'local_page'));
}
$PAGE->set_url($binurl);
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('recyclebin', 'local_page'));

require_capability(\local_page\local\scope::capability($context), $context);

if ($action === 'restore') {
    require_sesskey();
    $restored = bin::restore($pageid, $context);
    $a = (object) [
        'name' => format_string($restored->name, true, ['context' => $context]),
        'menuname' => s($restored->menuname),
    ];
    if ($restored->renamed) {
        $message = get_string('binrestored_renamed', 'local_page', $a);
    } else {
        $message = get_string('binrestored', 'local_page', $a->name);
    }
    redirect($binurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'purge' || $action === 'empty') {
    if ($confirm) {
        require_sesskey();
        if ($action === 'purge') {
            bin::purge($pageid, $context);
            $message = get_string('binpurged', 'local_page');
        } else {
            bin::empty($context);
            $message = get_string('binemptied', 'local_page');
        }
        redirect($binurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'purge') {
        $page = $DB->get_record('local_page', [
            'id' => $pageid,
            'contextid' => \local_page\local\scope::stored_contextid($context),
            'deleted' => 1,
        ], 'id, pagename', MUST_EXIST);
        $question = get_string('binpurge_confirm', 'local_page', format_string($page->pagename, true, ['context' => $context]));
    } else {
        $question = get_string('binempty_confirm', 'local_page', bin::count($context));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('recyclebin', 'local_page'));
    echo $OUTPUT->confirm(
        $question,
        new \core\output\single_button(
            local_page_bin_url($context, ['action' => $action, 'pageid' => $pageid, 'confirm' => 1, 'sesskey' => sesskey()]),
            get_string($action === 'purge' ? 'binpurge' : 'binempty', 'local_page'),
            'post',
            \core\output\single_button::BUTTON_DANGER
        ),
        $binurl
    );
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recyclebin', 'local_page'));
echo $OUTPUT->render_from_template(
    'local_page/bin_list',
    (new \local_page\output\bin_list($context))->export_for_template($OUTPUT)
);
echo $OUTPUT->footer();
