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

use core_text;

/**
 * Crossword layout generator for the wordsgame lesson page type.
 *
 * Receives a list of word/clue pairs and computes a crossword grid layout,
 * placing words so they intersect on shared letters when possible.
 *
 * @package    lessonpagetype_wordsgame
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class crossword_generator {

    /** @var int Direction constant for vertical (top-to-bottom). */
    private const DIR_VERTICAL = 1;

    /** @var int Direction constant for horizontal (left-to-right). */
    private const DIR_HORIZONTAL = 3;

    /** @var array The original word/clue pairs. */
    private array $wordclues;

    /** @var array Sparse 2D grid indexed by [row][col] holding single characters. */
    private array $grid = [];

    /** @var array List of placed word records (associative arrays). */
    private array $placed = [];

    /**
     * Constructor.
     *
     * @param array $wordclues Array of associative arrays with keys 'word' (string) and 'clue' (string).
     */
    public function __construct(array $wordclues) {
        $this->wordclues = $wordclues;
    }

    /**
     * Generate the crossword layout.
     *
     * @return array Array of objects with properties: term, clue, x, y, label, direction, gridwidth, gridheight.
     */
    public function generate(): array {
        if (empty($this->wordclues)) {
            return [];
        }

        // Normalise words to uppercase and sort longest first.
        $items = [];
        foreach ($this->wordclues as $wc) {
            $items[] = [
                'word' => core_text::strtoupper($wc['word']),
                'clue' => $wc['clue'],
            ];
        }
        usort($items, function ($a, $b) {
            return core_text::strlen($b['word']) - core_text::strlen($a['word']);
        });

        // Place the first word horizontally at (0, 0).
        $this->do_place($items[0]['word'], $items[0]['clue'], 0, 0, self::DIR_HORIZONTAL);

        // Place remaining words.
        for ($i = 1; $i < count($items); $i++) {
            $word = $items[$i]['word'];
            $clue = $items[$i]['clue'];

            $intersections = $this->find_intersections($word);

            if (!empty($intersections)) {
                // Score each candidate and pick the best.
                $best = null;
                $bestscore = -PHP_INT_MAX;

                foreach ($intersections as $candidate) {
                    $row = $candidate['row'];
                    $col = $candidate['col'];
                    $dir = $candidate['direction'];

                    if (!$this->can_place_at($word, $row, $col, $dir)) {
                        continue;
                    }

                    // Score: count intersections and compactness.
                    $score = $this->score_placement($word, $row, $col, $dir);

                    if ($score > $bestscore) {
                        $bestscore = $score;
                        $best = $candidate;
                    }
                }

                if ($best !== null) {
                    $this->do_place($word, $clue, $best['row'], $best['col'], $best['direction']);
                    continue;
                }
            }

            // No intersection found; place in an adjacent empty area.
            $this->place_adjacent($word, $clue);
        }

        // Normalise so that minimum coordinates are 0, with +1 padding
        // so label cells (placed one position before each word) fit at row/col 0.
        $this->normalize_positions();

        // x/y are the word start positions (where the first letter goes).
        // The JS will place the label cell one position before (x-1 or y-1).
        foreach ($this->placed as &$p) {
            $p['x'] = $p['col'];
            $p['y'] = $p['row'];
        }
        unset($p);

        // Assign labels in reading order (top-to-bottom, left-to-right).
        $this->assign_labels();

        // Compute grid dimensions from letter cells.
        $maxcol = 0;
        $maxrow = 0;
        foreach ($this->grid as $r => $cols) {
            foreach ($cols as $c => $char) {
                if ($c > $maxcol) {
                    $maxcol = $c;
                }
                if ($r > $maxrow) {
                    $maxrow = $r;
                }
            }
        }
        // +1 for 0-based to size. Grid already has +1 padding from normalize_positions.
        $gridwidth = $maxcol + 1;
        $gridheight = $maxrow + 1;

        // Build result objects.
        $result = [];
        foreach ($this->placed as $p) {
            $obj = new \stdClass();
            $obj->term = $p['word'];
            $obj->clue = $p['clue'];
            $obj->x = $p['x'];
            $obj->y = $p['y'];
            $obj->label = $p['label'];
            $obj->direction = $p['direction'];
            $obj->gridwidth = $gridwidth;
            $obj->gridheight = $gridheight;
            $result[] = $obj;
        }

        return $result;
    }

    /**
     * Find possible intersection points between a word and all already-placed words.
     *
     * For each shared letter, computes the candidate row/col where the new word would start
     * if it crosses the placed word at that letter position. The new word is placed in the
     * opposite direction of the placed word.
     *
     * @param string $word The word to find intersections for (uppercase).
     * @return array Array of candidate arrays with keys: row, col, direction.
     */
    private function find_intersections(string $word): array {
        $candidates = [];
        $wordlen = core_text::strlen($word);

        foreach ($this->placed as $existing) {
            $elen = core_text::strlen($existing['word']);

            for ($ei = 0; $ei < $elen; $ei++) {
                $eletter = core_text::substr($existing['word'], $ei, 1);

                for ($wi = 0; $wi < $wordlen; $wi++) {
                    $wletter = core_text::substr($word, $wi, 1);

                    if ($eletter !== $wletter) {
                        continue;
                    }

                    // Compute the intersection cell in the existing word.
                    if ($existing['direction'] === self::DIR_HORIZONTAL) {
                        $irow = $existing['row'];
                        $icol = $existing['col'] + $ei;
                        // New word goes vertical; start row = intersection row - wi.
                        $candidates[] = [
                            'row' => $irow - $wi,
                            'col' => $icol,
                            'direction' => self::DIR_VERTICAL,
                        ];
                    } else {
                        $irow = $existing['row'] + $ei;
                        $icol = $existing['col'];
                        // New word goes horizontal; start col = intersection col - wi.
                        $candidates[] = [
                            'row' => $irow,
                            'col' => $icol - $wi,
                            'direction' => self::DIR_HORIZONTAL,
                        ];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Check whether a word can be placed at the given position and direction without conflicts.
     *
     * Validates that every cell the word occupies is either empty or already contains the same letter.
     * Also checks that the cells immediately before and after the word (along its direction) are empty,
     * and that adjacent parallel cells do not create unintended side-by-side words.
     *
     * @param string $word The word to place (uppercase).
     * @param int $row Starting row.
     * @param int $col Starting column.
     * @param int $direction DIR_HORIZONTAL or DIR_VERTICAL.
     * @return bool True if placement is valid.
     */
    private function can_place_at(string $word, int $row, int $col, int $direction): bool {
        $len = core_text::strlen($word);

        for ($i = 0; $i < $len; $i++) {
            $letter = core_text::substr($word, $i, 1);

            if ($direction === self::DIR_HORIZONTAL) {
                $r = $row;
                $c = $col + $i;
            } else {
                $r = $row + $i;
                $c = $col;
            }

            // Check cell content.
            if (isset($this->grid[$r][$c])) {
                if ($this->grid[$r][$c] !== $letter) {
                    return false;
                }
                // Cell matches (intersection) — allowed.
                continue;
            }

            // Cell is empty; check that adjacent parallel cells are also empty
            // to avoid creating unintended side-by-side words.
            if ($direction === self::DIR_HORIZONTAL) {
                if (isset($this->grid[$r - 1][$c]) || isset($this->grid[$r + 1][$c])) {
                    // Adjacent cell occupied and this is not an intersection — conflict.
                    return false;
                }
            } else {
                if (isset($this->grid[$r][$c - 1]) || isset($this->grid[$r][$c + 1])) {
                    return false;
                }
            }
        }

        // Check cell before the start of the word.
        if ($direction === self::DIR_HORIZONTAL) {
            if (isset($this->grid[$row][$col - 1])) {
                return false;
            }
        } else {
            if (isset($this->grid[$row - 1][$col])) {
                return false;
            }
        }

        // Check cell after the end of the word.
        if ($direction === self::DIR_HORIZONTAL) {
            if (isset($this->grid[$row][$col + $len])) {
                return false;
            }
        } else {
            if (isset($this->grid[$row + $len][$col])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Place a word onto the grid.
     *
     * @param string $word The word to place (uppercase).
     * @param string $clue The clue/definition for the word.
     * @param int $row Starting row.
     * @param int $col Starting column.
     * @param int $direction DIR_HORIZONTAL or DIR_VERTICAL.
     */
    private function do_place(string $word, string $clue, int $row, int $col, int $direction): void {
        $len = core_text::strlen($word);

        for ($i = 0; $i < $len; $i++) {
            $letter = core_text::substr($word, $i, 1);

            if ($direction === self::DIR_HORIZONTAL) {
                $this->grid[$row][$col + $i] = $letter;
            } else {
                $this->grid[$row + $i][$col] = $letter;
            }
        }

        $this->placed[] = [
            'word' => $word,
            'clue' => $clue,
            'row' => $row,
            'col' => $col,
            'direction' => $direction,
            'label' => 0,
        ];
    }

    /**
     * Score a potential placement based on intersections and compactness.
     *
     * @param string $word The word to score (uppercase).
     * @param int $row Starting row.
     * @param int $col Starting column.
     * @param int $direction DIR_HORIZONTAL or DIR_VERTICAL.
     * @return int The placement score (higher is better).
     */
    private function score_placement(string $word, int $row, int $col, int $direction): int {
        $len = core_text::strlen($word);
        $intersections = 0;

        for ($i = 0; $i < $len; $i++) {
            $letter = core_text::substr($word, $i, 1);

            if ($direction === self::DIR_HORIZONTAL) {
                $r = $row;
                $c = $col + $i;
            } else {
                $r = $row + $i;
                $c = $col;
            }

            if (isset($this->grid[$r][$c]) && $this->grid[$r][$c] === $letter) {
                $intersections++;
            }
        }

        // Compute centre of the current grid.
        $centerrow = 0;
        $centercol = 0;
        $cellcount = 0;
        foreach ($this->grid as $r => $cols) {
            foreach ($cols as $c => $char) {
                $centerrow += $r;
                $centercol += $c;
                $cellcount++;
            }
        }

        if ($cellcount > 0) {
            $centerrow = $centerrow / $cellcount;
            $centercol = $centercol / $cellcount;
        }

        // Word midpoint.
        if ($direction === self::DIR_HORIZONTAL) {
            $midrow = $row;
            $midcol = $col + ($len / 2);
        } else {
            $midrow = $row + ($len / 2);
            $midcol = $col;
        }

        $distance = abs($midrow - $centerrow) + abs($midcol - $centercol);

        // Intersections are heavily weighted; closeness to centre is a tiebreaker.
        return ($intersections * 1000) - (int) $distance;
    }

    /**
     * Place a word in an empty area adjacent to the existing grid when no intersection is found.
     *
     * @param string $word The word to place (uppercase).
     * @param string $clue The clue/definition for the word.
     */
    private function place_adjacent(string $word, string $clue): void {
        // Find the bounding box of the current grid.
        $minrow = PHP_INT_MAX;
        $maxrow = -PHP_INT_MAX;

        foreach ($this->grid as $r => $cols) {
            if ($r < $minrow) {
                $minrow = $r;
            }
            if ($r > $maxrow) {
                $maxrow = $r;
            }
        }

        // Place horizontally two rows below the current grid.
        $this->do_place($word, $clue, $maxrow + 2, 0, self::DIR_HORIZONTAL);
    }

    /**
     * Normalize positions so that the minimum row and column in the grid are 0.
     */
    private function normalize_positions(): void {
        if (empty($this->grid)) {
            return;
        }

        $minrow = PHP_INT_MAX;
        $mincol = PHP_INT_MAX;

        foreach ($this->grid as $r => $cols) {
            if ($r < $minrow) {
                $minrow = $r;
            }
            foreach ($cols as $c => $char) {
                if ($c < $mincol) {
                    $mincol = $c;
                }
            }
        }

        // Shift by (min - 1) so that the minimum grid position is 1,
        // leaving row/col 0 available for label cells.
        $minrow -= 1;
        $mincol -= 1;

        if ($minrow === 0 && $mincol === 0) {
            return;
        }

        // Shift the grid.
        $newgrid = [];
        foreach ($this->grid as $r => $cols) {
            foreach ($cols as $c => $char) {
                $newgrid[$r - $minrow][$c - $mincol] = $char;
            }
        }
        $this->grid = $newgrid;

        // Shift placed words.
        foreach ($this->placed as &$p) {
            $p['row'] -= $minrow;
            $p['col'] -= $mincol;
        }
        unset($p);
    }

    /**
     * Assign numeric labels to placed words in reading order (top-to-bottom, left-to-right).
     */
    private function assign_labels(): void {
        // Sort by y then x (using the already-offset values).
        $indices = array_keys($this->placed);
        usort($indices, function ($a, $b) {
            $pa = $this->placed[$a];
            $pb = $this->placed[$b];
            if ($pa['y'] !== $pb['y']) {
                return $pa['y'] - $pb['y'];
            }
            return $pa['x'] - $pb['x'];
        });

        $label = 1;
        foreach ($indices as $idx) {
            $this->placed[$idx]['label'] = $label;
            $label++;
        }
    }
}
