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
 * Scene appearance class for lesson appearance subplugins.
 *
 * @package    lessonappearance_scene
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace lessonappearance_scene;

class appearance extends \lessonappearance_base\appearance {

    /** @var string Component name for file storage. */
    private const COMPONENT = 'lessonappearance_scene';

    /** @var array File areas used by this subplugin. */
    private const FILE_AREAS = [
        'appearance_background',
        'appearance_character',
        'appearance_flag',
        'appearance_sprite',
        'appearance_preview',
        'appearance_good',
        'appearance_bad',
    ];

    /** @var array Color config keys. */
    private const COLOR_KEYS = [
        'color_primary',
        'color_secondary',
        'color_good',
        'color_bad',
        'color_default',
    ];

    /**
     * File manager options for single image upload.
     *
     * @return array
     */
    private function get_filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ];
    }

    /**
     * Render the lesson page using the scene appearance template.
     *
     * @param \lesson $lesson The lesson instance.
     * @param \lesson_page $page The current page being displayed.
     * @param \mod_lesson_renderer $renderer The lesson renderer.
     * @param object|false $attempt The user's previous attempt, or false.
     * @param string $progressbar Pre-rendered progress bar HTML.
     * @return string The rendered HTML output.
     */
    public function render(
        \lesson $lesson,
        \lesson_page $page,
        \mod_lesson_renderer $renderer,
        $attempt,
        string $progressbar = ''
    ): string {
        global $PAGE, $DB;

        $data = [
            'content' => $page->get_page_content($renderer),
            'pagetype' => $page->get_typestring(),
            'attributes' => $this->get_page_attributes($lesson, $page),
            'qtypecontent' => $page->get_qtype_content($renderer, $attempt),
            'progress_bar' => $progressbar,
            'navigation_buttons' => $page->get_navigation_buttons($renderer),
        ];

        // Load design record.
        $design = $DB->get_record('lesson_appearance_designs', ['uniqueid' => $lesson->appearance]);
        if ($design) {
            $context = \context_system::instance();
            $configdata = !empty($design->configdata) ? (json_decode($design->configdata, true) ?: []) : [];

            // File areas to expose in the template (excluding preview).
            $fileareas = [
                'appearance_background' => 'background',
                'appearance_character'  => 'character',
                'appearance_flag'       => 'flag',
                'appearance_sprite'     => 'sprite',
                'appearance_good'       => 'good',
                'appearance_bad'        => 'bad',
            ];

            foreach ($fileareas as $filearea => $key) {
                $url = $this->get_file_url($context, $filearea, $design->id);
                $data[$key . '_url'] = $url;
                $data['has' . $key] = !empty($url);
            }

            // Colors as template variables.
            foreach (self::COLOR_KEYS as $colorkey) {
                $data[$colorkey] = $configdata[$colorkey] ?? '';
            }
        }

        $output = $PAGE->get_renderer('mod_lesson');
        return $output->render_from_template($this->get_template_name(), $data);
    }

    /**
     * Render the continue/feedback page using the scene appearance template.
     *
     * @param \lesson $lesson The lesson instance.
     * @param \lesson_page $page The current page.
     * @param \stdClass $result The result from process_page_responses.
     * @param bool $reviewmode Whether the user is in review mode.
     * @return string The rendered HTML output.
     */
    public function render_continue(
        \lesson $lesson,
        \lesson_page $page,
        \stdClass $result,
        bool $reviewmode
    ): string {
        global $PAGE, $DB, $OUTPUT, $USER;

        $cm = $lesson->cm;

        $data = [
            'pagetype' => $page->get_typestring(),
        ];

        // Feedback content.
        if (!$reviewmode) {
            $context = $lesson->context;
            $data['feedback'] = format_text($result->feedback, FORMAT_MOODLE, ['context' => $context, 'noclean' => true]);
        }

        // Determine correct/incorrect for good/bad images.
        $showfeedbackimage = !$result->noanswer && !$result->isessayquestion;
        $data['iscorrect'] = $showfeedbackimage && !empty($result->correctanswer);
        $data['isincorrect'] = $showfeedbackimage && empty($result->correctanswer);

        // Ongoing score.
        $lessonoutput = $PAGE->get_renderer('mod_lesson');
        $data['ongoing_score'] = ($lesson->ongoing && !$reviewmode) ? $lessonoutput->ongoing_score($lesson) : '';

        // Build navigation buttons.
        $buttons = '';

        // Modattempts block.
        if (isset($USER->modattempts[$lesson->id])) {
            $buttons .= $OUTPUT->box(get_string('gotoendoflesson', 'lesson'), 'center');
            $buttons .= $OUTPUT->box(get_string('or', 'lesson'), 'center');
            $buttons .= $OUTPUT->box(get_string('continuetonextpage', 'lesson'), 'center');
            $finishurl = new \moodle_url('/mod/lesson/view.php', ['id' => $cm->id, 'pageid' => LESSON_EOL]);
            $buttons .= $OUTPUT->single_button($finishurl, get_string('finish', 'lesson'));
        }

        // Review button back.
        $canreview = !$result->correctanswer && !$result->noanswer && !$result->isessayquestion
                     && !$reviewmode && $lesson->review && !$result->maxattemptsreached;
        if ($canreview) {
            $reviewurl = new \moodle_url('/mod/lesson/view.php', ['id' => $cm->id, 'pageid' => $page->id]);
            $buttons .= $OUTPUT->single_button($reviewurl, get_string('reviewquestionback', 'lesson'));
        }

        // Continue / review continue button.
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

        $data['navigation_buttons'] = $buttons;

        // Load design record for images and colors.
        $design = $DB->get_record('lesson_appearance_designs', ['uniqueid' => $lesson->appearance]);
        if ($design) {
            $syscontext = \context_system::instance();
            $configdata = !empty($design->configdata) ? (json_decode($design->configdata, true) ?: []) : [];

            $fileareas = [
                'appearance_background' => 'background',
                'appearance_character'  => 'character',
                'appearance_flag'       => 'flag',
                'appearance_good'       => 'good',
                'appearance_bad'        => 'bad',
            ];

            foreach ($fileareas as $filearea => $key) {
                $url = $this->get_file_url($syscontext, $filearea, $design->id);
                $data[$key . '_url'] = $url;
                $data['has' . $key] = !empty($url);
            }

            // Only show good/bad if the image exists AND the answer state matches.
            $data['showgood'] = $data['iscorrect'] && !empty($data['hasgood']);
            $data['showbad'] = $data['isincorrect'] && !empty($data['hasbad']);

            foreach (self::COLOR_KEYS as $colorkey) {
                $data[$colorkey] = $configdata[$colorkey] ?? '';
            }
        }

        $output = $PAGE->get_renderer('mod_lesson');
        return $output->render_from_template('lessonappearance_scene/continue', $data);
    }

    /**
     * Get the URL for a stored file in a given file area.
     *
     * @param \context $context The context where files are stored.
     * @param string $filearea The file area name.
     * @param int $itemid The item ID (design ID).
     * @return string The file URL, or empty string if no file exists.
     */
    private function get_file_url(\context $context, string $filearea, int $itemid): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, self::COMPONENT, $filearea, $itemid, 'sortorder', false);
        $file = reset($files);
        if (!$file) {
            return '';
        }
        return \moodle_url::make_pluginfile_url(
            $context->id,
            self::COMPONENT,
            $filearea,
            $itemid,
            $file->get_filepath(),
            $file->get_filename()
        )->out();
    }

    public function get_config_form_elements(\MoodleQuickForm $mform, int $designid = 0): void {
        $component = 'lessonappearance_scene';
        $context = \context_system::instance();
        $fmoptions = $this->get_filemanager_options();

        // Files section.
        $mform->addElement('header', 'scene_files', get_string('files', $component));

        $fileareas = [
            'appearance_background' => 'background',
            'appearance_character'  => 'character',
            'appearance_flag'       => 'flag',
            'appearance_sprite'     => 'sprite',
            'appearance_preview'    => 'preview',
            'appearance_good'       => 'good',
            'appearance_bad'        => 'bad',
        ];

        foreach ($fileareas as $filearea => $langkey) {
            $draftitemid = file_get_submitted_draft_itemid($filearea);
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                self::COMPONENT,
                $filearea,
                $designid,
                $fmoptions
            );
            $mform->addElement('filemanager', $filearea, get_string($langkey, $component), null, $fmoptions);
            $mform->setDefault($filearea, $draftitemid);
        }

        // Colors section.
        $mform->addElement('header', 'scene_colors', get_string('colors', $component));

        $colorfields = [
            'color_primary'   => 'colorprimary',
            'color_secondary' => 'colorsecondary',
            'color_good'      => 'colorgood',
            'color_bad'       => 'colorbad',
            'color_default'   => 'colordefault',
        ];

        // Load existing colors from configdata.
        $configdata = [];
        if ($designid) {
            global $DB;
            $record = $DB->get_field('lesson_appearance_designs', 'configdata', ['id' => $designid]);
            if ($record) {
                $configdata = json_decode($record, true) ?: [];
            }
        }

        foreach ($colorfields as $fieldname => $langkey) {
            $mform->addElement('text', $fieldname, get_string($langkey, $component), ['size' => '10']);
            $mform->setType($fieldname, PARAM_TEXT);
            if (isset($configdata[$fieldname])) {
                $mform->setDefault($fieldname, $configdata[$fieldname]);
            }
        }
    }

    public function save_config_form_data(\stdClass $data, int $designid, \context $context): void {
        global $DB;

        $fmoptions = $this->get_filemanager_options();

        // Save files.
        foreach (self::FILE_AREAS as $filearea) {
            if (isset($data->$filearea)) {
                file_save_draft_area_files(
                    $data->$filearea,
                    $context->id,
                    self::COMPONENT,
                    $filearea,
                    $designid,
                    $fmoptions
                );
            }
        }

        // Save colors into configdata JSON.
        $record = $DB->get_field('lesson_appearance_designs', 'configdata', ['id' => $designid]);
        $configdata = $record ? (json_decode($record, true) ?: []) : [];

        foreach (self::COLOR_KEYS as $key) {
            if (isset($data->$key)) {
                $configdata[$key] = $data->$key;
            }
        }

        $DB->set_field('lesson_appearance_designs', 'configdata', json_encode($configdata), ['id' => $designid]);
    }
}
