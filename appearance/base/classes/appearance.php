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
 * Base appearance class for lesson appearance subplugins.
 *
 * @package    lessonappearance_base
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace lessonappearance_base;

class appearance {
    public function get_name(): string {
        return get_string('pluginname', 'lessonappearance_' . $this->get_component());
    }

    public function get_description(): string {
        return get_string('pluginname_desc', 'lessonappearance_' . $this->get_component());
    }

    public function get_component(): string {
        $class = get_class($this);
        // Extract plugin name from namespace like lessonappearance_scene\appearance.
        $parts = explode('\\', $class);
        return str_replace('lessonappearance_', '', $parts[0]);
    }

    /**
     * Add subplugin-specific form elements for design configuration.
     *
     * @param \MoodleQuickForm $mform
     * @param int $designid
     */
    public function get_config_form_elements(\MoodleQuickForm $mform, int $designid = 0): void {
    }

    /**
     * Save subplugin-specific form data after the design record is saved.
     *
     * @param \stdClass $data Form data.
     * @param int $designid The design record id.
     * @param \context $context The context for file storage.
     */
    public function save_config_form_data(\stdClass $data, int $designid, \context $context): void {
    }

    /**
     * Render the lesson page using this appearance's mustache template.
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
        global $PAGE;

        $data = [
            'content' => $page->get_page_content($renderer),
            'pagetype' => $page->get_typestring(),
            'attributes' => $this->get_page_attributes($lesson, $page),
            'qtypecontent' => $page->get_qtype_content($renderer, $attempt),
            'progress_bar' => $progressbar,
            'navigation_buttons' => $page->get_navigation_buttons($renderer),
        ];

        $output = $PAGE->get_renderer('mod_lesson');
        return $output->render_from_template($this->get_template_name(), $data);
    }

    /**
     * Render the continue/feedback page using this appearance's template.
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
        return '';
    }

    /**
     * Returns the mustache template name for this appearance.
     *
     * @return string Template name in frankenstyle (e.g., lessonappearance_base/main).
     */
    protected function get_template_name(): string {
        return 'lessonappearance_' . $this->get_component() . '/main';
    }

    /**
     * Returns extra HTML attributes for the page container element.
     *
     * Subclasses can override this to add custom attributes based on
     * the design configuration.
     *
     * @param \lesson $lesson The lesson instance.
     * @param \lesson_page $page The current page.
     * @return array Array of ['name' => string, 'value' => string] pairs.
     */
    protected function get_page_attributes(\lesson $lesson, \lesson_page $page): array {
        return [];
    }
}
