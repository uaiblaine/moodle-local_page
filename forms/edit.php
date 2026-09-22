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
 * Dynamic Form for editing Moodec pages.
 *
 * This form allows users to create and edit custom pages within the Moodle platform.
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Ensure that this file is being accessed within the Moodle context.
defined('MOODLE_INTERNAL') || die;

// Include necessary libraries for form handling.
require_once($CFG->libdir . '/formslib.php');
require_once(dirname(__FILE__) . '/../lib.php');

/**
 * Class pages_edit_product_form.
 *
 * This class defines the form used for editing pages in the local_page plugin.
 *
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pages_edit_product_form extends moodleform {
    /**
     * @var $callingpage Holds the ID of the current page being edited.
     */
    public $callingpage;

    /**
     * @var \core\context The context this page belongs to; the system context for a site-wide page.
     */
    protected $pagecontext;

    /**
     * Constructor for the pages_edit_product_form class.
     *
     * The context has to be known before parent::__construct() runs, because that is what calls
     * definition(), and definition() builds the editor and the file manager against it.
     *
     * @param mixed $page The page data to be edited.
     * @param \core\context|null $context The context the page belongs to; system when omitted.
     */
    public function __construct($page, ?\core\context $context = null) {
        if ($page) {
            $this->callingpage = $page->id;
        }
        $this->pagecontext = $context ?? context_system::instance();
        parent::__construct(); // Call the parent constructor.
    }

    /**
     * The itemid this page's embedded files are stored under.
     *
     * A site-wide page shares one area at itemid 0, which is what every stored URL of every page
     * written before this stage names. A category page uses its own id, so that the file route can
     * authorise a file from the row it belongs to. A category page that has not been saved yet has
     * no id: its files are moved into place right after the insert (see renderer::save_page()).
     *
     * @param int $pageid Page id, or 0 for a page that does not exist yet.
     * @return int
     */
    protected function pagecontent_itemid(int $pageid): int {
        return $this->pagecontext->contextlevel == CONTEXT_COURSECAT ? $pageid : 0;
    }

    /**
     * Set the default data for the form fields.
     *
     * @param mixed $defaults The default values to set in the form.
     * @return mixed The result of the parent set_data method.
     */
    public function set_data($defaults) {
        $context = $this->pagecontext; // The context this page belongs to.
        $draftideditor = file_get_submitted_draft_itemid('pagecontent'); // Get the draft item ID for the editor.

        // Prepare the draft area for the page content.
        $defaults->pagecontent['text'] = file_prepare_draft_area(
            $draftideditor,
            $context->id,
            'local_page',
            'pagecontent',
            $this->pagecontent_itemid((int) ($defaults->id ?? 0)),
            ['subdirs' => true],
            $defaults->pagecontent['text']
        );

        $defaults->pagecontent['itemid'] = $draftideditor; // Set the item ID for the content.
        $defaults->pagecontent['format'] = FORMAT_HTML; // Set the format for the content.

        // Options for the file manager for the Open Graph image.
        $options = local_page_ogimage_filemanager_options();

        // Prepare the file manager for the Open Graph image.
        $defaults->ogimage = file_prepare_standard_filemanager(
            $defaults,
            'ogimage',
            $options,
            $context,
            'local_page',
            'ogimage',
            $defaults->id
        );

        return parent::set_data($defaults); // Call the parent set_data method.
    }

    /**
     * Define the form elements and structure.
     */
    public function definition() {
        global $DB, $PAGE;

        // Initialize the form.
        $mform = $this->_form;

        // Get a list of all pages for selection.
        $none = get_string("none", "local_page");
        $pages = [0 => $none];
        $allpages = $DB->get_records('local_page', ['deleted' => 0]); // Fetch all non-deleted pages.

        foreach ($allpages as $page) {
            if ($page->id != $this->callingpage) {
                $pages[$page->id] = $page->pagename; // Add page names to the selection.
            }
        }

        // Determine available layouts for the page.
        $hasstandard = false;
        $layouts = ["standard" => "standard"];
        $layoutkeys = array_keys($PAGE->theme->layouts);

        foreach ($layoutkeys as $layoutname) {
            if (strtolower($layoutname) != "standard") {
                $layouts[$layoutname] = $layoutname; // Add non-standard layouts.
            } else {
                $hasstandard = true; // Mark if standard layout exists.
            }
        }

        if (!$hasstandard) {
            unset($layouts['standard']); // Remove standard layout if not available.
        }

        // Page Details.
        $mform->addElement('header', 'details', get_string('details', 'moodle'));

        // Select Live or Draft.
        $mform->addElement(
            'select',
            'status',
            get_string('status', 'local_page'),
            [
                'live' => get_string('status_live', 'local_page'),
                'draft' => get_string('status_draft', 'local_page'),
                'archived' => get_string('status_archived', 'local_page'),
            ]
        );
        $mform->setDefault('status', 'live');
        $mform->setType('status', PARAM_ALPHA);

        // Hidden for non-logged in users.
        $mform->addElement(
            'select',
            'onlyloggedin',
            get_string('onlyloggedin', 'local_page'),
            [
                '0' => get_string('no'),
                '1' => get_string('yes'),
            ]
        );
        $mform->setDefault('onlyloggedin', '0');
        $mform->setType('onlyloggedin', PARAM_INT); // Set the type for the nonloggedin field.
        $mform->addHelpButton('onlyloggedin', 'onlyloggedin_description', 'local_page'); // Add help button.

        // Text area for the page name.
        $mform->addElement(
            'textarea',
            'pagename',
            get_string('page_name', 'local_page'),
            ['placeholder' => get_string('pagename_placeholder', 'local_page')]
        );
        $mform->setType('pagename', PARAM_TEXT); // Set the type for the page name.

        // Select Hide Title.
        $mform->addElement(
            'select',
            'hidetitle',
            get_string('hidetitle', 'local_page'),
            [
                'no' => get_string('no'),
                'yes' => get_string('yes'),
            ]
        );
        $mform->setDefault('hidetitle', 'no');
        $mform->setType('hidetitle', PARAM_ALPHA);

        // Date selector for the page date.
        $mform->addElement(
            'date_time_selector',
            'pagedate',
            get_string('form_field_date', 'local_page'),
            ['optional' => true]
        );
        $mform->setType('pagedate', PARAM_INT);
        $mform->addHelpButton('pagedate', 'pagedate_description', 'local_page'); // Add help button.

        // End date selector for the page date.
        $mform->addElement(
            'date_time_selector',
            'enddate',
            get_string('form_field_enddate', 'local_page'),
            ['optional' => true]
        );
        $mform->setType('enddate', PARAM_INT);
        $mform->addHelpButton('enddate', 'form_field_enddate_description', 'local_page'); // Add help button.

        // Text field for access level.
        $mform->addElement('text', 'accesslevel', get_string('accesslevel', 'local_page'));
        $mform->addHelpButton('accesslevel', 'accesslevel', 'local_page');
        $mform->setType('accesslevel', PARAM_TEXT); // Set the type for access level.

        // Text field for menu name.
        $mform->addElement('text', 'menuname', get_string('menu_name', 'local_page'));
        $mform->setType('menuname', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('menuname', 'menu_name_description', 'local_page'); // Add help button.

        // Page Display.
        $mform->addElement('header', 'htmlbody', get_string('page', 'moodle'));

        // Editor for page content.
        $context = $this->pagecontext; // The context this page belongs to.
        $editoroptions = ['maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true, 'context' => $context];

        $mform->addElement(
            'editor',
            'pagecontent',
            get_string('content', 'moodle'),
            get_string('page_content_description', 'local_page'),
            $editoroptions
        );

        // Page content is optional, so no required validation rule.
        $mform->setType('pagecontent', PARAM_RAW); // Set the type for page content.

        // Content HTML field.
        $mform->addElement(
            'textarea',
            'contenthtml',
            get_string('contenthtml', 'local_page'),
            [
                'rows' => 10,
                'cols' => 80,
                'placeholder' => get_string('contenthtml_placeholder', 'local_page'),
            ]
        );
        $mform->setType('contenthtml', PARAM_RAW); // Set the type for content HTML.
        $mform->addHelpButton('contenthtml', 'contenthtml_description', 'local_page'); // Add help button.

        // Head Content.
        $mform->addElement('header', 'htmlhead', get_string('additionalhtml', 'admin'));

        // Meta Description.
        $mform->addElement('textarea', 'metadescription', get_string('metadescription', 'local_page'));
        $mform->setType('metadescription', PARAM_TEXT); // Set the type for meta description.
        $mform->addHelpButton('metadescription', 'metadescription_description', 'local_page'); // Add help button.

        // Meta Keywords.
        $mform->addElement('textarea', 'metakeywords', get_string('metakeywords', 'local_page'));
        $mform->setType('metakeywords', PARAM_TEXT); // Set the type for meta keywords.
        $mform->addHelpButton('metakeywords', 'metakeywords_description', 'local_page'); // Add help button.

        // Meta Author.
        $mform->addElement('text', 'metaauthor', get_string('metaauthor', 'local_page'));
        $mform->setType('metaauthor', PARAM_TEXT); // Set the type for meta author.
        $mform->addHelpButton('metaauthor', 'metaauthor_description', 'local_page'); // Add help button.

        // Meta Title.
        $mform->addElement('textarea', 'metatitle', get_string('metatitle', 'local_page'));
        $mform->setType('metatitle', PARAM_TEXT); // Set the type for meta title.
        $mform->addHelpButton('metatitle', 'metatitle_description', 'local_page'); // Add help button.

        // Meta Robots.
        $mform->addElement('text', 'metarobots', get_string('metarobots', 'local_page'));
        $mform->setType('metarobots', PARAM_TEXT); // Set the type for meta robots.
        $mform->addHelpButton('metarobots', 'metarobots_description', 'local_page'); // Add help button.
        if (get_config('local_page', 'additionalhead')) {
            // Text area for additional HTML head content.
            $mform->addElement('textarea', 'meta', get_string('edit_head', 'local_page'));
            $mform->setType('meta', PARAM_RAW); // Set the type for meta content.
        }

        // File manager for Open Graph image.
        $options = local_page_ogimage_filemanager_options();
        $mform->addElement('filemanager', 'ogimage_filemanager', get_string('edit_ogimage', 'local_page'), null, $options);

        // Form Buttons.
        $this->add_action_buttons(); // Add standard form action buttons.

        // Hidden field for page ID.
        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT); // Set the type for the ID field.

        /*
         * Hidden transport for the context a NEW page is being created in, carrying the stored
         * convention where 0 means the system context. It is re-checked on the way back in by
         * local_page_require_editable_page(), and for a page that already exists it is discarded
         * in favour of the stored row's context — a posted value may not move a page.
         */
        $mform->addElement('hidden', 'contextid', \local_page\local\scope::stored_contextid($this->pagecontext));
        $mform->setType('contextid', PARAM_INT);
    }

    /**
     * Server-side validation for the fields whose stored value is a rule rather than text.
     *
     * Two of this form's fields are read back as instructions rather than displayed, and neither
     * had any validation at all: a typo in "Required capability" was stored happily and changed who
     * could see the page, and a friendly URL could be typed over another page's.
     *
     * The access level is parsed exactly the way local_page_user_can_view_page() parses it, so that
     * what is refused here is what would have been evaluated there. The read side is deliberately
     * left alone: it must keep answering for rows saved before this form existed.
     *
     * The friendly URL is refused on two counts: a name Moodle itself answers on, and a name
     * another live page of the same context already holds. Only the second one is scoped — see
     * \local_page\local\slug::is_reserved() for why stored rows are never renamed to match.
     *
     * @param array $data Submitted values, "fieldname" => value
     * @param array $files Uploaded files, unused here
     * @return array Errors keyed by element name, empty when everything is acceptable
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $accesslevel = trim((string) ($data['accesslevel'] ?? ''));
        if ($accesslevel !== '') {
            $unknown = null;
            $positives = 0;
            $entries = 0;

            foreach (explode(',', $accesslevel) as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }
                $entries++;

                $negated = substr($entry, 0, 1) === '!';
                $capability = trim($negated ? substr($entry, 1) : $entry);
                if (!$negated) {
                    $positives++;
                }

                if ($unknown === null && ($capability === '' || get_capability_info($capability) === null)) {
                    $unknown = $entry;
                }
            }

            if ($unknown !== null) {
                $errors['accesslevel'] = get_string('accesslevel_unknowncapability', 'local_page', $unknown);
            } else if ($entries > 0 && $positives === 0) {
                /*
                 * A list of negations only grants the page to everyone. The predicate starts at
                 * "no access" and a negated entry flips that to "access" for anyone who does NOT
                 * hold the capability — which is every anonymous visitor, since administrators were
                 * already admitted further up. Refusing it here is what closes that hole; the
                 * predicate keeps reading already-stored rows as it always did.
                 */
                $errors['accesslevel'] = get_string('accesslevel_negationonly', 'local_page');
            }
        }

        /*
         * Compared lower-cased and trimmed because that is the form the renderer stores. The
         * context decides both refusals: a slug is reserved site-wide, but it is only "taken"
         * within the scope that owns it, so two categories may each have a "contato".
         */
        $menuname = \core_text::strtolower(trim((string) ($data['menuname'] ?? '')));
        $contextid = (int) ($data['contextid'] ?? 0);
        if ($menuname !== '' && \local_page\local\slug::is_reserved($menuname)) {
            $errors['menuname'] = get_string('menuname_reserved', 'local_page');
        } else if ($menuname !== '' && \local_page\local\slug::is_taken($menuname, (int) ($data['id'] ?? 0), $contextid)) {
            $errors['menuname'] = get_string('menuname_taken', 'local_page');
        }

        return $errors;
    }
}
