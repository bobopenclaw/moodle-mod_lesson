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
 * Words game page type for the lesson module.
 *
 * @package    lessonpagetype_wordsgame
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Words game question type */
define("LESSON_PAGE_WORDSGAME", "11");

class lesson_page_type_wordsgame extends lesson_page {

    protected $type = lesson_page::TYPE_QUESTION;
    protected $typeid = LESSON_PAGE_WORDSGAME;
    protected $typeidstring = 'wordsgame';
    protected $string = null;

    public function get_typeid() {
        return $this->typeid;
    }

    public function get_typestring() {
        if ($this->string === null) {
            $this->string = get_string('pluginname', 'lessonpagetype_wordsgame');
        }
        return $this->string;
    }

    public function get_idstring() {
        return $this->typeidstring;
    }

    public function max_answers($default) {
        return (get_config('lessonpagetype_wordsgame', 'maxwords') ?: 20) + 2;
    }

    public function update_form_data(stdClass $data): stdClass {
        $maxwords = (get_config('lessonpagetype_wordsgame', 'maxwords') ?: 20);

        for ($i = 2; $i < $maxwords + 2; $i++) {
            $answerkey = 'answer_editor[' . $i . ']';
            if (isset($data->$answerkey) && is_array($data->$answerkey)) {
                $data->$answerkey = $data->$answerkey['text'];
            }

            $responsekey = 'response_editor[' . $i . ']';
            if (isset($data->$responsekey) && is_array($data->$responsekey)) {
                $data->$responsekey = $data->$responsekey['text'];
            }
        }

        return $data;
    }

    public function display($renderer, $attempt) {
        global $USER, $CFG, $PAGE;

        $allanswers = $this->get_answers();

        // Skip first 2 answers (control answers), remaining are words.
        $wordanswers = array_slice($allanswers, 2);

        $words = [];
        foreach ($wordanswers as $wa) {
            $word = trim($wa->answer);
            if ($word !== '') {
                $words[] = [
                    'word' => $word,
                    'clue' => trim($wa->response),
                ];
            }
        }

        // Randomly pick game type.
        $gametype = random_int(0, 1) ? 'crossword' : 'wordsearch';

        if ($gametype === 'wordsearch') {
            $generator = new \lessonpagetype_wordsgame\wordsearch_generator(array_column($words, 'word'));
            $gamedata = $generator->generate();
            // Merge clue data back into the placed words.
            $cluebyword = [];
            foreach ($words as $w) {
                $cluebyword[\core_text::strtoupper(trim($w['word']))] = $w['clue'];
            }
            foreach ($gamedata['words'] as &$pw) {
                $upper = \core_text::strtoupper($pw->term);
                $pw->clue = isset($cluebyword[$upper]) ? $cluebyword[$upper] : $pw->term;
            }
            unset($pw);
        } else {
            $generator = new \lessonpagetype_wordsgame\crossword_generator($words);
            $gamedata = $generator->generate();
        }

        $action = $CFG->wwwroot . '/mod/lesson/continue.php';
        $params = [
            'contents' => $this->get_contents(),
            'lessonid' => $this->lesson->id,
            'gametype' => $gametype,
            'gamedata' => $gamedata,
        ];
        $mform = new lesson_display_answer_form_wordsgame($action, $params);

        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
        $mform->set_data($data);

        // Trigger an event question viewed.
        $eventparams = [
            'context' => context_module::instance($PAGE->cm->id),
            'objectid' => $this->properties->id,
            'other' => [
                'pagetype' => $this->get_typestring(),
            ],
        ];
        $event = \mod_lesson\event\question_viewed::create($eventparams);
        $event->trigger();

        return $mform->display();
    }

    public function check_answer() {
        global $CFG, $PAGE;

        $formattextdefoptions = new stdClass();
        $formattextdefoptions->noclean = true;
        $formattextdefoptions->para = false;

        $result = parent::check_answer();

        $action = $CFG->wwwroot . '/mod/lesson/continue.php';
        $params = [
            'contents' => $this->get_contents(),
            'lessonid' => $this->lesson->id,
        ];
        $mform = new lesson_display_answer_form_wordsgame($action, $params);

        $data = $mform->get_data();
        require_sesskey();

        if (!$data) {
            $result->inmediatejump = true;
            $result->newpageid = $this->properties->id;
            return $result;
        }

        $foundwords = !empty($data->wordsfound) ? json_decode($data->wordsfound, true) : [];

        if (empty($foundwords)) {
            $result->noanswer = true;
            return $result;
        }

        $allanswers = $this->get_answers();
        $correct = array_shift($allanswers);
        $wrong = array_shift($allanswers);

        // Build expected words array from remaining answers.
        $expectedwords = [];
        foreach ($allanswers as $answer) {
            $word = trim($answer->answer);
            if ($word !== '') {
                $expectedwords[] = core_text::strtoupper($word);
            }
        }

        // Normalise submitted words to uppercase.
        $foundwords = array_map(function ($w) {
            return core_text::strtoupper(trim($w));
        }, $foundwords);

        // Count hits.
        $hits = 0;
        foreach ($expectedwords as $expected) {
            if (in_array($expected, $foundwords)) {
                $hits++;
            }
        }

        if ($hits == count($expectedwords)) {
            $result->correctanswer = true;
            $result->response = format_text($correct->answer, $correct->answerformat, $formattextdefoptions);
            $result->answerid = $correct->id;
            $result->newpageid = $correct->jumpto;
        } else {
            $result->correctanswer = false;
            $result->response = format_text($wrong->answer, $wrong->answerformat, $formattextdefoptions);
            $result->answerid = $wrong->id;
            $result->newpageid = $wrong->jumpto;
        }

        $result->userresponse = json_encode($foundwords);
        $result->studentanswer = implode(', ', $foundwords);

        return $result;
    }

    public function create_answers($properties) {
        global $DB, $PAGE;

        $newanswer = new stdClass;
        $newanswer->lessonid = $this->lesson->id;
        $newanswer->pageid = $this->properties->id;
        $newanswer->timecreated = $this->properties->timecreated;

        $cm = get_coursemodule_from_instance('lesson', $this->lesson->id, $this->lesson->course);
        $context = context_module::instance($cm->id);

        // Check for duplicate response format.
        $duplicateresponse = [];
        if (is_array($properties->response_editor) &&
                is_array(reset($properties->response_editor))) {
            foreach ($properties->response_editor as $response) {
                $duplicateresponse[] = $response['text'];
            }
            $properties->response_editor = $duplicateresponse;
        }

        $answers = [];
        $maxwords = (get_config('lessonpagetype_wordsgame', 'maxwords') ?: 20);
        $total = $maxwords + 2;

        for ($i = 0; $i < $total; $i++) {
            $answer = clone($newanswer);

            if ($i < 2) {
                // Control answers (correct/wrong feedback).
                if (!empty($properties->answer_editor[$i]) && is_array($properties->answer_editor[$i])) {
                    $answer->answer = $properties->answer_editor[$i]['text'];
                    $answer->answerformat = $properties->answer_editor[$i]['format'];
                }
                if (!empty($properties->response_editor[$i])) {
                    $answer->response = $properties->response_editor[$i];
                    $answer->responseformat = 0;
                }
                if (isset($properties->jumpto[$i])) {
                    $answer->jumpto = $properties->jumpto[$i];
                }
                if ($this->lesson->custom && isset($properties->score[$i])) {
                    $answer->score = $properties->score[$i];
                }

                $answer->id = $DB->insert_record("lesson_answers", $answer);
                $this->save_answers_files($context, $PAGE->course->maxbytes,
                        $answer, $properties->answer_editor[$i]);
                $answers[$answer->id] = new lesson_page_answer($answer);
            } else {
                // Word answers.
                if (!empty($properties->answer_editor[$i])) {
                    if (is_array($properties->answer_editor[$i])) {
                        $answer->answer = $properties->answer_editor[$i]['text'];
                        $answer->answerformat = FORMAT_PLAIN;
                    } else {
                        $answer->answer = $properties->answer_editor[$i];
                        $answer->answerformat = FORMAT_PLAIN;
                    }
                }
                if (!empty($properties->response_editor[$i])) {
                    $answer->response = $properties->response_editor[$i];
                    $answer->responseformat = 0;
                }

                if (isset($answer->answer) && $answer->answer != '') {
                    $answer->id = $DB->insert_record("lesson_answers", $answer);
                    $answers[$answer->id] = new lesson_page_answer($answer);
                } else {
                    break;
                }
            }
        }
        $this->answers = $answers;
        return $answers;
    }

    public function update($properties, $context = null, $maxbytes = null) {
        global $DB, $PAGE;

        $answers = $this->get_answers();
        $properties->id = $this->properties->id;
        $properties->lessonid = $this->lesson->id;
        $properties->timemodified = time();
        $properties = file_postupdate_standard_editor($properties, 'contents',
                array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $PAGE->course->maxbytes),
                context_module::instance($PAGE->cm->id), 'mod_lesson', 'page_contents', $properties->id);
        $DB->update_record("lesson_pages", $properties);

        // Trigger an event: page updated.
        \mod_lesson\event\page_updated::create_from_lesson_page($this, $context)->trigger();

        // Check for duplicate response format.
        $duplicateresponse = [];
        if (is_array($properties->response_editor) &&
                is_array(reset($properties->response_editor))) {
            foreach ($properties->response_editor as $response) {
                $duplicateresponse[] = $response['text'];
            }
            $properties->response_editor = $duplicateresponse;
        }

        $maxwords = (get_config('lessonpagetype_wordsgame', 'maxwords') ?: 20);
        $total = $maxwords + 2;

        for ($i = 0; $i < $total; $i++) {
            if (!array_key_exists($i, $this->answers)) {
                $this->answers[$i] = new stdClass;
                $this->answers[$i]->lessonid = $this->lesson->id;
                $this->answers[$i]->pageid = $this->id;
                $this->answers[$i]->timecreated = $this->timecreated;
            }

            if ($i < 2) {
                // Control answers.
                if (!empty($properties->answer_editor[$i]) && is_array($properties->answer_editor[$i])) {
                    $this->answers[$i]->answer = $properties->answer_editor[$i]['text'];
                    $this->answers[$i]->answerformat = $properties->answer_editor[$i]['format'];
                }
                if (!empty($properties->response_editor[$i])) {
                    $this->answers[$i]->response = $properties->response_editor[$i];
                    $this->answers[$i]->responseformat = 0;
                }
                if (isset($properties->jumpto[$i])) {
                    $this->answers[$i]->jumpto = $properties->jumpto[$i];
                }
                if ($this->lesson->custom && isset($properties->score[$i])) {
                    $this->answers[$i]->score = $properties->score[$i];
                }

                if (!isset($this->answers[$i]->id)) {
                    $this->answers[$i]->id = $DB->insert_record("lesson_answers", $this->answers[$i]);
                } else {
                    $DB->update_record("lesson_answers", $this->answers[$i]->properties());
                }
                $this->save_answers_files($context, $maxbytes, $this->answers[$i], $properties->answer_editor[$i]);
            } else {
                // Word answers.
                if (!empty($properties->answer_editor[$i])) {
                    if (is_array($properties->answer_editor[$i])) {
                        $this->answers[$i]->answer = $properties->answer_editor[$i]['text'];
                        $this->answers[$i]->answerformat = FORMAT_PLAIN;
                    } else {
                        $this->answers[$i]->answer = $properties->answer_editor[$i];
                        $this->answers[$i]->answerformat = FORMAT_PLAIN;
                    }
                }
                if (!empty($properties->response_editor[$i])) {
                    $this->answers[$i]->response = $properties->response_editor[$i];
                    $this->answers[$i]->responseformat = 0;
                }

                if ($this->answers[$i]->answer != '') {
                    if (!isset($this->answers[$i]->id)) {
                        $this->answers[$i]->id = $DB->insert_record("lesson_answers", $this->answers[$i]);
                    } else {
                        $DB->update_record("lesson_answers", $this->answers[$i]->properties());
                    }
                } else if (isset($this->answers[$i]->id)) {
                    $DB->delete_records('lesson_answers', array('id' => $this->answers[$i]->id));
                    unset($this->answers[$i]);
                }
            }
        }
        return true;
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass;
        $options->noclean = true;
        $options->para = false;
        $i = 1;
        $n = 0;

        foreach ($answers as $answer) {
            $answer = parent::rewrite_answers_urls($answer);
            if ($n < 2) {
                if ($answer->answer != null) {
                    $cells = [];
                    if ($n == 0) {
                        $cells[] = '<label>' . get_string('correctresponse', 'lessonpagetype_wordsgame') . '</label>';
                    } else {
                        $cells[] = '<label>' . get_string('wrongresponse', 'lessonpagetype_wordsgame') . '</label>';
                    }
                    $cells[] = format_text($answer->answer, $answer->answerformat, $options);
                    $table->data[] = new html_table_row($cells);
                }

                if ($n == 0) {
                    $cells = [];
                    $cells[] = '<label>' . get_string('correctanswerscore', 'lessonpagetype_wordsgame') . '</label>: ';
                    $cells[] = $answer->score;
                    $table->data[] = new html_table_row($cells);

                    $cells = [];
                    $cells[] = '<label>' . get_string('correctanswerjump', 'lessonpagetype_wordsgame') . '</label>: ';
                    $cells[] = $this->get_jump_name($answer->jumpto);
                    $table->data[] = new html_table_row($cells);
                } elseif ($n == 1) {
                    $cells = [];
                    $cells[] = '<label>' . get_string('wronganswerscore', 'lessonpagetype_wordsgame') . '</label>: ';
                    $cells[] = $answer->score;
                    $table->data[] = new html_table_row($cells);

                    $cells = [];
                    $cells[] = '<label>' . get_string('wronganswerjump', 'lessonpagetype_wordsgame') . '</label>: ';
                    $cells[] = $this->get_jump_name($answer->jumpto);
                    $table->data[] = new html_table_row($cells);
                }

                if ($n === 0) {
                    $table->data[count($table->data) - 1]->cells[0]->style = 'width:20%;';
                }
                $n++;
                $i--;
            } else {
                $cells = [];
                $cells[] = '<label>' . get_string('word', 'lessonpagetype_wordsgame') . " {$i}</label>: \n";
                $cells[] = format_text($answer->answer, $answer->answerformat, $options);
                $table->data[] = new html_table_row($cells);

                $cells = [];
                $cells[] = '<label>' . get_string('clue', 'lessonpagetype_wordsgame') . " {$i}</label>: \n";
                $cells[] = format_text($answer->response, $answer->responseformat, $options);
                $table->data[] = new html_table_row($cells);
            }
            $i++;
        }
        return $table;
    }

    public function stats(array &$pagestats, $tries) {
        $temp = $this->lesson->get_last_attempt($tries);
        if ($temp->correct) {
            if (isset($pagestats[$temp->pageid]["correct"])) {
                $pagestats[$temp->pageid]["correct"]++;
            } else {
                $pagestats[$temp->pageid]["correct"] = 1;
            }
        }
        if (isset($pagestats[$temp->pageid]["total"])) {
            $pagestats[$temp->pageid]["total"]++;
        } else {
            $pagestats[$temp->pageid]["total"] = 1;
        }
        return true;
    }

    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = [];
        foreach ($this->get_answers() as $answer) {
            $answers[$answer->id] = $answer;
        }
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;

        foreach ($answers as $answer) {
            if ($n == 0 && $useranswer != null && $useranswer->correct) {
                if ($answer->response == null && $useranswer != null) {
                    $answerdata->response = get_string("thatsthecorrectanswer", "lesson");
                } else {
                    $answerdata->response = $answer->response;
                }
                if ($this->lesson->custom) {
                    $answerdata->score = get_string("pointsearned", "lesson") . ": " . $answer->score;
                } else {
                    $answerdata->score = get_string("receivedcredit", "lesson");
                }
            } elseif ($n == 1 && $useranswer != null && !$useranswer->correct) {
                if ($answer->response == null && $useranswer != null) {
                    $answerdata->response = get_string("thatsthewronganswer", "lesson");
                } else {
                    $answerdata->response = $answer->response;
                }
                if ($this->lesson->custom) {
                    $answerdata->score = get_string("pointsearned", "lesson") . ": " . $answer->score;
                } else {
                    $answerdata->score = get_string("didnotreceivecredit", "lesson");
                }
            } elseif ($n > 1) {
                $data = '<label class="accesshide" for="answer_' . $n . '">' . get_string('word', 'lessonpagetype_wordsgame') . '</label>';
                $data .= strip_tags(format_string($answer->answer)) . ' ';
                if ($useranswer != null) {
                    $userwords = json_decode($useranswer->useranswer, true);
                    if (is_array($userwords)) {
                        $data .= implode(', ', $userwords);
                    }
                }

                if ($n == 2) {
                    if (isset($pagestats[$this->properties->id])) {
                        if (!array_key_exists('correct', $pagestats[$this->properties->id])) {
                            $pagestats[$this->properties->id]["correct"] = 0;
                        }
                        $percent = $pagestats[$this->properties->id]["correct"] / $pagestats[$this->properties->id]["total"] * 100;
                        $percent = round($percent, 2);
                        $percent .= "% " . get_string("answeredcorrectly", "lesson");
                    } else {
                        $percent = get_string("nooneansweredthisquestion", "lesson");
                    }
                } else {
                    $percent = '';
                }

                $answerdata->answers[] = array($data, $percent);
                $i++;
            }
            $n++;
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }

    public function get_jumps() {
        global $DB;
        $jumps = [];
        if ($answers = $DB->get_records("lesson_answers",
                array("lessonid" => $this->lesson->id, "pageid" => $this->properties->id), 'id', '*', 0, 2)) {
            foreach ($answers as $answer) {
                $jumps[] = $this->get_jump_name($answer->jumpto);
            }
        } else {
            $jumps[] = $this->get_jump_name($this->properties->nextpageid);
        }
        return $jumps;
    }
}

class lesson_add_page_form_wordsgame extends lesson_add_page_form_base {

    public $qtype = 'wordsgame';
    public $qtypestring = 'wordsgame';
    protected $answerformat = LESSON_ANSWER_HTML;
    protected $responseformat = '';

    public function custom_definition() {

        $this->_form->addElement('header', 'correctresponse', get_string('correctresponse', 'lessonpagetype_wordsgame'));
        $this->_form->addElement('editor', 'answer_editor[0]', get_string('correctresponse', 'lessonpagetype_wordsgame'),
                array('rows' => '4', 'columns' => '80'),
                array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $this->_customdata['maxbytes']));
        $this->_form->setType('answer_editor[0]', PARAM_RAW);
        $this->_form->setDefault('answer_editor[0]', array('text' => '', 'format' => FORMAT_HTML));
        $this->add_jumpto(0, get_string('correctanswerjump', 'lessonpagetype_wordsgame'), LESSON_NEXTPAGE);
        $this->add_score(0, get_string('correctanswerscore', 'lessonpagetype_wordsgame'), 1);

        $this->_form->addElement('header', 'wrongresponse', get_string('wrongresponse', 'lessonpagetype_wordsgame'));
        $this->_form->addElement('editor', 'answer_editor[1]', get_string('wrongresponse', 'lessonpagetype_wordsgame'),
                array('rows' => '4', 'columns' => '80'),
                array('noclean' => true, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $this->_customdata['maxbytes']));
        $this->_form->setType('answer_editor[1]', PARAM_RAW);
        $this->_form->setDefault('answer_editor[1]', array('text' => '', 'format' => FORMAT_HTML));
        $this->add_jumpto(1, get_string('wronganswerjump', 'lessonpagetype_wordsgame'), LESSON_THISPAGE);
        $this->add_score(1, get_string('wronganswerscore', 'lessonpagetype_wordsgame'), 0);

        $maxwords = get_config('lessonpagetype_wordsgame', 'maxwords') ?: 20;
        for ($i = 2; $i < $maxwords + 2; $i++) {
            $this->_form->addElement('header', 'wordpair' . ($i - 1), get_string('wordpair', 'lessonpagetype_wordsgame', $i - 1));
            $this->_form->addElement('text', 'answer_editor[' . $i . ']', get_string('word', 'lessonpagetype_wordsgame'),
                    array('size' => '50'));
            $this->_form->setType('answer_editor[' . $i . ']', PARAM_TEXT);
            $this->_form->addElement('text', 'response_editor[' . $i . ']', get_string('clue', 'lessonpagetype_wordsgame'),
                    array('size' => '50'));
            $this->_form->setType('response_editor[' . $i . ']', PARAM_TEXT);

            if ($i < 4) {
                $this->_form->addRule('answer_editor[' . $i . ']', get_string('required'), 'required', null, 'client');
            }
        }
    }
}

class lesson_display_answer_form_wordsgame extends moodleform {

    public function definition() {
        global $USER, $OUTPUT, $PAGE;

        $mform = $this->_form;
        $contents = $this->_customdata['contents'];
        $lessonid = $this->_customdata['lessonid'];
        $gametype = isset($this->_customdata['gametype']) ? $this->_customdata['gametype'] : '';
        $gamedata = isset($this->_customdata['gamedata']) ? $this->_customdata['gamedata'] : [];

        // Disable shortforms.
        $mform->setDisableShortforms();

        $mform->addElement('header', 'pageheader');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        if ($gametype !== '') {
            $mform->addElement('html', '<div id="wordsgame-container" data-gametype="' . $gametype .
                    '" data-gamedata="' . s(json_encode($gamedata)) . '"></div>');
        }

        $mform->addElement('hidden', 'wordsfound', '');
        $mform->setType('wordsfound', PARAM_RAW);

        $hasattempt = false;
        if (isset($USER->modattempts[$lessonid])) {
            $hasattempt = true;
        }

        if ($hasattempt) {
            $this->add_action_buttons(null, get_string("nextpage", "lesson"));
        } else {
            $this->add_action_buttons(null, get_string("submit", "lesson"));
        }

        $PAGE->requires->js_call_amd('lessonpagetype_wordsgame/wordsgame', 'init', ['wordsgame-container']);
    }
}
