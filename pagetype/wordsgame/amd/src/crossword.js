/**
 * Crossword game module.
 *
 * Renders an interactive crossword puzzle where users fill in letters
 * using keyboard input with automatic navigation.
 *
 * @module     lessonpagetype_wordsgame/crossword
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /** @type {string} Direction constant for horizontal words. */
    var DIR_HORIZONTAL = 'horizontal';

    /** @type {string} Direction constant for vertical words. */
    var DIR_VERTICAL = 'vertical';

    /**
     * Build the sparse grid data structure from word definitions.
     *
     * Each cell tracks its expected letter, the input element, and which
     * word indices it belongs to (horizontal and/or vertical).
     *
     * @param {Object[]} words Array of word data objects.
     * @return {Object} cells[row][col] objects.
     */
    function buildCellMap(words) {
        var cells = {};
        var w, word, dir, startX, startY, len, i, r, c, letter;

        for (w = 0; w < words.length; w++) {
            word = words[w];
            dir = word.direction === 3 ? DIR_HORIZONTAL : DIR_VERTICAL;
            len = word.term.length;

            // word.x/word.y is the first letter position.
            startX = word.x;
            startY = word.y;

            for (i = 0; i < len; i++) {
                if (dir === DIR_HORIZONTAL) {
                    r = startY;
                    c = startX + i;
                } else {
                    r = startY + i;
                    c = startX;
                }

                letter = word.term.charAt(i).toUpperCase();

                if (!cells[r]) {
                    cells[r] = {};
                }
                if (!cells[r][c]) {
                    cells[r][c] = {
                        letter: letter,
                        input: null,
                        words: {},
                        isLabel: false,
                        label: null
                    };
                }
                cells[r][c].words[dir] = w;
            }

            // Label cell goes one position before the first letter.
            if (dir === DIR_HORIZONTAL) {
                r = word.y;
                c = word.x - 1;
            } else {
                r = word.y - 1;
                c = word.x;
            }
            if (!cells[r]) {
                cells[r] = {};
            }
            if (!cells[r][c]) {
                cells[r][c] = {
                    letter: null,
                    input: null,
                    words: {},
                    isLabel: true,
                    label: word.label
                };
            } else {
                cells[r][c].isLabel = true;
                cells[r][c].label = word.label;
            }
        }

        return cells;
    }

    /**
     * Get all input cells for a word, ordered by position.
     *
     * @param {Object} cells The cell map.
     * @param {Object} word  A word data object.
     * @return {Array.<{row: number, col: number, cell: Object}>}
     */
    function getWordCells(cells, word) {
        var dir = word.direction === 3 ? DIR_HORIZONTAL : DIR_VERTICAL;
        var startX, startY, len, list, i, r, c;

        // word.x/word.y is the first letter position.
        startX = word.x;
        startY = word.y;

        len = word.term.length;
        list = [];
        for (i = 0; i < len; i++) {
            if (dir === DIR_HORIZONTAL) {
                r = startY;
                c = startX + i;
            } else {
                r = startY + i;
                c = startX;
            }
            if (cells[r] && cells[r][c]) {
                list.push({row: r, col: c, cell: cells[r][c]});
            }
        }
        return list;
    }

    /**
     * Collect all input elements in the grid, row‑major order.
     *
     * @param {Object} cells
     * @param {number} gridHeight
     * @param {number} gridWidth
     * @return {HTMLInputElement[]}
     */
    function allInputsOrdered(cells, gridHeight, gridWidth) {
        var inputs = [];
        var r, c;
        for (r = 0; r < gridHeight; r++) {
            for (c = 0; c < gridWidth; c++) {
                if (cells[r] && cells[r][c] && cells[r][c].input) {
                    inputs.push(cells[r][c].input);
                }
            }
        }
        return inputs;
    }

    return {
        /**
         * Initialise the crossword game inside the given container.
         *
         * @param {HTMLElement} container  The wrapper element.
         * @param {Object[]}   gamedata   Array of word definitions.
         * @param {Function}   onComplete Called with words array when solved.
         */
        init: function(container, gamedata, onComplete) {
            var words = gamedata;
            if (!words || words.length === 0) {
                return;
            }

            var gridWidth = words[0].gridwidth;
            var gridHeight = words[0].gridheight;
            var cells = buildCellMap(words);

            var currentDirection = DIR_HORIZONTAL;
            var completed = false;

            // Build DOM.
            var wrapper = document.createElement('div');
            wrapper.className = 'wordsgame-crossword';

            var boardDiv = document.createElement('div');
            boardDiv.className = 'wordsgame-cw-board';

            var r, c, rowDiv, cellDiv, inp, labelSpan;
            for (r = 0; r < gridHeight; r++) {
                rowDiv = document.createElement('div');
                rowDiv.className = 'wordsgame-cw-row';
                for (c = 0; c < gridWidth; c++) {
                    cellDiv = document.createElement('div');
                    cellDiv.className = 'wordsgame-cw-cell';
                    cellDiv.setAttribute('data-row', r);
                    cellDiv.setAttribute('data-col', c);

                    if (cells[r] && cells[r][c]) {
                        if (cells[r][c].isLabel && cells[r][c].letter === null) {
                            // Pure label cell (no letter input).
                            cellDiv.classList.add('wordsgame-cw-label');
                            labelSpan = document.createElement('span');
                            labelSpan.className = 'wordsgame-cw-label-number';
                            labelSpan.textContent = cells[r][c].label;
                            cellDiv.appendChild(labelSpan);
                        } else if (cells[r][c].letter !== null) {
                            // Input cell.
                            cellDiv.classList.add('wordsgame-cw-active');

                            if (cells[r][c].isLabel) {
                                labelSpan = document.createElement('span');
                                labelSpan.className = 'wordsgame-cw-label-number';
                                labelSpan.textContent = cells[r][c].label;
                                cellDiv.appendChild(labelSpan);
                            }

                            inp = document.createElement('input');
                            inp.type = 'text';
                            inp.maxLength = 1;
                            inp.setAttribute('data-row', r);
                            inp.setAttribute('data-col', c);
                            inp.setAttribute('autocomplete', 'off');
                            inp.setAttribute('autocapitalize', 'characters');
                            cellDiv.appendChild(inp);
                            cells[r][c].input = inp;
                        }
                    } else {
                        cellDiv.classList.add('wordsgame-cw-empty');
                    }

                    rowDiv.appendChild(cellDiv);
                }
                boardDiv.appendChild(rowDiv);
            }

            // Clues panel.
            var cluesDiv = document.createElement('div');
            cluesDiv.className = 'wordsgame-cw-clues';

            var acrossDiv = document.createElement('div');
            acrossDiv.className = 'wordsgame-cw-clues-across';
            var acrossTitle = document.createElement('h4');
            acrossTitle.textContent = 'Across';
            acrossDiv.appendChild(acrossTitle);

            var downDiv = document.createElement('div');
            downDiv.className = 'wordsgame-cw-clues-down';
            var downTitle = document.createElement('h4');
            downTitle.textContent = 'Down';
            downDiv.appendChild(downTitle);

            var w, word, clueEl, strong;
            for (w = 0; w < words.length; w++) {
                word = words[w];
                clueEl = document.createElement('div');
                clueEl.className = 'wordsgame-cw-clue';
                clueEl.setAttribute('data-word-index', w);

                strong = document.createElement('strong');
                strong.textContent = word.label + '. ';
                clueEl.appendChild(strong);
                clueEl.appendChild(document.createTextNode(word.clue));

                if (word.direction === 3) {
                    acrossDiv.appendChild(clueEl);
                } else {
                    downDiv.appendChild(clueEl);
                }
            }

            cluesDiv.appendChild(acrossDiv);
            cluesDiv.appendChild(downDiv);

            wrapper.appendChild(boardDiv);
            wrapper.appendChild(cluesDiv);
            container.appendChild(wrapper);

            // ----- Helpers -----

            /**
             * Remove highlight classes from all cells.
             */
            function clearHighlights() {
                var highlighted = boardDiv.querySelectorAll('.wordsgame-cw-cell-highlight');
                var j;
                for (j = 0; j < highlighted.length; j++) {
                    highlighted[j].classList.remove('wordsgame-cw-cell-highlight');
                }
                var actives = boardDiv.querySelectorAll('.wordsgame-cw-cell-active-focus');
                for (j = 0; j < actives.length; j++) {
                    actives[j].classList.remove('wordsgame-cw-cell-active-focus');
                }
            }

            /**
             * Highlight the word that the given cell belongs to in the current direction.
             *
             * @param {number} row
             * @param {number} col
             */
            function highlightWord(row, col) {
                clearHighlights();

                var cell = cells[row] && cells[row][col] ? cells[row][col] : null;
                if (!cell) {
                    return;
                }

                // Mark active cell.
                var activeCellDiv = boardDiv.querySelector(
                    '.wordsgame-cw-cell[data-row="' + row + '"][data-col="' + col + '"]'
                );
                if (activeCellDiv) {
                    activeCellDiv.classList.add('wordsgame-cw-cell-active-focus');
                }

                var wordIdx = cell.words[currentDirection];
                if (wordIdx === undefined) {
                    // Fall back to the other direction.
                    var otherDir = currentDirection === DIR_HORIZONTAL ? DIR_VERTICAL : DIR_HORIZONTAL;
                    wordIdx = cell.words[otherDir];
                    if (wordIdx !== undefined) {
                        currentDirection = otherDir;
                    } else {
                        return;
                    }
                }

                var wordCells = getWordCells(cells, words[wordIdx]);
                var j, cellEl;
                for (j = 0; j < wordCells.length; j++) {
                    cellEl = boardDiv.querySelector(
                        '.wordsgame-cw-cell[data-row="' + wordCells[j].row + '"][data-col="' + wordCells[j].col + '"]'
                    );
                    if (cellEl) {
                        cellEl.classList.add('wordsgame-cw-cell-highlight');
                    }
                }
            }

            /**
             * Focus an input at (row, col) if it exists.
             *
             * @param {number} row
             * @param {number} col
             * @return {boolean} Whether a cell was focused.
             */
            function focusCell(row, col) {
                if (cells[row] && cells[row][col] && cells[row][col].input) {
                    cells[row][col].input.focus();
                    highlightWord(row, col);
                    return true;
                }
                return false;
            }

            /**
             * Move to the next cell in the current direction.
             *
             * @param {number} row
             * @param {number} col
             */
            function advanceForward(row, col) {
                var nr = row;
                var nc = col;
                if (currentDirection === DIR_HORIZONTAL) {
                    nc = col + 1;
                } else {
                    nr = row + 1;
                }
                focusCell(nr, nc);
            }

            /**
             * Move to the previous cell in the current direction.
             *
             * @param {number} row
             * @param {number} col
             */
            function advanceBackward(row, col) {
                var nr = row;
                var nc = col;
                if (currentDirection === DIR_HORIZONTAL) {
                    nc = col - 1;
                } else {
                    nr = row - 1;
                }
                focusCell(nr, nc);
            }

            /**
             * Check whether all cells are filled and correct. If so, trigger completion.
             */
            function checkCompletion() {
                if (completed) {
                    return;
                }

                var allFilled = true;
                var allCorrect = true;
                var row, col, cell;

                for (row in cells) {
                    if (!cells.hasOwnProperty(row)) {
                        continue;
                    }
                    for (col in cells[row]) {
                        if (!cells[row].hasOwnProperty(col)) {
                            continue;
                        }
                        cell = cells[row][col];
                        if (cell.letter === null || !cell.input) {
                            continue;
                        }
                        if (!cell.input.value) {
                            allFilled = false;
                            return; // No point checking further.
                        }
                        if (cell.input.value.toUpperCase() !== cell.letter) {
                            allCorrect = false;
                        }
                    }
                }

                if (allFilled && allCorrect) {
                    completed = true;
                    boardDiv.classList.add('wordsgame-cw-correct');

                    var foundTerms = [];
                    var j;
                    for (j = 0; j < words.length; j++) {
                        foundTerms.push(words[j].term);
                    }
                    onComplete(foundTerms);
                }
            }

            // ----- Event bindings -----

            // Click on input: toggle direction if intersection, then highlight.
            boardDiv.addEventListener('click', function(e) {
                var target = e.target;
                if (target.tagName !== 'INPUT') {
                    return;
                }
                var row = parseInt(target.getAttribute('data-row'), 10);
                var col = parseInt(target.getAttribute('data-col'), 10);
                var cell = cells[row] && cells[row][col] ? cells[row][col] : null;
                if (!cell) {
                    return;
                }

                // If cell belongs to both directions, toggle.
                var hasH = cell.words[DIR_HORIZONTAL] !== undefined;
                var hasV = cell.words[DIR_VERTICAL] !== undefined;
                if (hasH && hasV) {
                    // Toggle only if already focused on this cell.
                    if (document.activeElement === target) {
                        currentDirection = currentDirection === DIR_HORIZONTAL ? DIR_VERTICAL : DIR_HORIZONTAL;
                    }
                } else if (hasH) {
                    currentDirection = DIR_HORIZONTAL;
                } else if (hasV) {
                    currentDirection = DIR_VERTICAL;
                }

                highlightWord(row, col);
            });

            // Focus event on inputs.
            boardDiv.addEventListener('focusin', function(e) {
                var target = e.target;
                if (target.tagName !== 'INPUT') {
                    return;
                }
                var row = parseInt(target.getAttribute('data-row'), 10);
                var col = parseInt(target.getAttribute('data-col'), 10);
                highlightWord(row, col);
                target.select();
            });

            // Keyboard events.
            boardDiv.addEventListener('keydown', function(e) {
                var target = e.target;
                if (target.tagName !== 'INPUT') {
                    return;
                }
                var row = parseInt(target.getAttribute('data-row'), 10);
                var col = parseInt(target.getAttribute('data-col'), 10);
                var key = e.key;

                if (key === 'ArrowRight') {
                    e.preventDefault();
                    currentDirection = DIR_HORIZONTAL;
                    focusCell(row, col + 1);
                } else if (key === 'ArrowLeft') {
                    e.preventDefault();
                    currentDirection = DIR_HORIZONTAL;
                    focusCell(row, col - 1);
                } else if (key === 'ArrowDown') {
                    e.preventDefault();
                    currentDirection = DIR_VERTICAL;
                    focusCell(row + 1, col);
                } else if (key === 'ArrowUp') {
                    e.preventDefault();
                    currentDirection = DIR_VERTICAL;
                    focusCell(row - 1, col);
                } else if (key === 'Backspace') {
                    e.preventDefault();
                    target.value = '';
                    advanceBackward(row, col);
                } else if (key === 'Delete') {
                    e.preventDefault();
                    target.value = '';
                } else if (key === 'Tab') {
                    e.preventDefault();
                    var ordered = allInputsOrdered(cells, gridHeight, gridWidth);
                    var idx = ordered.indexOf(target);
                    if (idx !== -1) {
                        if (e.shiftKey) {
                            if (idx > 0) {
                                ordered[idx - 1].focus();
                            }
                        } else {
                            if (idx < ordered.length - 1) {
                                ordered[idx + 1].focus();
                            }
                        }
                    }
                } else if (key.length === 1 && key.match(/[a-zA-ZÀ-ÿ]/)) {
                    e.preventDefault();
                    target.value = key.toUpperCase();
                    advanceForward(row, col);
                    checkCompletion();
                }
            });

            // Clue click: focus first cell of that word.
            cluesDiv.addEventListener('click', function(e) {
                var clueEl = e.target.closest('.wordsgame-cw-clue');
                if (!clueEl) {
                    return;
                }
                var wordIdx = parseInt(clueEl.getAttribute('data-word-index'), 10);
                var word = words[wordIdx];
                currentDirection = word.direction === 3 ? DIR_HORIZONTAL : DIR_VERTICAL;

                var wordCells = getWordCells(cells, word);
                if (wordCells.length > 0) {
                    focusCell(wordCells[0].row, wordCells[0].col);
                }
            });
        }
    };
});
