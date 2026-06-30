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

use mod_lesson\local\template_manager;

/**
 * Renderable for a lesson question page under a non-default design skin.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_page implements \renderable, \templatable {
    /** @var \lesson The lesson. */
    protected $lesson;

    /** @var \lesson_page The question page. */
    protected $page;

    /** @var object|null The current attempt, if any. */
    protected $attempt;

    /** @var int The course module id. */
    protected $cmid;

    /**
     * Constructor.
     *
     * @param \lesson $lesson The lesson.
     * @param \lesson_page $page The question page.
     * @param object|null $attempt The current attempt, if any.
     * @param int $cmid The course module id.
     */
    public function __construct(\lesson $lesson, \lesson_page $page, $attempt, int $cmid) {
        $this->lesson = $lesson;
        $this->page = $page;
        $this->attempt = $attempt;
        $this->cmid = $cmid;
    }

    /**
     * Export data for the mustache template.
     *
     * @param \renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $CFG, $USER;

        $tpl = template_manager::get_for_lesson($this->lesson->properties());
        $config = template_manager::resolved_config($tpl);

        $pageprops = $this->page->properties();
        $multiple = !empty($pageprops->qoption);
        $formname = $multiple
            ? 'lesson_display_answer_form_multichoice_multianswer'
            : 'lesson_display_answer_form_multichoice_singleanswer';

        $answers = $this->page->get_used_answers();
        shuffle($answers);
        $textoptions = ['para' => false, 'noclean' => true];

        $hasattempt = isset($USER->modattempts[$this->lesson->id])
            && !empty($USER->modattempts[$this->lesson->id]);
        $useransrid = $hasattempt ? ($USER->modattempts[$this->lesson->id]->answerid ?? 0) : 0;

        $answerdata = [];
        foreach ($answers as $answer) {
            $answerdata[] = [
                'label' => format_text($answer->answer, $answer->answerformat, $textoptions),
                'value' => $multiple ? 1 : $answer->id,
                'name' => $multiple ? 'answer[' . $answer->id . ']' : 'answerid',
                'type' => $multiple ? 'checkbox' : 'radio',
                'checked' => (!$multiple && $answer->id == $useransrid),
                'disabled' => $hasattempt,
            ];
        }

        $lessonprops = $this->lesson->properties();

        return [
            'formaction' => $CFG->wwwroot . '/mod/lesson/continue.php',
            'sesskey' => sesskey(),
            'qfmarkername' => '_qf__' . $formname,
            'cmid' => $this->cmid,
            'pageid' => $pageprops->id,
            'contents' => $this->page->get_contents(),
            'multiple' => $multiple,
            'answers' => $answerdata,
            'answercolumns' => (int) ($config['answercolumns'] ?? 2),
            'showprogress' => !empty($config['showprogress']),
            'nav' => [
                'showback' => !empty($config['nav']['showback']),
                'showretry' => !empty($config['nav']['showretry']) && !empty($lessonprops->retake),
                'shownext' => !empty($config['nav']['shownext']),
            ],
            'palette' => $config['palette'] ?? [],
            'submitlabel' => get_string('submit', 'lesson'),
        ];
    }
}
