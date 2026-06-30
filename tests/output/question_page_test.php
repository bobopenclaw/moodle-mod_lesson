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

namespace mod_lesson\output;

/**
 * Tests for the question_page renderable.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_lesson\output\question_page
 */
final class question_page_test extends \advanced_testcase {
    /**
     * export_for_template exposes the answers and the form fields needed by continue.php.
     */
    public function test_export_for_template_lists_answers(): void {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/mod/lesson/locallib.php');

        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $lessonrec = $gen->create_module('lesson', ['course' => $course->id, 'design' => 'monsterwelt']);
        $cm = get_coursemodule_from_instance('lesson', $lessonrec->id);
        $lessonrec = $DB->get_record('lesson', ['id' => $lessonrec->id], '*', MUST_EXIST);
        $lessonrec->cmid = $cm->id;

        /** @var \mod_lesson_generator $lgen */
        $lgen = $gen->get_plugin_generator('mod_lesson');
        $pagerec = $lgen->create_question_multichoice($lessonrec, []);

        // The page rendering relies on $PAGE->cm being set.
        $PAGE->set_cm($cm, $course);

        $lesson = new \lesson($lessonrec, $cm, $course);
        $page = $lesson->load_page($pagerec->id);

        $renderable = new question_page($lesson, $page, null, $cm->id);
        $renderer = $PAGE->get_renderer('mod_lesson');
        $data = $renderable->export_for_template($renderer);

        $this->assertCount(2, $data['answers']);
        $this->assertSame(sesskey(), $data['sesskey']);
        $this->assertStringContainsString('continue.php', $data['formaction']);
        $this->assertSame((int) $cm->id, (int) $data['cmid']);
        $this->assertSame((int) $pagerec->id, (int) $data['pageid']);
        $this->assertStringStartsWith('_qf__', $data['qfmarkername']);
        $this->assertFalse($data['multiple']);
        $this->assertSame('answerid', $data['answers'][0]['name']);
        $this->assertSame('radio', $data['answers'][0]['type']);
    }
}
