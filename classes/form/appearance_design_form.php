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

namespace mod_lesson\form;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing appearance designs.
 *
 * @package    mod_lesson
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appearance_design_form extends \moodleform {

    /** @var string File area for design preview images. */
    private const PREVIEW_FILEAREA = 'appearance_preview';

    /**
     * File manager options for the preview image.
     *
     * @return array
     */
    public static function get_preview_filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ];
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $design = $this->_customdata['design'];
        $context = $this->_customdata['context'];

        // General fields.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'uniqueid', get_string('uniqueid', 'lesson'), ['size' => '64']);
        $mform->setType('uniqueid', PARAM_TEXT);
        $mform->addRule('uniqueid', null, 'required', null, 'client');
        $mform->addRule('uniqueid', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('uniqueid', 'uniqueid', 'lesson');

        // Build type options from installed lessonappearance subplugins.
        $types = \core_plugin_manager::instance()->get_installed_plugins('lessonappearance');
        $typeoptions = [];
        foreach ($types as $name => $version) {
            $typeoptions[$name] = get_string('pluginname', 'lessonappearance_' . $name);
        }

        $mform->addElement('select', 'type', get_string('type', 'lesson'), $typeoptions);
        $mform->addRule('type', null, 'required', null, 'client');

        $draftitemid = file_get_submitted_draft_itemid(self::PREVIEW_FILEAREA);
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'mod_lesson',
            self::PREVIEW_FILEAREA,
            $design->id ?? 0,
            self::get_preview_filemanager_options()
        );
        $mform->addElement(
            'filemanager',
            self::PREVIEW_FILEAREA,
            get_string('previewimage', 'lesson'),
            null,
            self::get_preview_filemanager_options()
        );
        $mform->addHelpButton(self::PREVIEW_FILEAREA, 'previewimage', 'lesson');
        $mform->setDefault(self::PREVIEW_FILEAREA, $draftitemid);

        // Hidden id field.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();

        // Set existing data.
        if (!empty($design->id)) {
            $this->set_data($design);
        }
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        // Check uniqueid is unique.
        $params = ['uniqueid' => $data['uniqueid']];
        $sql = "SELECT id FROM {lesson_appearance_designs} WHERE uniqueid = :uniqueid";
        if (!empty($data['id'])) {
            $sql .= " AND id != :id";
            $params['id'] = $data['id'];
        }
        if ($DB->record_exists_sql($sql, $params)) {
            $errors['uniqueid'] = get_string('uniqueidalreadyexists', 'lesson');
        }

        return $errors;
    }
}
