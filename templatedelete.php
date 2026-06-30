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
 * Delete confirmation for a lesson design template.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('lessontemplates');
require_capability('mod/lesson:managetemplates', context_system::instance());

$id = required_param('id', PARAM_INT);
require_sesskey();

$template = new \mod_lesson\local\template($id);
$returnurl = new moodle_url('/mod/lesson/templates.php');

if ($template->get('idnumber') === 'default') {
    throw new moodle_exception('design_cannotdeletebuiltin', 'lesson', $returnurl);
}

$confirm = optional_param('confirm', 0, PARAM_BOOL);
if ($confirm) {
    require_sesskey();
    $template->delete();
    redirect($returnurl, get_string('changessaved'));
}

$PAGE->set_url(new moodle_url('/mod/lesson/templatedelete.php', ['id' => $id]));

echo $OUTPUT->header();
$confirmurl = new moodle_url('/mod/lesson/templatedelete.php', ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->confirm(
    get_string('design_deleteconfirm', 'lesson', format_string($template->get('name'))),
    $confirmurl,
    $returnurl
);
echo $OUTPUT->footer();
