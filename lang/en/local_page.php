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
 * Local Pages Module
 *
 * This module facilitates the creation and management of custom pages and forms within Moodle.
 *
 * @package    local_page
 * @copyright  2025 Marcin Czaja RoseaThemes (rosea.io)
 * @author     Marcin Czaja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accesslevel'] = 'Required capability';
$string['accesslevel_help'] = 'Optional comma-separated Moodle capabilities that further restrict who can view this page (in addition to status, dates, and "only logged in"). <strong>Evaluation is left-to-right with OR-style results:</strong> each entry is applied only while access is not yet granted; a plain capability name grants access if the user has that capability; a name prefixed with <strong>!</strong> grants access if the user does <em>not</em> have that capability. Once an entry grants access, remaining entries are ignored—order matters when you mix positive and negated rules. Example: <code>moodle/course:view, !moodle/site:config</code> allows any user with course view who is not a full site administrator. Leave empty for no extra capability gate.';
$string['accesslevel_negationonly'] = 'The required capability cannot be made up of negated entries only. A rule such as <code>!moodle/site:config</code> grants the page to every visitor who does <em>not</em> hold that capability, anonymous ones included, so it restricts nothing. Add a positive capability alongside the negation.';
$string['accesslevel_unknowncapability'] = 'The capability \'{$a}\' does not exist on this site. Check the spelling and use the full name, such as <code>moodle/site:config</code>.';
$string['addpage'] = "Add New Page";
$string['backtolist'] = "Return to Pages List";
$string['categorymovebusy'] = 'The custom pages of this category could not be moved, because another page is being saved at this moment. Try again in a moment.';
$string['categorypages'] = 'Custom pages';
$string['confirmdeletepage'] = 'Are you sure you want to delete the page \'{$a}\'?';
$string['contenthtml'] = 'Content HTML';
$string['contenthtml_description'] = 'Raw HTML Content';
$string['contenthtml_description_help'] = 'Enter raw HTML content that will be displayed directly on the page. This content will not be processed by the editor and will be rendered as-is. Use with caution as it can affect page layout and security. On a page belonging to a course category the block is cleaned like any other content, unless its author is trusted with unclean HTML on this site.';
$string['contenthtml_placeholder'] = 'Enter raw HTML content here...';
$string['custompage_title'] = 'Page Management';
$string['delete'] = "Remove";
$string['edit_head'] = "Content for &lt;head&gt;";
$string['edit_ogimage'] = "Open Graph Image File";
$string['edit_ogimage_notimage'] = 'This is not a JPEG, PNG or WebP picture of the kind its file name says. The Open Graph image is served to anybody, so only the image itself is accepted: upload the picture, saved under its own format\'s extension.';
$string['form_field_date'] = "Start Publishing Date";
$string['form_field_enddate'] = "End Publishing Date";
$string['form_field_enddate_description'] = "End Publishing Date";
$string['form_field_enddate_description_help'] = "Select the date when this page will be unpublished - a past date will restrict access until that date.";
$string['hidetitle'] = 'Hide Title';
$string['menu_name'] = 'Friendly URL';
$string['menu_name_description'] = 'Friendly URL Description';
$string['menu_name_description_help'] = 'Provide a URL slug for the page (letters, numbers, hyphen, underscore only; invalid characters are removed on save). Web server rewrite rules must map requests such as <strong>about-us</strong> to this plugin\'s viewer if you use root-level URLs.';
$string['menuname_reserved'] = 'This friendly URL is reserved by Moodle itself. Addresses such as <code>login</code>, <code>course</code>, <code>pluginfile</code> or anything starting with a plugin type such as <code>local_</code> are answered by the site, so a page holding one would either never be reached or would hide part of Moodle. Choose a different one.';
$string['menuname_taken'] = 'This friendly URL is already used by another page. Choose a different one.';
$string['metaauthor'] = 'Meta Author';
$string['metaauthor_description'] = 'Meta Author Description';
$string['metaauthor_description_help'] = 'Provide a meta author for the page. This will be used to identify the author of the page.';
$string['metadescription'] = 'Meta Description';
$string['metadescription_description'] = 'Meta Description Description';
$string['metadescription_description_help'] = 'Provide a meta description for the page. This will be used to describe the page to search engines.';
$string['metakeywords'] = 'Meta Keywords';
$string['metakeywords_description'] = 'Meta Keywords Description';
$string['metakeywords_description_help'] = 'Provide a meta keywords for the page. This will be used to describe the page to search engines.';
$string['metarobots'] = 'Meta Robots';
$string['metarobots_description'] = 'Meta Robots Description';
$string['metarobots_description_help'] = 'Provide a meta robots tag for the page. This will be used to control how search engines index the page. Available options include:<br />
<ul>
    <li>"index": Allow indexing of the page.</li>
    <li>"noindex": Prevent indexing of the page.</li>
    <li>"follow": Allow following of links on the page.</li>
    <li>"nofollow": Prevent following of links on the page.</li>
    <li>"noarchive": Prevent search engines from caching the page.</li>
    <li>"nosnippet": Prevent search engines from showing a snippet of the page in search results.</li>
    <li>"noodp": Prevent the use of Open Directory Project (DMOZ) data for the page.</li>
    <li>"notranslate": Prevent search engines from offering translation of the page.</li>
    <li>"noimageindex": Prevent search engines from indexing images on the page.</li>
</ul>';
$string['metatitle'] = 'Meta Title';
$string['metatitle_description'] = 'Meta Title Description';
$string['metatitle_description_help'] = 'Provide a meta title for the page. This will be used to display the title of the page in search results.';
$string['noaccess'] = 'You do not have permission to view this page.';
$string['onlyloggedin'] = "Only Logged In";
$string['onlyloggedin_description'] = "Only show the page to logged in users";
$string['onlyloggedin_description_help'] = "<ul>
    <li>If you select 'Yes', the page will only be visible to logged in users.</li>
    <li>If you select 'No', the page will be visible to all users.</li>
    <li>Non-logged in users will see a message that the page is only visible to logged in users.</li>
    <li>Guest users will see a message that the page is only visible to logged in users.</li>
</ul>";
$string['onlyloggedin_publishlocked'] = 'Publishing to visitors who are not logged in needs the capability \'Publish a category page to visitors who are not logged in\' (<code>local/page:publishcategorypages</code>) in this course category, which you do not hold here. The page is saved for logged-in users only.';
$string['page:addpages'] = 'Add and edit custom site pages (trusted HTML, raw head meta, and Content HTML—declared as RISK_XSS). The default course creator role can perform this site-wide; assign only to roles that should be able to inject arbitrary markup for all visitors.';
$string['page:managecategorypages'] = 'Create and edit the custom pages that belong to a course category. Holders author pages only in the categories where they hold the capability; the site-wide pages stay with \'Add and edit custom site pages\'. Declared as RISK_SPAM because a page carries author-written text.';
$string['page:publishcategorypages'] = 'Publish a category page to visitors who are not logged in. Deliberately separate from authoring: writing a page and putting it in front of the open web are different acts, and a role may be trusted with one and not the other. Declared now and enforced by the publishing gate that follows it.';
$string['page_content_description'] = 'Enter the content for the page here.';
$string['page_name'] = 'Title of the Page';
$string['pagedate_description'] = 'Select the date when this page will be published - a future date will restrict access until that date.';
$string['pagedate_description_help'] = 'Select the date for publishing this page - access will be restricted until the specified date.';
$string['pagename_placeholder'] = 'Enter the page name';
$string['pagenotfound'] = 'The requested page does not exist or has been deleted.';
$string['pagesetup_heading'] = 'Page Setup Heading';
$string['pagesetup_title'] = 'Page Setup Title';
$string['pluginname'] = 'Custom Pages';
$string['pluginsettings'] = 'Plugin Settings';
$string['pluginsettings_managepages'] = 'Manage Pages Settings';
$string['privacy:metadata'] = 'The local pages plugin does not store any personal data.';
$string['restricted'] = 'Restricted by date';
$string['setting_additionalhead'] = "Enable Additional HTML in Head";
$string['setting_additionalhead_description'] = "Allow custom content to be added to the HTML &lt;head&gt; section.";
$string['shareurl'] = 'Short address to share';
$string['status'] = 'Status';
$string['status_archived'] = 'Archived';
$string['status_draft'] = 'Draft';
$string['status_live'] = 'Live';
