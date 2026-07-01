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
     * Get available appearance designs with display metadata for tile selectors.
     *
     * @return array List of design metadata objects.
     */
    public static function get_appearance_design_tiles(): array {
        global $DB;

        $tiles = [
            (object) [
                'value' => '',
                'name' => get_string('none'),
                'type' => '',
                'typename' => get_string('default'),
                'previewurl' => '',
            ],
        ];

        $designs = $DB->get_records('lesson_appearance_designs', ['enabled' => 1], 'name ASC', 'id, uniqueid, name, type');
        foreach ($designs as $design) {
            $component = 'lessonappearance_' . $design->type;
            $typename = get_string_manager()->string_exists('pluginname', $component)
                ? get_string('pluginname', $component)
                : $design->type;

            $tiles[] = (object) [
                'value' => $design->uniqueid,
                'name' => format_string($design->name),
                'type' => $design->type,
                'typename' => $typename,
                'previewurl' => self::get_appearance_design_preview_url($design),
            ];
        }

        return $tiles;
    }

    /**
     * Get a preview image URL for an appearance design when its subplugin provides one.
     *
     * @param \stdClass $design Appearance design record.
     * @return string
     */
    private static function get_appearance_design_preview_url(\stdClass $design): string {
        $context = \context_system::instance();
        $component = 'lessonappearance_' . $design->type;
        $filearea = 'appearance_preview';

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, $component, $filearea, $design->id, 'sortorder', false);
        if (!$files) {
            return '';
        }

        $file = reset($files);
        if (!$file) {
            return '';
        }

        return \moodle_url::make_pluginfile_url(
            $context->id,
            $component,
            $filearea,
            $design->id,
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
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
