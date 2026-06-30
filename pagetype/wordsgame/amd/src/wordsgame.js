/**
 * Words Game main entry point.
 *
 * @module     lessonpagetype_wordsgame/wordsgame
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['lessonpagetype_wordsgame/wordsearch', 'lessonpagetype_wordsgame/crossword'], function(WordSearch, Crossword) {
    'use strict';

    return {
        /**
         * Initialise the words game.
         *
         * @param {string} containerId The ID of the game container element.
         */
        init: function(containerId) {
            var container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            var gametype = container.getAttribute('data-gametype');
            var gamedata = JSON.parse(container.getAttribute('data-gamedata'));
            var form = container.closest('form');
            var hiddenInput = form ? form.querySelector('input[name="wordsfound"]') : null;
            var submitBtn = form ? form.querySelector('input[type="submit"]') : null;

            // Disable submit until game is complete.
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            var onComplete = function(foundWords) {
                if (hiddenInput) {
                    hiddenInput.value = JSON.stringify(foundWords);
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            };

            if (gametype === 'wordsearch') {
                WordSearch.init(container, gamedata, onComplete);
            } else if (gametype === 'crossword') {
                Crossword.init(container, gamedata, onComplete);
            }
        }
    };
});
