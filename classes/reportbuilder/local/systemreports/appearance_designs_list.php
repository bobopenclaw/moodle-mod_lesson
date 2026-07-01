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

declare(strict_types=1);

namespace mod_lesson\reportbuilder\local\systemreports;

use core\context\system;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;

/**
 * Appearance designs list system report
 *
 * @package    mod_lesson
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appearance_designs_list extends system_report {

    /**
     * Report initialisation
     */
    protected function initialise(): void {
        $entity = new \mod_lesson\reportbuilder\local\entities\appearance_design();
        $this->add_entity($entity);

        $alias = $entity->get_table_alias('lesson_appearance_designs');
        $this->set_main_table('lesson_appearance_designs', $alias);

        // Base fields required for actions.
        $this->add_base_fields("{$alias}.id, {$alias}.uniqueid, {$alias}.name, {$alias}.type");

        // Columns.
        $this->add_column_from_entity('appearance_design:name');
        $this->add_column_from_entity('appearance_design:uniqueid');
        $this->add_column_from_entity('appearance_design:type');
        $this->add_column_from_entity('appearance_design:timemodified');

        $this->set_initial_sort_column('appearance_design:name', SORT_ASC);

        $this->set_downloadable(false);

        // Filters.
        $this->add_filters_from_entities([
            'appearance_design:name',
            'appearance_design:type',
        ]);

        $this->add_actions();
    }

    /**
     * Report access
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('mod/lesson:manageappearancedesigns', system::instance());
    }

    /**
     * Report actions
     */
    protected function add_actions(): void {

        // Edit.
        $this->add_action(new action(
            new moodle_url('/mod/lesson/appearance/edit.php', [
                'id' => ':id',
            ]),
            new pix_icon('t/edit', ''),
            [],
            false,
            new lang_string('edit'),
        ));

        // Configure.
        $this->add_action(new action(
            new moodle_url('/mod/lesson/appearance/configure.php', [
                'id' => ':id',
            ]),
            new pix_icon('t/preferences', ''),
            [],
            false,
            new lang_string('configuration'),
        ));

        // Delete.
        $this->add_action(new action(
            new moodle_url('/mod/lesson/appearance/manage.php', [
                'action' => 'delete',
                'id' => ':id',
                'sesskey' => sesskey(),
            ]),
            new pix_icon('t/delete', ''),
            [
                'class' => 'text-danger',
            ],
            false,
            new lang_string('delete'),
        ));
    }
}
