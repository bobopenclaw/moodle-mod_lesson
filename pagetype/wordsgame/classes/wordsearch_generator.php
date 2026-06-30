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

namespace lessonpagetype_wordsgame;

/**
 * Word search grid generator.
 *
 * Generates a letter grid with words placed randomly in various directions.
 *
 * @package    lessonpagetype_wordsgame
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wordsearch_generator {

    /** @var int Direction: top to bottom. */
    const TB = 1;

    /** @var int Direction: bottom to top. */
    const BT = 2;

    /** @var int Direction: left to right. */
    const LR = 3;

    /** @var int Direction: right to left. */
    const RL = 4;

    /** @var int Direction: top-bottom left-right (diagonal). */
    const TBLR = 5;

    /** @var int Direction: top-bottom right-left (diagonal). */
    const TBRL = 6;

    /** @var int Direction: bottom-top left-right (diagonal). */
    const BTLR = 7;

    /** @var int Direction: bottom-top right-left (diagonal). */
    const BTRL = 8;

    /** @var array Directions used for word placement. */
    const DIRECTIONS = [self::LR, self::TB, self::TBLR, self::TBRL];

    /** @var int Maximum random placement attempts per word. */
    const MAX_ATTEMPTS = 100;

    /** @var array The list of words to place in the grid (uppercase). */
    private array $words;

    /** @var array The 2D grid array. */
    private array $grid;

    /** @var int The size of the square grid. */
    private int $size;

    /** @var array Information about placed words. */
    private array $placedwords = [];

    /**
     * Constructor.
     *
     * @param array $words Array of plain text words to place in the grid.
     */
    public function __construct(array $words) {
        $this->words = array_map(function ($word) {
            return \core_text::strtoupper(trim($word));
        }, $words);
    }

    /**
     * Generate the word search grid.
     *
     * @return array With keys 'grid' (string) and 'words' (array of placement objects).
     */
    public function generate(): array {
        $this->size = $this->calculate_grid_size();

        // Initialise empty grid.
        $this->grid = array_fill(0, $this->size, array_fill(0, $this->size, ''));

        // Sort words longest first.
        $sortedwords = $this->words;
        usort($sortedwords, function ($a, $b) {
            return \core_text::strlen($b) - \core_text::strlen($a);
        });

        // Place each word.
        foreach ($sortedwords as $word) {
            if (\core_text::strlen($word) === 0) {
                continue;
            }
            $this->place_word($word);
        }

        // Fill remaining empty cells with random letters.
        $this->fill_empty_cells();

        // Build grid string: columns separated by commas, rows by pipes.
        $rows = [];
        foreach ($this->grid as $row) {
            $rows[] = implode(',', $row);
        }
        $gridstring = implode('|', $rows);

        return [
            'grid' => $gridstring,
            'words' => $this->placedwords,
        ];
    }

    /**
     * Calculate the size of the square grid.
     *
     * The size is the maximum of (longest word length + 2) and ceil(sqrt(total letters * 3)).
     *
     * @return int The grid size.
     */
    private function calculate_grid_size(): int {
        $maxlen = 0;
        $totalletters = 0;

        foreach ($this->words as $word) {
            $len = \core_text::strlen($word);
            if ($len > $maxlen) {
                $maxlen = $len;
            }
            $totalletters += $len;
        }

        return max($maxlen + 2, (int) ceil(sqrt($totalletters * 3)));
    }

    /**
     * Try to place a word in the grid.
     *
     * First attempts random positions and directions. If that fails, tries all positions systematically.
     *
     * @param string $word The word to place (uppercase).
     * @return array|null Placement info or null if the word could not be placed.
     */
    private function place_word(string $word): ?array {
        $directions = self::DIRECTIONS;

        // Try random placement.
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $direction = $directions[array_rand($directions)];
            $row = random_int(0, $this->size - 1);
            $col = random_int(0, $this->size - 1);

            if ($this->can_place($word, $row, $col, $direction)) {
                $this->do_place($word, $row, $col, $direction);
                $info = (object) [
                    'term' => $word,
                    'direction' => $direction,
                    'row' => $row,
                    'col' => $col,
                ];
                $this->placedwords[] = $info;
                return (array) $info;
            }
        }

        // Systematic placement: try every position and direction.
        foreach ($directions as $direction) {
            for ($row = 0; $row < $this->size; $row++) {
                for ($col = 0; $col < $this->size; $col++) {
                    if ($this->can_place($word, $row, $col, $direction)) {
                        $this->do_place($word, $row, $col, $direction);
                        $info = (object) [
                            'term' => $word,
                            'direction' => $direction,
                            'row' => $row,
                            'col' => $col,
                        ];
                        $this->placedwords[] = $info;
                        return (array) $info;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check whether a word can be placed at the given position and direction.
     *
     * Verifies bounds and that each cell is either empty or contains the matching letter.
     *
     * @param string $word The word to check.
     * @param int $row Starting row.
     * @param int $col Starting column.
     * @param int $direction The placement direction.
     * @return bool True if the word can be placed.
     */
    private function can_place(string $word, int $row, int $col, int $direction): bool {
        [$drow, $dcol] = $this->get_delta($direction);
        $len = \core_text::strlen($word);

        // Check that the word fits within the grid.
        $endrow = $row + $drow * ($len - 1);
        $endcol = $col + $dcol * ($len - 1);

        if ($endrow < 0 || $endrow >= $this->size || $endcol < 0 || $endcol >= $this->size) {
            return false;
        }

        // Check each cell.
        $r = $row;
        $c = $col;
        for ($i = 0; $i < $len; $i++) {
            $letter = \core_text::substr($word, $i, 1);
            if ($this->grid[$r][$c] !== '' && $this->grid[$r][$c] !== $letter) {
                return false;
            }
            $r += $drow;
            $c += $dcol;
        }

        return true;
    }

    /**
     * Place a word into the grid at the given position and direction.
     *
     * @param string $word The word to place.
     * @param int $row Starting row.
     * @param int $col Starting column.
     * @param int $direction The placement direction.
     */
    private function do_place(string $word, int $row, int $col, int $direction): void {
        [$drow, $dcol] = $this->get_delta($direction);
        $len = \core_text::strlen($word);

        $r = $row;
        $c = $col;
        for ($i = 0; $i < $len; $i++) {
            $this->grid[$r][$c] = \core_text::substr($word, $i, 1);
            $r += $drow;
            $c += $dcol;
        }
    }

    /**
     * Fill all empty cells in the grid with random uppercase letters.
     */
    private function fill_empty_cells(): void {
        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if ($this->grid[$row][$col] === '') {
                    $this->grid[$row][$col] = chr(random_int(65, 90));
                }
            }
        }
    }

    /**
     * Get the row and column deltas for a given direction.
     *
     * @param int $direction The direction constant.
     * @return array Array of [drow, dcol].
     */
    private function get_delta(int $direction): array {
        switch ($direction) {
            case self::TB:
                return [1, 0];
            case self::BT:
                return [-1, 0];
            case self::LR:
                return [0, 1];
            case self::RL:
                return [0, -1];
            case self::TBLR:
                return [1, 1];
            case self::TBRL:
                return [1, -1];
            case self::BTLR:
                return [-1, 1];
            case self::BTRL:
                return [-1, -1];
            default:
                return [0, 1];
        }
    }
}
