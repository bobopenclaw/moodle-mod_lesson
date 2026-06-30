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

namespace mod_lesson\local;

/**
 * Tests for template persistent and template_manager.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_lesson\local\template
 * @covers     \mod_lesson\local\template_manager
 */
final class template_manager_test extends \advanced_testcase {
    /**
     * The built-in templates are seeded.
     */
    public function test_builtins_seeded(): void {
        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $this->assertTrue(template::record_exists_select('idnumber = ?', ['default']));
        $this->assertTrue(template::record_exists_select('idnumber = ?', ['monsterwelt']));
    }

    /**
     * Duplicate idnumber is rejected on create.
     */
    public function test_duplicate_idnumber_rejected(): void {
        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $dup = new template(0, (object) ['name' => 'X', 'idnumber' => 'default',
            'baseskin' => 'card', 'config' => '{}']);
        $this->expectException(\core\invalid_persistent_exception::class);
        $dup->create();
    }

    /**
     * An unknown design idnumber falls back to the default template.
     */
    public function test_get_for_lesson_falls_back_to_default(): void {
        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $tpl = template_manager::get_for_lesson((object) ['design' => 'does-not-exist']);
        $this->assertSame('default', $tpl->get('idnumber'));
    }

    /**
     * The selected template is returned.
     */
    public function test_get_for_lesson_returns_selected(): void {
        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $tpl = template_manager::get_for_lesson((object) ['design' => 'monsterwelt']);
        $this->assertSame('card', $tpl->get('baseskin'));
    }

    /**
     * A disabled template falls back to default.
     */
    public function test_get_for_lesson_disabled_falls_back(): void {
        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $tpl = template::get_record(['idnumber' => 'monsterwelt']);
        $tpl->set('enabled', 0);
        $tpl->update();
        $resolved = template_manager::get_for_lesson((object) ['design' => 'monsterwelt']);
        $this->assertSame('default', $resolved->get('idnumber'));
    }

    /**
     * Invalid config is rejected on save.
     */
    public function test_invalid_config_rejected_on_save(): void {
        $this->resetAfterTest();
        $bad = new template(0, (object) ['name' => 'Bad', 'idnumber' => 'bad',
            'baseskin' => 'card', 'config' => json_encode(['evil' => 1])]);
        $this->expectException(\core\invalid_persistent_exception::class);
        $bad->create();
    }

    /**
     * Menu options list only enabled templates.
     */
    public function test_menu_options_lists_enabled(): void {
        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $options = template_manager::menu_options();
        $this->assertArrayHasKey('default', $options);
        $this->assertArrayHasKey('monsterwelt', $options);
    }

    /**
     * Resolved config merges stored values over the skin defaults.
     */
    public function test_resolved_config_merges_defaults(): void {
        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $tpl = template_manager::get_for_lesson((object) ['design' => 'monsterwelt']);
        $config = template_manager::resolved_config($tpl);
        $this->assertSame(2, $config['answercolumns']);
        $this->assertArrayHasKey('primary', $config['palette']);
    }
}
