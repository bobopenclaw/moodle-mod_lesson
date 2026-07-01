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
 * Edit a lesson appearance design.
 *
 * @package    mod_lesson
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('mod_lesson_managedesigns');

$id = optional_param('id', 0, PARAM_INT);
$context = context_system::instance();

require_capability('mod/lesson:manageappearancedesigns', $context);

$manageurl = new moodle_url('/mod/lesson/appearance/manage.php');

if ($id) {
    $design = $DB->get_record('lesson_appearance_designs', ['id' => $id], '*', MUST_EXIST);
    $title = get_string('editdesign', 'lesson');
} else {
    $design = new stdClass();
    $design->id = 0;
    $title = get_string('createnewdesign', 'lesson');
}

$pageurl = new moodle_url('/mod/lesson/appearance/edit.php', ['id' => $id]);
$PAGE->set_url($pageurl);
$PAGE->set_title($title);

$mform = new \mod_lesson\form\appearance_design_form($pageurl, ['design' => $design, 'context' => $context]);

if ($mform->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $mform->get_data()) {
    $now = time();

    if ($design->id) {
        // Update existing.
        $design->name = $data->name;
        $design->uniqueid = $data->uniqueid;
        $design->type = $data->type;
        $design->timemodified = $now;
        $design->usermodified = $USER->id;
        $DB->update_record('lesson_appearance_designs', $design);
    } else {
        // Create new.
        $design->name = $data->name;
        $design->uniqueid = $data->uniqueid;
        $design->type = $data->type;
        $design->timecreated = $now;
        $design->timemodified = $now;
        $design->usermodified = $USER->id;
        $design->id = $DB->insert_record('lesson_appearance_designs', $design);
    }

    \core\notification::add(get_string('changessaved'), \core\notification::SUCCESS);
    redirect($manageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$mform->display();
echo $OUTPUT->footer();
