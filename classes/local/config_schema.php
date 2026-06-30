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
 * Allowlist validation for design template config JSON.
 *
 * Templates are config-only: admins edit a small set of parameters, never raw
 * markup. This class defines the legal parameters per base skin and validates
 * incoming config so the admin CRUD interface cannot be abused.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config_schema {
    /**
     * Known base skins and their human-readable label string keys.
     *
     * @return array<string, string> Map of baseskin => lang string key.
     */
    public static function baseskins(): array {
        return ['default' => 'design_skin_default', 'card' => 'design_skin_card'];
    }

    /**
     * Default config for a base skin.
     *
     * @param string $baseskin The base skin identifier.
     * @return array The default config array (empty for the default skin).
     */
    public static function defaults(string $baseskin): array {
        if ($baseskin === 'card') {
            return [
                'palette' => [
                    'primary' => '#6c5ce7',
                    'surface' => '#ffffff',
                    'text' => '#2d2d44',
                    'accent' => '#00b894',
                ],
                'answercolumns' => 2,
                'nav' => ['showback' => true, 'showretry' => true, 'shownext' => true],
                'showprogress' => true,
            ];
        }
        return [];
    }

    /**
     * Validate a config array against the allowlist for the given base skin.
     *
     * @param string $baseskin The base skin identifier.
     * @param array $config The config array to validate.
     * @return array{0: bool, 1: string[]} A pair of [valid, errors].
     */
    public static function validate(string $baseskin, array $config): array {
        if (!array_key_exists($baseskin, self::baseskins())) {
            return [false, ['unknownbaseskin']];
        }

        if ($baseskin === 'default') {
            $errors = empty($config) ? [] : ['defaultskinnoconfig'];
            return [empty($errors), $errors];
        }

        // Card skin.
        $errors = [];
        $allowed = ['palette', 'answercolumns', 'nav', 'showprogress'];
        foreach (array_keys($config) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'unknownkey:' . $key;
            }
        }

        if (isset($config['palette'])) {
            if (!is_array($config['palette'])) {
                $errors[] = 'badpalette';
            } else {
                $palettekeys = ['primary', 'surface', 'text', 'accent'];
                foreach ($config['palette'] as $pk => $pv) {
                    if (!in_array($pk, $palettekeys, true)) {
                        $errors[] = 'unknownpalettekey:' . $pk;
                    } else if (!is_string($pv) || !preg_match('/^#[0-9a-fA-F]{6}$/', $pv)) {
                        $errors[] = 'badcolour:' . $pk;
                    }
                }
            }
        }

        if (isset($config['answercolumns']) && !in_array($config['answercolumns'], [1, 2], true)) {
            $errors[] = 'badanswercolumns';
        }

        if (isset($config['nav'])) {
            if (!is_array($config['nav'])) {
                $errors[] = 'badnav';
            } else {
                foreach ($config['nav'] as $nk => $nv) {
                    if (!in_array($nk, ['showback', 'showretry', 'shownext'], true)) {
                        $errors[] = 'unknownnavkey:' . $nk;
                    } else if (!is_bool($nv)) {
                        $errors[] = 'badnavvalue:' . $nk;
                    }
                }
            }
        }

        if (isset($config['showprogress']) && !is_bool($config['showprogress'])) {
            $errors[] = 'badshowprogress';
        }

        return [empty($errors), $errors];
    }
}
