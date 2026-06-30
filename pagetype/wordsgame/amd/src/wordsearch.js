/**
 * Word Search game module.
 *
 * Renders an interactive word search grid where users find hidden words
 * by clicking and dragging across letter cells, or by clicking a clue
 * then clicking start and end cells on the grid (two-click mode).
 *
 * @module     lessonpagetype_wordsgame/wordsearch
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /** @type {string[]} Colour classes for found words, cycled mod 8. */
    var COLORS = [
        'wordsgame-ws-color-0',
        'wordsgame-ws-color-1',
        'wordsgame-ws-color-2',
        'wordsgame-ws-color-3',
        'wordsgame-ws-color-4',
        'wordsgame-ws-color-5',
        'wordsgame-ws-color-6',
        'wordsgame-ws-color-7'
    ];

    /**
     * Parse the grid string into a 2-D array of letters.
     *
     * @param {string} gridStr Rows separated by "|", cells by ",".
     * @return {string[][]}
     */
    function parseGrid(gridStr) {
        var rows = gridStr.split('|');
        var grid = [];
        var r, cells, c, row;
        for (r = 0; r < rows.length; r++) {
            cells = rows[r].split(',');
            row = [];
            for (c = 0; c < cells.length; c++) {
                row.push(cells[c]);
            }
            grid.push(row);
        }
        return grid;
    }

    /**
     * Return the sign of a number (-1, 0 or 1).
     *
     * @param {number} n
     * @return {number}
     */
    function sign(n) {
        if (n > 0) {
            return 1;
        }
        if (n < 0) {
            return -1;
        }
        return 0;
    }

    /**
     * Determine whether two cells form a valid straight-line selection
     * (horizontal, vertical, or 45 degree diagonal).
     *
     * @param {number} r1 Start row.
     * @param {number} c1 Start column.
     * @param {number} r2 End row.
     * @param {number} c2 End column.
     * @return {boolean}
     */
    function isValidLine(r1, c1, r2, c2) {
        var dr = Math.abs(r2 - r1);
        var dc = Math.abs(c2 - c1);
        return dr === 0 || dc === 0 || dr === dc;
    }

    /**
     * Return all (row, col) pairs along the straight line from (r1,c1) to (r2,c2).
     *
     * @param {number} r1
     * @param {number} c1
     * @param {number} r2
     * @param {number} c2
     * @return {Array.<{row: number, col: number}>}
     */
    function getCellsInLine(r1, c1, r2, c2) {
        var cells = [];
        var dr = sign(r2 - r1);
        var dc = sign(c2 - c1);
        var steps = Math.max(Math.abs(r2 - r1), Math.abs(c2 - c1));
        var i, r, c;
        for (i = 0; i <= steps; i++) {
            r = r1 + i * dr;
            c = c1 + i * dc;
            cells.push({row: r, col: c});
        }
        return cells;
    }

    /**
     * Get the cell element at a given row and column.
     *
     * @param {HTMLElement} board The board container.
     * @param {number} row
     * @param {number} col
     * @return {HTMLElement|null}
     */
    function getCellElement(board, row, col) {
        return board.querySelector(
            '.wordsgame-ws-cell[data-row="' + row + '"][data-col="' + col + '"]'
        );
    }

    /**
     * Extract the row/col from a cell element.
     *
     * @param {HTMLElement} el
     * @return {{row: number, col: number}|null}
     */
    function cellPosition(el) {
        if (!el || !el.classList || !el.classList.contains('wordsgame-ws-cell')) {
            return null;
        }
        return {
            row: parseInt(el.getAttribute('data-row'), 10),
            col: parseInt(el.getAttribute('data-col'), 10)
        };
    }

    return {
        /**
         * Initialise the word search game inside the given container.
         *
         * @param {HTMLElement} container The wrapper element.
         * @param {Object}      gamedata  Parsed game data.
         * @param {string}      gamedata.grid  Grid string (rows "|", cols ",").
         * @param {Object[]}    gamedata.words Words to find.
         * @param {Function}    onComplete Called with found-words array when done.
         */
        init: function(container, gamedata, onComplete) {
            var grid = parseGrid(gamedata.grid);
            var words = gamedata.words;
            var totalRows = grid.length;
            var totalCols = totalRows > 0 ? grid[0].length : 0;

            // Track found state.
            var foundFlags = [];
            var foundWords = [];
            var foundCount = 0;
            var colorIndex = 0;
            var i;
            for (i = 0; i < words.length; i++) {
                foundFlags.push(false);
            }

            // Drag-selection state.
            var dragging = false;
            var startRow = 0;
            var startCol = 0;
            var currentEndRow = 0;
            var currentEndCol = 0;

            // Two-click (clue-initiated) selection state.
            var activeWordIndex = -1;
            var clickPhase = 0; // 0 = idle, 1 = waiting for start click, 2 = waiting for end click.
            var clickStartRow = 0;
            var clickStartCol = 0;

            // Build DOM.
            var wrapper = document.createElement('div');
            wrapper.className = 'wordsgame-wordsearch';

            // Board.
            var board = document.createElement('div');
            board.className = 'wordsgame-ws-board';

            var r, c, lineDiv, btn;
            for (r = 0; r < totalRows; r++) {
                lineDiv = document.createElement('div');
                lineDiv.className = 'wordsgame-ws-line';
                for (c = 0; c < totalCols; c++) {
                    btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'wordsgame-ws-cell';
                    btn.setAttribute('data-row', r);
                    btn.setAttribute('data-col', c);
                    btn.textContent = grid[r][c];
                    lineDiv.appendChild(btn);
                }
                board.appendChild(lineDiv);
            }

            // Word list (shows clues, not the actual terms).
            var wordListDiv = document.createElement('div');
            wordListDiv.className = 'wordsgame-ws-wordlist';
            var ul = document.createElement('ul');
            for (i = 0; i < words.length; i++) {
                var li = document.createElement('li');
                li.setAttribute('data-word-index', i);
                li.textContent = words[i].clue || words[i].term;
                ul.appendChild(li);
            }
            wordListDiv.appendChild(ul);

            wrapper.appendChild(board);
            wrapper.appendChild(wordListDiv);
            container.appendChild(wrapper);

            // ----- Helpers -----

            /**
             * Clear all selecting highlights from cells.
             */
            function clearSelecting() {
                var cells = board.querySelectorAll('.wordsgame-ws-cell-selecting');
                var j;
                for (j = 0; j < cells.length; j++) {
                    cells[j].classList.remove('wordsgame-ws-cell-selecting');
                }
            }

            /**
             * Clear the active clue highlight from the word list.
             */
            function clearActiveClue() {
                var items = wordListDiv.querySelectorAll('.wordsgame-ws-word-active');
                var j;
                for (j = 0; j < items.length; j++) {
                    items[j].classList.remove('wordsgame-ws-word-active');
                }
            }

            /**
             * Reset the two-click selection state.
             */
            function resetClickMode() {
                activeWordIndex = -1;
                clickPhase = 0;
                clearActiveClue();
                clearSelecting();
            }

            /**
             * Highlight cells along a line.
             *
             * @param {number} r1
             * @param {number} c1
             * @param {number} r2
             * @param {number} c2
             */
            function highlightLine(r1, c1, r2, c2) {
                var lineCells = getCellsInLine(r1, c1, r2, c2);
                var j, el;
                for (j = 0; j < lineCells.length; j++) {
                    el = getCellElement(board, lineCells[j].row, lineCells[j].col);
                    if (el) {
                        el.classList.add('wordsgame-ws-cell-selecting');
                    }
                }
            }

            /**
             * Build the string from selected cells.
             *
             * @param {number} r1
             * @param {number} c1
             * @param {number} r2
             * @param {number} c2
             * @return {string}
             */
            function extractWord(r1, c1, r2, c2) {
                var lineCells = getCellsInLine(r1, c1, r2, c2);
                var word = '';
                var j;
                for (j = 0; j < lineCells.length; j++) {
                    word += grid[lineCells[j].row][lineCells[j].col];
                }
                return word;
            }

            /**
             * Reverse a string.
             *
             * @param {string} s
             * @return {string}
             */
            function reverseStr(s) {
                return s.split('').reverse().join('');
            }

            /**
             * Check if the selected word matches any unfound word.
             *
             * @param {string} selected
             * @return {number} Index into words array, or -1.
             */
            function matchWord(selected) {
                var upper = selected.toUpperCase();
                var rev = reverseStr(upper);
                var j;
                for (j = 0; j < words.length; j++) {
                    if (foundFlags[j]) {
                        continue;
                    }
                    var term = words[j].term.toUpperCase();
                    if (upper === term || rev === term) {
                        return j;
                    }
                }
                return -1;
            }

            /**
             * Check if the selected word matches a specific word index.
             *
             * @param {string} selected
             * @param {number} wordIdx
             * @return {boolean}
             */
            function matchSpecificWord(selected, wordIdx) {
                var upper = selected.toUpperCase();
                var term = words[wordIdx].term.toUpperCase();
                var rev = reverseStr(upper);
                return upper === term || rev === term;
            }

            /**
             * Mark a word as found: persistent colour on grid, strike in list.
             *
             * @param {number} wordIdx
             * @param {number} r1
             * @param {number} c1
             * @param {number} r2
             * @param {number} c2
             */
            function markFound(wordIdx, r1, c1, r2, c2) {
                foundFlags[wordIdx] = true;
                foundWords.push(words[wordIdx].term);
                foundCount++;

                var cls = COLORS[colorIndex % COLORS.length];
                colorIndex++;

                var lineCells = getCellsInLine(r1, c1, r2, c2);
                var j, el;
                for (j = 0; j < lineCells.length; j++) {
                    el = getCellElement(board, lineCells[j].row, lineCells[j].col);
                    if (el) {
                        el.classList.add('wordsgame-ws-cell-found');
                        el.classList.add(cls);
                    }
                }

                var liEl = wordListDiv.querySelector('li[data-word-index="' + wordIdx + '"]');
                if (liEl) {
                    liEl.classList.add('wordsgame-ws-word-found');
                    liEl.classList.remove('wordsgame-ws-word-active');
                }

                if (foundCount === words.length) {
                    onComplete(foundWords);
                }
            }

            /**
             * Try to finalise a selection (from either drag or two-click mode).
             *
             * @param {number} r1
             * @param {number} c1
             * @param {number} r2
             * @param {number} c2
             */
            function trySelection(r1, c1, r2, c2) {
                var selected = extractWord(r1, c1, r2, c2);
                var idx;

                if (activeWordIndex !== -1) {
                    // Two-click mode: must match the specific active word.
                    if (matchSpecificWord(selected, activeWordIndex)) {
                        markFound(activeWordIndex, r1, c1, r2, c2);
                    }
                } else {
                    // Drag mode: match any unfound word.
                    idx = matchWord(selected);
                    if (idx !== -1) {
                        markFound(idx, r1, c1, r2, c2);
                    }
                }
                clearSelecting();
            }

            // ----- Drag event handlers -----

            /**
             * Begin drag selection.
             *
             * @param {number} row
             * @param {number} col
             */
            function startDragSelection(row, col) {
                dragging = true;
                startRow = row;
                startCol = col;
                currentEndRow = row;
                currentEndCol = col;
                clearSelecting();
                var el = getCellElement(board, row, col);
                if (el) {
                    el.classList.add('wordsgame-ws-cell-selecting');
                }
            }

            /**
             * Continue drag selection to a new cell.
             *
             * @param {number} row
             * @param {number} col
             */
            function moveDragSelection(row, col) {
                if (!dragging) {
                    return;
                }
                if (!isValidLine(startRow, startCol, row, col)) {
                    return;
                }
                currentEndRow = row;
                currentEndCol = col;
                clearSelecting();
                highlightLine(startRow, startCol, currentEndRow, currentEndCol);
            }

            /**
             * End drag selection and check for match.
             */
            function endDragSelection() {
                if (!dragging) {
                    return;
                }
                dragging = false;

                trySelection(startRow, startCol, currentEndRow, currentEndCol);
            }

            // ----- Two-click (clue-initiated) handlers -----

            /**
             * Handle a click on a grid cell during two-click mode.
             *
             * @param {number} row
             * @param {number} col
             */
            function handleClickMode(row, col) {
                if (clickPhase === 1) {
                    // First click: set the start cell.
                    clickStartRow = row;
                    clickStartCol = col;
                    clickPhase = 2;
                    clearSelecting();
                    var el = getCellElement(board, row, col);
                    if (el) {
                        el.classList.add('wordsgame-ws-cell-selecting');
                    }
                } else if (clickPhase === 2) {
                    // Second click: set the end cell and try selection.
                    if (!isValidLine(clickStartRow, clickStartCol, row, col)) {
                        return;
                    }
                    clearSelecting();
                    highlightLine(clickStartRow, clickStartCol, row, col);
                    trySelection(clickStartRow, clickStartCol, row, col);
                    resetClickMode();
                }
            }

            // ----- Word list click handler (initTerm equivalent) -----

            wordListDiv.addEventListener('click', function(e) {
                var li = e.target;
                while (li && li.tagName !== 'LI') {
                    li = li.parentNode;
                    if (li === wordListDiv) {
                        li = null;
                    }
                }
                if (!li) {
                    return;
                }

                var idx = parseInt(li.getAttribute('data-word-index'), 10);
                if (isNaN(idx) || foundFlags[idx]) {
                    return;
                }

                // If already active on this word, deactivate.
                if (activeWordIndex === idx) {
                    resetClickMode();
                    return;
                }

                // Activate this word for two-click selection.
                clearSelecting();
                clearActiveClue();
                activeWordIndex = idx;
                clickPhase = 1;
                li.classList.add('wordsgame-ws-word-active');
            });

            // ----- Mouse events (delegated to board) -----

            board.addEventListener('mousedown', function(e) {
                var pos = cellPosition(e.target);
                if (!pos) {
                    return;
                }
                e.preventDefault();

                if (clickPhase > 0) {
                    // Two-click mode active.
                    handleClickMode(pos.row, pos.col);
                    return;
                }

                // Start drag.
                startDragSelection(pos.row, pos.col);
            });

            board.addEventListener('mouseover', function(e) {
                if (clickPhase > 0) {
                    // In two-click mode, show preview line from start to hovered cell.
                    if (clickPhase === 2) {
                        var pos = cellPosition(e.target);
                        if (pos && isValidLine(clickStartRow, clickStartCol, pos.row, pos.col)) {
                            clearSelecting();
                            highlightLine(clickStartRow, clickStartCol, pos.row, pos.col);
                        }
                    }
                    return;
                }
                var pos2 = cellPosition(e.target);
                if (pos2) {
                    moveDragSelection(pos2.row, pos2.col);
                }
            });

            document.addEventListener('mouseup', function() {
                if (clickPhase > 0) {
                    return;
                }
                endDragSelection();
            });

            // ----- Touch events -----

            board.addEventListener('touchstart', function(e) {
                var touch = e.touches[0];
                var target = document.elementFromPoint(touch.clientX, touch.clientY);
                var pos = cellPosition(target);
                if (!pos) {
                    return;
                }
                e.preventDefault();

                if (clickPhase > 0) {
                    handleClickMode(pos.row, pos.col);
                    return;
                }

                startDragSelection(pos.row, pos.col);
            });

            board.addEventListener('touchmove', function(e) {
                var touch = e.touches[0];
                var target = document.elementFromPoint(touch.clientX, touch.clientY);
                var pos = cellPosition(target);
                if (!pos) {
                    return;
                }
                e.preventDefault();

                if (clickPhase === 2) {
                    if (isValidLine(clickStartRow, clickStartCol, pos.row, pos.col)) {
                        clearSelecting();
                        highlightLine(clickStartRow, clickStartCol, pos.row, pos.col);
                    }
                    return;
                }

                moveDragSelection(pos.row, pos.col);
            });

            board.addEventListener('touchend', function(e) {
                e.preventDefault();
                if (clickPhase > 0) {
                    return;
                }
                endDragSelection();
            });
        }
    };
});
