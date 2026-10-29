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
 * Pages plugin settings file.
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Used to stay DRY with the get_string function call.
$componentname = 'local_page';

// Default for users that have site config.
if ($hassiteconfig) {
    // Add the category to the local plugin branch.
    $ADMIN->add('localplugins', new \admin_category('local_page', get_string('pluginname', $componentname)));

    // Create a settings page for local pages.
    $settingspage = new \admin_settingpage('pages', get_string('pluginsettings', $componentname));

    // Make a container for all of the settings for the settings page.
    $settings = [];

    // Setting to show text box for HTML head on edit page and contents on view page.
    $settings[] = new \admin_setting_configcheckbox(
        'local_page/additionalhead',
        get_string('setting_additionalhead', 'local_page'),
        get_string('setting_additionalhead_description', 'local_page'),
        0
    );

    // How long a deleted page stays in the recycle bin; see \local_page\local\bin::expiry().
    $settings[] = new \admin_setting_configduration(
        'local_page/binexpiry',
        get_string('binexpiry', 'local_page'),
        get_string('binexpiry_desc', 'local_page'),
        \local_page\local\bin::DEFAULT_EXPIRY,
        DAYSECS
    );

    // Add all the settings to the settings page.
    foreach ($settings as $setting) {
        $settingspage->add($setting);
    }

    // Add the settings page to the nav tree.
    $ADMIN->add('local_page', $settingspage);

    // Add the 'Manage pages' page to the nav tree (first argument = stable nodename for admin_externalpage_setup).
    $ADMIN->add(
        'local_page',
        new \admin_externalpage(
            'local_page_managepages',
            get_string('pluginsettings_managepages', $componentname),
            new \moodle_url('/local/page/pages.php'),
            'local/page:addpages'
        )
    );
} else if (has_capability('local/page:addpages', \context_system::instance())) {
    // For other users that don't have the site config capability, do this.
    $ADMIN->add('root', new \admin_category('local_page', get_string('pluginname', $componentname)));

    // Add the 'Manage pages' page to the nav tree.
    $ADMIN->add(
        'local_page',
        new \admin_externalpage(
            'local_page_managepages',
            get_string('pluginsettings_managepages', $componentname),
            new \moodle_url('/local/page/pages.php'),
            'local/page:addpages'
        )
    );
}
