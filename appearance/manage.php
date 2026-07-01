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
 * Manage lesson appearance designs.
 *
 * @package    mod_lesson
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('mod_lesson_managedesigns');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$pageurl = new moodle_url('/mod/lesson/appearance/manage.php');
$context = context_system::instance();

require_capability('mod/lesson:manageappearancedesigns', $context);

// Handle delete action.
if ($action === 'delete' && $id) {
    require_sesskey();
    $design = $DB->get_record('lesson_appearance_designs', ['id' => $id], '*', MUST_EXIST);

    // Delete associated files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_lesson', 'appearance_preview', $id);
    if (!empty($design->type)) {
        $fs->delete_area_files($context->id, 'lessonappearance_' . $design->type, 'appearance_preview', $id);
    }

    $DB->delete_records('lesson_appearance_designs', ['id' => $id]);

    \core\notification::add(get_string('designdeleted', 'lesson'), \core\notification::SUCCESS);
    redirect($pageurl);
}

$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('manageappearancedesigns', 'lesson'));

// Build the report.
$report = \core_reportbuilder\system_report_factory::create(
    \mod_lesson\reportbuilder\local\systemreports\appearance_designs_list::class,
    $context,
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageappearancedesigns', 'lesson'));

// Add "Create new design" button.
$addurl = new moodle_url('/mod/lesson/appearance/edit.php');
echo html_writer::div(
    $OUTPUT->single_button($addurl, get_string('createnewdesign', 'lesson'), 'get'),
    'mb-3'
);

echo $report->output();
echo $OUTPUT->footer();
