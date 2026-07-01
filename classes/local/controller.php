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
 * Class controller
 *
 * @package    mod_lesson
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controller {
    /**
     * Get all available appearance designs.
     *
     * @return array List of designs indexed by uniqueid, value is the name.
     */
    public static function get_appearance_designs(): array {
        global $DB;

        $designs = $DB->get_records('lesson_appearance_designs', null, 'name ASC', 'id, uniqueid, name, type');

        $options = ['' => get_string('none')];
        foreach ($designs as $design) {
            $options[$design->uniqueid] = $design->name;
        }

        return $options;
    }

    /**
     * Resolve the appearance subplugin instance for a given lesson.
     *
     * Looks up the lesson's selected design (by uniqueid), loads the
     * corresponding lessonappearance subplugin class, and returns it.
     *
     * @param \lesson $lesson The lesson instance.
     * @return \lessonappearance_base\appearance|null The appearance instance or null if none selected.
     */
    public static function get_appearance_instance(\lesson $lesson): ?\lessonappearance_base\appearance {
        global $DB;

        $appearanceid = $lesson->appearance ?? '';
        if (empty($appearanceid)) {
            return null;
        }

        $design = $DB->get_record('lesson_appearance_designs', ['uniqueid' => $appearanceid]);
        if (!$design || empty($design->type)) {
            return null;
        }

        $classname = "\\lessonappearance_{$design->type}\\appearance";
        if (!class_exists($classname)) {
            return null;
        }

        return new $classname();
    }
}
