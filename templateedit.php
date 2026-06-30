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
 * Add/edit page for a lesson design template.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('lessontemplates');
require_capability('mod/lesson:managetemplates', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);
$pageurl = new moodle_url('/mod/lesson/templateedit.php', $id ? ['id' => $id] : []);
$PAGE->set_url($pageurl);

$template = $id ? new \mod_lesson\local\template($id) : null;

$form = new \mod_lesson\local\form\template_form($pageurl->out(false));
if ($template) {
    $form->set_data((object) [
        'id' => $template->get('id'),
        'name' => $template->get('name'),
        'idnumber' => $template->get('idnumber'),
        'baseskin' => $template->get('baseskin'),
        'config' => $template->get('config'),
        'enabled' => $template->get('enabled'),
    ]);
}

$returnurl = new moodle_url('/mod/lesson/templates.php');
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    unset($data->submitbutton);
    if ($template) {
        $template->from_record($data);
        $template->update();
    } else {
        $template = new \mod_lesson\local\template(0, $data);
        $template->create();
    }
    redirect($returnurl, get_string('changessaved'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($id ? 'design_edittemplate' : 'design_addtemplate', 'lesson'));
$form->display();
echo $OUTPUT->footer();
