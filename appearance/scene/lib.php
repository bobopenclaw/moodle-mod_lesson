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
 * Library functions for lessonappearance_scene.
 *
 * @package    lessonappearance_scene
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve the files from the lessonappearance_scene file areas.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context.
 * @param string $filearea The name of the file area.
 * @param array $args Extra arguments (itemid, filepath, filename).
 * @param bool $forcedownload Whether or not force download.
 * @param array $options Additional options affecting the file serving.
 * @return bool false if the file not found, just send the file otherwise.
 */
function lessonappearance_scene_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {

    $validareas = [
        'appearance_background',
        'appearance_character',
        'appearance_flag',
        'appearance_sprite',
        'appearance_preview',
        'appearance_good',
        'appearance_bad',
    ];

    if (!in_array($filearea, $validareas)) {
        return false;
    }

    $itemid = (int) array_shift($args);
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/lessonappearance_scene/{$filearea}/{$itemid}/{$relativepath}";

    $fs = get_file_storage();
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
