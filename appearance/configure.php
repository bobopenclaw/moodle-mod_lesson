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
 * Configure a lesson appearance design (subplugin-specific settings).
 *
 * @package    mod_lesson
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('mod_lesson_managedesigns');

$id = required_param('id', PARAM_INT);
$context = context_system::instance();

require_capability('mod/lesson:manageappearancedesigns', $context);

$design = $DB->get_record('lesson_appearance_designs', ['id' => $id], '*', MUST_EXIST);

$manageurl = new moodle_url('/mod/lesson/appearance/manage.php');
$pageurl = new moodle_url('/mod/lesson/appearance/configure.php', ['id' => $id]);
$title = get_string('configuredesign', 'lesson', $design->name);

$PAGE->set_url($pageurl);
$PAGE->set_title($title);

$mform = new \mod_lesson\form\appearance_design_config_form($pageurl, [
    'design' => $design,
    'context' => $context,
]);

if ($mform->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $mform->get_data()) {
    // Let the subplugin save its config/files.
    $classname = "lessonappearance_{$design->type}\\appearance";
    if (class_exists($classname)) {
        $plugin = new $classname();
        $plugin->save_config_form_data($data, $design->id, $context);
    }

    // Update timemodified.
    $DB->set_field('lesson_appearance_designs', 'timemodified', time(), ['id' => $design->id]);
    $DB->set_field('lesson_appearance_designs', 'usermodified', $USER->id, ['id' => $design->id]);

    \core\notification::add(get_string('changessaved'), \core\notification::SUCCESS);
    redirect($manageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$mform->display();
echo $OUTPUT->footer();
