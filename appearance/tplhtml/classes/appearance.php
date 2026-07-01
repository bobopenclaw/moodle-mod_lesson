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
 * HTML Template appearance class for lesson appearance subplugins.
 *
 * @package    lessonappearance_tplhtml
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace lessonappearance_tplhtml;

class appearance extends \lessonappearance_base\appearance {

    /** @var array Config keys stored in configdata. */
    private const CONFIG_KEYS = [
        'templatehtml',
        'continuehtml',
        'customcss',
    ];

    /**
     * Load the configdata for a given lesson's design.
     *
     * @param \lesson $lesson The lesson instance.
     * @return array The decoded configdata array.
     */
    private function get_design_config(\lesson $lesson): array {
        global $DB;

        $design = $DB->get_record('lesson_appearance_designs', ['uniqueid' => $lesson->appearance]);
        if (!$design || empty($design->configdata)) {
            return [];
        }

        return json_decode($design->configdata, true) ?: [];
    }

    /**
     * Process an HTML template by replacing placeholders with actual content.
     *
     * @param string $template The HTML template with {placeholder} tokens.
     * @param array $replacements Associative array of placeholder => content.
     * @return string The processed HTML.
     */
    private function process_template(string $template, array $replacements): string {
        $search = [];
        $replace = [];
        foreach ($replacements as $key => $value) {
            $search[] = '{' . $key . '}';
            $replace[] = $value;
        }
        return str_replace($search, $replace, $template);
    }

    public function render(
        \lesson $lesson,
        \lesson_page $page,
        \mod_lesson_renderer $renderer,
        $attempt,
        string $progressbar = ''
    ): string {
        global $PAGE;

        $configdata = $this->get_design_config($lesson);

        $templatehtml = $configdata['templatehtml'] ?? '';
        if (empty($templatehtml)) {
            // Fallback to base rendering if no template is configured.
            return parent::render($lesson, $page, $renderer, $attempt, $progressbar);
        }

        $replacements = [
            'lesson_content' => $page->get_page_content($renderer),
            'lesson_qtype' => $page->get_qtype_content($renderer, $attempt),
            'progress_bar' => $progressbar,
            'navigation_buttons' => $page->get_navigation_buttons($renderer),
        ];

        $processedhtml = $this->process_template($templatehtml, $replacements);
        $customcss = $configdata['customcss'] ?? '';

        $output = $PAGE->get_renderer('mod_lesson');
        return $output->render_from_template($this->get_template_name(), [
            'processedhtml' => $processedhtml,
            'customcss' => $customcss,
            'hascss' => !empty($customcss),
        ]);
    }

    public function render_continue(
        \lesson $lesson,
        \lesson_page $page,
        \stdClass $result,
        bool $reviewmode
    ): string {
        global $PAGE, $OUTPUT, $USER;

        $configdata = $this->get_design_config($lesson);

        $continuehtml = $configdata['continuehtml'] ?? '';
        if (empty($continuehtml)) {
            return '';
        }

        $cm = $lesson->cm;
        $lessonoutput = $PAGE->get_renderer('mod_lesson');

        // Build feedback.
        $feedback = '';
        if (!$reviewmode) {
            $context = $lesson->context;
            $feedback = format_text($result->feedback, FORMAT_MOODLE, ['context' => $context, 'noclean' => true]);
        }

        // Build ongoing score.
        $ongoingscore = ($lesson->ongoing && !$reviewmode) ? $lessonoutput->ongoing_score($lesson) : '';

        // Build navigation buttons.
        $buttons = '';

        if (isset($USER->modattempts[$lesson->id])) {
            $buttons .= $OUTPUT->box(get_string('gotoendoflesson', 'lesson'), 'center');
            $buttons .= $OUTPUT->box(get_string('or', 'lesson'), 'center');
            $buttons .= $OUTPUT->box(get_string('continuetonextpage', 'lesson'), 'center');
            $finishurl = new \moodle_url('/mod/lesson/view.php', ['id' => $cm->id, 'pageid' => LESSON_EOL]);
            $buttons .= $OUTPUT->single_button($finishurl, get_string('finish', 'lesson'));
        }

        $canreview = !$result->correctanswer && !$result->noanswer && !$result->isessayquestion
                     && !$reviewmode && $lesson->review && !$result->maxattemptsreached;
        if ($canreview) {
            $reviewurl = new \moodle_url('/mod/lesson/view.php', ['id' => $cm->id, 'pageid' => $page->id]);
            $buttons .= $OUTPUT->single_button($reviewurl, get_string('reviewquestionback', 'lesson'));
        }

        $continueurl = new \moodle_url('/mod/lesson/view.php', ['id' => $cm->id, 'pageid' => $result->newpageid]);
        $showreviewcontinue = $lesson->review && !$result->correctanswer && !$result->noanswer
                              && !$result->isessayquestion && !$result->maxattemptsreached;
        if ($showreviewcontinue) {
            if ($page->id != $result->newpageid) {
                $buttons .= $OUTPUT->single_button($continueurl, get_string('reviewquestioncontinue', 'lesson'));
            }
        } else {
            $buttons .= $OUTPUT->single_button($continueurl, get_string('continue', 'lesson'));
        }

        $replacements = [
            'lesson_feedback' => $feedback,
            'navigation_buttons' => $buttons,
            'ongoing_score' => $ongoingscore,
        ];

        $processedhtml = $this->process_template($continuehtml, $replacements);
        $customcss = $configdata['customcss'] ?? '';

        $output = $PAGE->get_renderer('mod_lesson');
        return $output->render_from_template('lessonappearance_tplhtml/continue', [
            'processedhtml' => $processedhtml,
            'customcss' => $customcss,
            'hascss' => !empty($customcss),
        ]);
    }

    public function get_config_form_elements(\MoodleQuickForm $mform, int $designid = 0): void {
        $component = 'lessonappearance_tplhtml';

        // Load existing configdata.
        $configdata = [];
        if ($designid) {
            global $DB;
            $record = $DB->get_field('lesson_appearance_designs', 'configdata', ['id' => $designid]);
            if ($record) {
                $configdata = json_decode($record, true) ?: [];
            }
        }

        // Page template.
        $mform->addElement('header', 'tplhtml_page', get_string('templatehtml', $component));

        $mform->addElement('static', 'placeholders_page', get_string('availableplaceholders', $component),
            '<code>' . get_string('placeholders_page', $component) . '</code>');

        $mform->addElement('textarea', 'templatehtml', get_string('templatehtml', $component),
            ['rows' => 15, 'cols' => 80]);
        $mform->setType('templatehtml', PARAM_RAW);
        $mform->addHelpButton('templatehtml', 'templatehtml', $component);
        if (isset($configdata['templatehtml'])) {
            $mform->setDefault('templatehtml', $configdata['templatehtml']);
        }

        // Continue template.
        $mform->addElement('header', 'tplhtml_continue', get_string('continuehtml', $component));

        $mform->addElement('static', 'placeholders_continue', get_string('availableplaceholders', $component),
            '<code>' . get_string('placeholders_continue', $component) . '</code>');

        $mform->addElement('textarea', 'continuehtml', get_string('continuehtml', $component),
            ['rows' => 15, 'cols' => 80]);
        $mform->setType('continuehtml', PARAM_RAW);
        $mform->addHelpButton('continuehtml', 'continuehtml', $component);
        if (isset($configdata['continuehtml'])) {
            $mform->setDefault('continuehtml', $configdata['continuehtml']);
        }

        // Custom CSS.
        $mform->addElement('header', 'tplhtml_css', get_string('customcss', $component));

        $mform->addElement('textarea', 'customcss', get_string('customcss', $component),
            ['rows' => 10, 'cols' => 80]);
        $mform->setType('customcss', PARAM_RAW);
        $mform->addHelpButton('customcss', 'customcss', $component);
        if (isset($configdata['customcss'])) {
            $mform->setDefault('customcss', $configdata['customcss']);
        }
    }

    public function save_config_form_data(\stdClass $data, int $designid, \context $context): void {
        global $DB;

        $record = $DB->get_field('lesson_appearance_designs', 'configdata', ['id' => $designid]);
        $configdata = $record ? (json_decode($record, true) ?: []) : [];

        foreach (self::CONFIG_KEYS as $key) {
            if (isset($data->$key)) {
                $configdata[$key] = $data->$key;
            }
        }

        $DB->set_field('lesson_appearance_designs', 'configdata', json_encode($configdata), ['id' => $designid]);
    }
}
