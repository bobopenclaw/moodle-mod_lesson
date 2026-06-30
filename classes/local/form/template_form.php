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

namespace mod_lesson\local\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Add/edit form for a lesson design template.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('design_name', 'lesson'), ['size' => 48]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('design_idnumber', 'lesson'), ['size' => 32]);
        $mform->setType('idnumber', PARAM_ALPHANUMEXT);
        $mform->addRule('idnumber', null, 'required', null, 'client');

        $skins = [];
        foreach (\mod_lesson\local\config_schema::baseskins() as $key => $stringkey) {
            $skins[$key] = get_string($stringkey, 'lesson');
        }
        $mform->addElement('select', 'baseskin', get_string('design_baseskin', 'lesson'), $skins);

        $mform->addElement('textarea', 'config', get_string('design_config', 'lesson'), ['rows' => 10, 'cols' => 60]);
        $mform->setType('config', PARAM_RAW);
        $mform->setDefault('config', '{}');

        $mform->addElement('advcheckbox', 'enabled', get_string('design_enabled', 'lesson'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Server-side validation of the config JSON against the chosen skin.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Array of errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $decoded = json_decode((string) $data['config'], true);
        if ($data['config'] !== '' && $decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors['config'] = get_string('design_configinvalidjson', 'lesson');
            return $errors;
        }
        [$valid, $verrors] = \mod_lesson\local\config_schema::validate($data['baseskin'], $decoded ?? []);
        if (!$valid) {
            $errors['config'] = get_string('design_configinvalid', 'lesson', implode(', ', $verrors));
        }
        return $errors;
    }
}
