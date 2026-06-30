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
 * Tests for config_schema.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_lesson\local\config_schema
 */
final class config_schema_test extends \advanced_testcase {
    /**
     * A fully valid card config passes validation.
     */
    public function test_valid_card_config_passes(): void {
        [$valid, $errors] = config_schema::validate('card', [
            'palette' => ['primary' => '#6c5ce7', 'surface' => '#ffffff',
                'text' => '#2d2d44', 'accent' => '#00b894'],
            'answercolumns' => 2,
            'nav' => ['showback' => true, 'showretry' => true, 'shownext' => true],
            'showprogress' => true,
        ]);
        $this->assertTrue($valid, implode(',', $errors));
        $this->assertSame([], $errors);
    }

    /**
     * Unknown top-level keys are rejected.
     */
    public function test_unknown_key_is_rejected(): void {
        [$valid, $errors] = config_schema::validate('card', ['evil' => '<script>']);
        $this->assertFalse($valid);
        $this->assertNotEmpty($errors);
    }

    /**
     * Invalid hex colours are rejected.
     */
    public function test_bad_hex_colour_is_rejected(): void {
        [$valid] = config_schema::validate('card', ['palette' => ['primary' => 'red']]);
        $this->assertFalse($valid);
    }

    /**
     * answercolumns outside the allowed set is rejected.
     */
    public function test_bad_answercolumns_is_rejected(): void {
        [$valid] = config_schema::validate('card', ['answercolumns' => 5]);
        $this->assertFalse($valid);
    }

    /**
     * A non-boolean nav value is rejected.
     */
    public function test_bad_nav_value_is_rejected(): void {
        [$valid] = config_schema::validate('card', ['nav' => ['showback' => 'yes']]);
        $this->assertFalse($valid);
    }

    /**
     * The default skin only accepts an empty config.
     */
    public function test_default_skin_accepts_empty_only(): void {
        $this->assertTrue(config_schema::validate('default', [])[0]);
        $this->assertFalse(config_schema::validate('default', ['x' => 1])[0]);
    }

    /**
     * An unknown base skin is rejected.
     */
    public function test_unknown_baseskin_is_rejected(): void {
        [$valid] = config_schema::validate('nope', []);
        $this->assertFalse($valid);
    }

    /**
     * Defaults are returned for the card skin and empty for default.
     */
    public function test_defaults(): void {
        $this->assertSame([], config_schema::defaults('default'));
        $carddefaults = config_schema::defaults('card');
        $this->assertArrayHasKey('palette', $carddefaults);
        $this->assertSame(2, $carddefaults['answercolumns']);
    }
}
