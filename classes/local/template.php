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

namespace mod_lesson\local;

/**
 * Persistent for an admin-managed lesson design template.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template extends \core\persistent {
    /** @var string Table name. */
    const TABLE = 'lesson_design_template';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => ['type' => PARAM_TEXT],
            'idnumber' => ['type' => PARAM_ALPHANUMEXT],
            'baseskin' => ['type' => PARAM_ALPHANUMEXT, 'default' => 'default'],
            'config' => ['type' => PARAM_RAW, 'default' => '{}'],
            'enabled' => ['type' => PARAM_INT, 'default' => 1],
            'sortorder' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }

    /**
     * Validate that the idnumber is unique.
     *
     * @param string $value The proposed idnumber.
     * @return true|\lang_string True when valid, otherwise an error string.
     */
    protected function validate_idnumber($value) {
        $params = ['idnumber' => $value];
        $select = 'idnumber = :idnumber';
        if ($this->get('id')) {
            $select .= ' AND id <> :id';
            $params['id'] = $this->get('id');
        }
        if (self::record_exists_select($select, $params)) {
            return new \lang_string('design_idnumbertaken', 'lesson');
        }
        return true;
    }

    /**
     * Validate the config JSON against the base skin allowlist.
     *
     * @param string $value The proposed config JSON.
     * @return true|\lang_string True when valid, otherwise an error string.
     */
    protected function validate_config($value) {
        $decoded = json_decode($value, true);
        if ($decoded === null && $value !== '{}' && trim($value) !== '') {
            return new \lang_string('design_configinvalidjson', 'lesson');
        }
        [$valid, $errors] = config_schema::validate($this->get('baseskin'), $decoded ?? []);
        if (!$valid) {
            return new \lang_string('design_configinvalid', 'lesson', implode(', ', $errors));
        }
        return true;
    }
}
