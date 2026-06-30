# Lesson Design Templates Implementation Plan

> **For implementers:** Execute this plan task-by-task. Steps use checkbox
> (`- [ ]`) syntax for tracking. Run code quality checks (`bindev/codechecker.sh`,
> `bindev/moodlecheck.sh`) before finishing.

**Goal:** Add a per-lesson, admin-managed visual "design template" that changes how
lesson pages are rendered to learners, with "Default" reproducing today's look exactly.

**Architecture:** Config-only templates stored in a new `lesson_design_template`
table (managed via an admin CRUD page) selected per lesson via a new `lesson.design`
column. Rendering moves to a renderable/templatable + mustache pipeline at the
`mod_lesson_renderer::display_page()` choke point; `default` keeps the existing
`moodleform` path so the standard look never regresses, while new skins (`card`)
render from vetted mustache files parameterised by a validated JSON config.

**Tech Stack:** Moodle core module `mod_lesson`, PHP 8.2, `\core\persistent`,
mustache, XMLDB, PHPUnit + Behat. Fork policy: core edits marked with `MBS-HACK`.

## Global Constraints

- Moodle Coding Style + PHPDoc on every new class/method/file (verbatim from spec).
- Core APIs first: `$DB`, `\core\persistent`, `moodle_url`, `html_writer`,
  `get_string`, mustache, renderables/templatables. No bespoke reinvention.
- Security: allowlist-validate the `config` JSON; escape all output; capability +
  `sesskey` on every CRUD mutation; validate `design` at render, fall back to
  `default` for unknown/disabled values.
- Config-only templates: admins edit *parameters*, never raw markup.
- Surgical changes: `default` template output must be byte-identical to today.
- Mark every core deviation with `# +++ Core-HACK(<author>): reason … # --- Core-HACK`.
- Quality gates before finishing: `bindev/codechecker.sh <abs plugin path>` and
  `bindev/moodlecheck.sh <abs plugin path>` clean.
- Plugin absolute path (inside repo): `mbsmoodle/public/mod/lesson`.
- Spec: `docs/specs/lesson-design-templates.md`. This plan implements **Phase 1**
  (vertical slice on `multichoice`); Phases 2–3 are outlined at the end.

---

## File Structure (Phase 1)

**Create:**
- `mbsmoodle/public/mod/lesson/classes/local/template.php` — `\core\persistent` for `lesson_design_template`.
- `mbsmoodle/public/mod/lesson/classes/local/config_schema.php` — per-baseskin allowlist validation.
- `mbsmoodle/public/mod/lesson/classes/local/template_manager.php` — resolution, menu options, built-in seeding.
- `mbsmoodle/public/mod/lesson/classes/output/question_page.php` — renderable/templatable for question pages.
- `mbsmoodle/public/mod/lesson/templates/pages/default/question.mustache` — identical-to-today markup (new skins only; see Task 6).
- `mbsmoodle/public/mod/lesson/templates/pages/card/question.mustache` — card look.
- `mbsmoodle/public/mod/lesson/scss/design-card.scss` (loaded via `styles.css` import or `lib.php` SCSS callback — see Task 6).
- `mbsmoodle/public/mod/lesson/templates.php` — admin list page.
- `mbsmoodle/public/mod/lesson/templateedit.php` — admin add/edit page.
- `mbsmoodle/public/mod/lesson/templatedelete.php` — admin delete confirm.
- `mbsmoodle/public/mod/lesson/classes/local/form/template_form.php` — moodleform for add/edit.
- `mbsmoodle/public/mod/lesson/db/install.php` — seed built-ins on fresh install.
- Tests under `mbsmoodle/public/mod/lesson/tests/` and `tests/behat/`.

**Modify (MBS-HACK):**
- `db/install.xml` — `lesson.design` field + new table.
- `db/upgrade.php` — add field, create table, seed built-ins.
- `version.php` — bump.
- `db/access.php` — `mod/lesson:managetemplates`.
- `settings.php` — site default + admin externalpage.
- `mod_form.php` — appearance `design` select.
- `renderer.php` — `display_page()` template switch.
- `lang/en/lesson.php` — new strings.

---

## Task 1: Database schema, version bump, upgrade

**Files:**
- Modify: `mbsmoodle/public/mod/lesson/db/install.xml`
- Modify: `mbsmoodle/public/mod/lesson/db/upgrade.php:97` (after last savepoint, before the v5.2.0 line)
- Modify: `mbsmoodle/public/mod/lesson/version.php:27`
- Create: `mbsmoodle/public/mod/lesson/db/install.php`

**Interfaces:**
- Produces: table `lesson_design_template` (columns: `id, name, idnumber, baseskin,
  config, enabled, sortorder, timecreated, timemodified`), `lesson.design`
  (varchar 100, default `'default'`), and `lesson_install_builtin_templates()`
  free function callable from install.php + upgrade.php.

- [ ] **Step 1: Add the `design` field to the `lesson` table in install.xml**

In `db/install.xml`, inside `<TABLE NAME="lesson"><FIELDS>`, after the
`allowofflineattempts` field, add (wrap the block in MBS-HACK comments is not valid
inside XML — instead add an XML comment marker):

```xml
        <FIELD NAME="allowofflineattempts" TYPE="int" LENGTH="1" NOTNULL="false" DEFAULT="0" SEQUENCE="false" COMMENT="Whether to allow the lesson to be attempted offline in the mobile app"/>
        <!-- MBS-HACK(<author>): design templates feature -->
        <FIELD NAME="design" TYPE="char" LENGTH="100" NOTNULL="true" DEFAULT="default" SEQUENCE="false" COMMENT="idnumber of the selected lesson_design_template"/>
```

- [ ] **Step 2: Add the `lesson_design_template` table in install.xml**

In `db/install.xml`, after the closing `</TABLE>` of the last table (before
`</TABLES>`), add:

```xml
    <!-- MBS-HACK(<author>): design templates feature -->
    <TABLE NAME="lesson_design_template" COMMENT="Admin-managed visual design templates for lessons">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="name" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="idnumber" TYPE="char" LENGTH="100" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="baseskin" TYPE="char" LENGTH="100" NOTNULL="true" DEFAULT="default" SEQUENCE="false"/>
        <FIELD NAME="config" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="enabled" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="1" SEQUENCE="false"/>
        <FIELD NAME="sortorder" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="idnumber" UNIQUE="true" FIELDS="idnumber"/>
      </INDEXES>
    </TABLE>
```

- [ ] **Step 3: Bump the module version**

In `version.php`, change:

```php
$plugin->version   = 2026042000;     // The current module version (Date: YYYYMMDDXX).
```
to:
```php
$plugin->version   = 2026063000;     // The current module version (Date: YYYYMMDDXX).
```

- [ ] **Step 4: Create `db/install.php` to seed built-ins on fresh install**

```php
<?php
// This file is part of Moodle - http://moodle.org/
// ... standard GPL header ...

/**
 * Install-time setup for mod_lesson.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post-install hook: seed the built-in design templates.
 */
function xmldb_lesson_install() {
    lesson_install_builtin_templates();
}
```

- [ ] **Step 5: Add the upgrade step + seeding function in `db/upgrade.php`**

Immediately before the `// Automatically generated Moodle v5.2.0 release upgrade
line.` comment, add:

```php
    // +++ MBS-HACK(<author>): design templates feature.
    if ($oldversion < 2026063000) {
        // Add lesson.design field.
        $table = new xmldb_table('lesson');
        $field = new xmldb_field('design', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'default', 'allowofflineattempts');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Create lesson_design_template table.
        $templatetable = new xmldb_table('lesson_design_template');
        if (!$dbman->table_exists($templatetable)) {
            $templatetable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $templatetable->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $templatetable->add_field('idnumber', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $templatetable->add_field('baseskin', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'default');
            $templatetable->add_field('config', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $templatetable->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $templatetable->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $templatetable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $templatetable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $templatetable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $templatetable->add_index('idnumber', XMLDB_INDEX_UNIQUE, ['idnumber']);
            $dbman->create_table($templatetable);
        }

        // Seed built-in templates.
        lesson_install_builtin_templates();

        upgrade_mod_savepoint(true, 2026063000, 'lesson');
    }
    // --- MBS-HACK
```

- [ ] **Step 6: Add the seeding free function**

This function must be loadable during install/upgrade without autoloading classes.
Add it to `mbsmoodle/public/mod/lesson/lib.php` (end of file), wrapped in MBS-HACK:

```php
// +++ MBS-HACK(<author>): design templates feature.
/**
 * Seed the built-in design templates ('default', 'monsterwelt') idempotently.
 *
 * @return void
 */
function lesson_install_builtin_templates() {
    global $DB;
    $now = time();
    $builtins = [
        (object) [
            'name' => get_string('design_default', 'lesson'),
            'idnumber' => 'default',
            'baseskin' => 'default',
            'config' => '{}',
            'enabled' => 1,
            'sortorder' => 0,
        ],
        (object) [
            'name' => get_string('design_monsterwelt', 'lesson'),
            'idnumber' => 'monsterwelt',
            'baseskin' => 'card',
            'config' => json_encode([
                'palette' => [
                    'primary' => '#6c5ce7',
                    'surface' => '#ffffff',
                    'text' => '#2d2d44',
                    'accent' => '#00b894',
                ],
                'answercolumns' => 2,
                'nav' => ['showback' => true, 'showretry' => true, 'shownext' => true],
                'showprogress' => true,
            ]),
            'enabled' => 1,
            'sortorder' => 1,
        ],
    ];
    foreach ($builtins as $tpl) {
        if (!$DB->record_exists('lesson_design_template', ['idnumber' => $tpl->idnumber])) {
            $tpl->timecreated = $now;
            $tpl->timemodified = $now;
            $DB->insert_record('lesson_design_template', $tpl);
        }
    }
}
// --- MBS-HACK
```

- [ ] **Step 7: Run the upgrade and verify**

Run: `bindev/upgrade.sh`
Expected: completes without error; lesson upgraded to `2026063000`.

- [ ] **Step 8: Commit**

```bash
git add mbsmoodle/public/mod/lesson/db/install.xml \
        mbsmoodle/public/mod/lesson/db/upgrade.php \
        mbsmoodle/public/mod/lesson/db/install.php \
        mbsmoodle/public/mod/lesson/version.php \
        mbsmoodle/public/mod/lesson/lib.php
git commit -m "feat(lesson): add design template schema and built-in seeding"
```

---

## Task 2: Config schema validation

**Files:**
- Create: `mbsmoodle/public/mod/lesson/classes/local/config_schema.php`
- Test: `mbsmoodle/public/mod/lesson/tests/local/config_schema_test.php`

**Interfaces:**
- Produces: `\mod_lesson\local\config_schema::validate(string $baseskin, array $config): array`
  returns `[bool $valid, string[] $errors]`; `::defaults(string $baseskin): array`;
  `::baseskins(): array` returns `['default' => ..., 'card' => ...]`.

- [ ] **Step 1: Write the failing test**

`tests/local/config_schema_test.php`:

```php
<?php
namespace mod_lesson\local;

/**
 * Tests for config_schema.
 *
 * @package    mod_lesson
 * @covers     \mod_lesson\local\config_schema
 */
final class config_schema_test extends \advanced_testcase {
    public function test_valid_card_config_passes(): void {
        [$valid, $errors] = config_schema::validate('card', [
            'palette' => ['primary' => '#6c5ce7', 'surface' => '#ffffff',
                'text' => '#2d2d44', 'accent' => '#00b894'],
            'answercolumns' => 2,
            'nav' => ['showback' => true, 'showretry' => true, 'shownext' => true],
            'showprogress' => true,
        ]);
        $this->assertTrue($valid, implode(',', $errors));
    }

    public function test_unknown_key_is_rejected(): void {
        [$valid, $errors] = config_schema::validate('card', ['evil' => '<script>']);
        $this->assertFalse($valid);
        $this->assertNotEmpty($errors);
    }

    public function test_bad_hex_colour_is_rejected(): void {
        [$valid] = config_schema::validate('card', ['palette' => ['primary' => 'red']]);
        $this->assertFalse($valid);
    }

    public function test_default_skin_accepts_empty_only(): void {
        $this->assertSame([true, []], [config_schema::validate('default', [])[0],
            config_schema::validate('default', [])[1]]);
        $this->assertFalse(config_schema::validate('default', ['x' => 1])[0]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `bindev/phpunit.sh --filter config_schema_test`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement `config_schema`**

`classes/local/config_schema.php`:

```php
<?php
// ... GPL header ...
namespace mod_lesson\local;

/**
 * Allowlist validation for design template config JSON.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config_schema {
    /**
     * Known base skins and their human label string keys.
     *
     * @return array<string, string>
     */
    public static function baseskins(): array {
        return ['default' => 'design_skin_default', 'card' => 'design_skin_card'];
    }

    /**
     * Default config for a base skin.
     *
     * @param string $baseskin
     * @return array
     */
    public static function defaults(string $baseskin): array {
        if ($baseskin === 'card') {
            return [
                'palette' => ['primary' => '#6c5ce7', 'surface' => '#ffffff',
                    'text' => '#2d2d44', 'accent' => '#00b894'],
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
     * @param string $baseskin
     * @param array $config
     * @return array{0: bool, 1: string[]} [valid, errors]
     */
    public static function validate(string $baseskin, array $config): array {
        $errors = [];
        if (!array_key_exists($baseskin, self::baseskins())) {
            return [false, ['unknownbaseskin'];
        }
        if ($baseskin === 'default') {
            if (!empty($config)) {
                $errors[] = 'defaultskinnoconfig';
            }
            return [empty($errors), $errors];
        }
        // Card skin.
        $allowed = ['palette', 'answercolumns', 'nav', 'showprogress'];
        foreach (array_keys($config) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = 'unknownkey:' . $key;
            }
        }
        if (isset($config['palette'])) {
            $palettekeys = ['primary', 'surface', 'text', 'accent'];
            foreach ($config['palette'] as $pk => $pv) {
                if (!in_array($pk, $palettekeys, true)) {
                    $errors[] = 'unknownpalettekey:' . $pk;
                } else if (!is_string($pv) || !preg_match('/^#[0-9a-fA-F]{6}$/', $pv)) {
                    $errors[] = 'badcolour:' . $pk;
                }
            }
        }
        if (isset($config['answercolumns']) && !in_array($config['answercolumns'], [1, 2], true)) {
            $errors[] = 'badanswercolumns';
        }
        if (isset($config['nav'])) {
            foreach ($config['nav'] as $nk => $nv) {
                if (!in_array($nk, ['showback', 'showretry', 'shownext'], true)) {
                    $errors[] = 'unknownnavkey:' . $nk;
                } else if (!is_bool($nv)) {
                    $errors[] = 'badnavvalue:' . $nk;
                }
            }
        }
        if (isset($config['showprogress']) && !is_bool($config['showprogress'])) {
            $errors[] = 'badshowprogress';
        }
        return [empty($errors), $errors];
    }
}
```

> Note: fix the bracket typo `['unknownbaseskin'];` → `['unknownbaseskin']];`
> when typing — it must read `return [false, ['unknownbaseskin']];`.

- [ ] **Step 4: Run test to verify it passes**

Run: `bindev/phpunit.sh --filter config_schema_test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add mbsmoodle/public/mod/lesson/classes/local/config_schema.php \
        mbsmoodle/public/mod/lesson/tests/local/config_schema_test.php
git commit -m "feat(lesson): add design template config schema validation"
```

---

## Task 3: `template` persistent + `template_manager`

**Files:**
- Create: `mbsmoodle/public/mod/lesson/classes/local/template.php`
- Create: `mbsmoodle/public/mod/lesson/classes/local/template_manager.php`
- Test: `mbsmoodle/public/mod/lesson/tests/local/template_manager_test.php`

**Interfaces:**
- Consumes: `config_schema` (Task 2).
- Produces:
  - `\mod_lesson\local\template` (`\core\persistent`, TABLE `lesson_design_template`)
    with property validation that rejects duplicate `idnumber` and invalid `config`.
  - `\mod_lesson\local\template_manager::get_for_lesson(\stdClass $lesson): template`
    (resolves `$lesson->design`, falls back to the `default` template when
    missing/disabled).
  - `template_manager::menu_options(): array<string,string>` (idnumber => name, enabled only).
  - `template_manager::resolved_config(template $tpl): array` (decoded config merged
    over `config_schema::defaults`).

- [ ] **Step 1: Write the failing test**

`tests/local/template_manager_test.php`:

```php
<?php
namespace mod_lesson\local;

/**
 * Tests for template + template_manager.
 *
 * @package    mod_lesson
 * @covers     \mod_lesson\local\template
 * @covers     \mod_lesson\local\template_manager
 */
final class template_manager_test extends \advanced_testcase {
    public function test_builtins_seeded(): void {
        $this->resetAfterTest();
        lesson_install_builtin_templates();
        $this->assertTrue(template::record_exists_select('idnumber = ?', ['default']));
        $this->assertTrue(template::record_exists_select('idnumber = ?', ['monsterwelt']));
    }

    public function test_duplicate_idnumber_rejected(): void {
        $this->resetAfterTest();
        lesson_install_builtin_templates();
        $dup = new template(0, (object) ['name' => 'X', 'idnumber' => 'default',
            'baseskin' => 'card', 'config' => '{}']);
        $this->expectException(\core\invalid_persistent_exception::class);
        $dup->create();
    }

    public function test_get_for_lesson_falls_back_to_default(): void {
        $this->resetAfterTest();
        lesson_install_builtin_templates();
        $tpl = template_manager::get_for_lesson((object) ['design' => 'does-not-exist']);
        $this->assertSame('default', $tpl->get('idnumber'));
    }

    public function test_get_for_lesson_returns_selected(): void {
        $this->resetAfterTest();
        lesson_install_builtin_templates();
        $tpl = template_manager::get_for_lesson((object) ['design' => 'monsterwelt']);
        $this->assertSame('card', $tpl->get('baseskin'));
    }

    public function test_invalid_config_rejected_on_save(): void {
        $this->resetAfterTest();
        $bad = new template(0, (object) ['name' => 'Bad', 'idnumber' => 'bad',
            'baseskin' => 'card', 'config' => json_encode(['evil' => 1])]);
        $this->expectException(\core\invalid_persistent_exception::class);
        $bad->create();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `bindev/phpunit.sh --filter template_manager_test`
Expected: FAIL (classes not found).

- [ ] **Step 3: Implement the `template` persistent**

`classes/local/template.php`:

```php
<?php
// ... GPL header ...
namespace mod_lesson\local;

/**
 * Persistent for an admin-managed lesson design template.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template extends \core\persistent {
    /** @var string Table name. */
    const TABLE = 'lesson_design_template';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'name' => ['type' => PARAM_TEXT],
            'idnumber' => ['type' => PARAM_ALPHANUMEXT],
            'baseskin' => ['type' => PARAM_ALPHANUMEXT, 'default' => 'default'],
            'config' => ['type' => PARAM_RAW, 'default' => '{}'],
            'enabled' => ['type' => PARAM_INT, 'default' => 1],
            'sortorder' => ['type' => PARAM_INT, 'default' => 0],
        ];
    }

    /**
     * Validate idnumber uniqueness.
     *
     * @param string $value
     * @return true|\lang_string
     */
    protected function validate_idnumber($value) {
        $params = ['idnumber' => $value];
        $select = 'idnumber = :idnumber';
        if ($this->get('id')) {
            $select .= ' AND id <> :id';
            $params['id'] = $this->get('id');
        }
        if (self::record_exists_select($select, $params)) {
            return new \lang_string('design_idnumbertaken', 'lesson');
        }
        return true;
    }

    /**
     * Validate config JSON against the base skin allowlist.
     *
     * @param string $value
     * @return true|\lang_string
     */
    protected function validate_config($value) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded) && $value !== '{}' && $value !== '') {
            return new \lang_string('design_configinvalidjson', 'lesson');
        }
        [$valid, $errors] = config_schema::validate($this->get('baseskin'), $decoded ?? []);
        if (!$valid) {
            return new \lang_string('design_configinvalid', 'lesson', implode(', ', $errors));
        }
        return true;
    }
}
```

- [ ] **Step 4: Implement `template_manager`**

`classes/local/template_manager.php`:

```php
<?php
// ... GPL header ...
namespace mod_lesson\local;

/**
 * Resolves and lists lesson design templates.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_manager {
    /**
     * Get the template for a lesson, falling back to 'default'.
     *
     * @param \stdClass $lesson Lesson record (needs ->design).
     * @return template
     */
    public static function get_for_lesson(\stdClass $lesson): template {
        $idnumber = $lesson->design ?? 'default';
        $tpl = template::get_record(['idnumber' => $idnumber, 'enabled' => 1]);
        if (!$tpl) {
            $tpl = template::get_record(['idnumber' => 'default']);
        }
        return $tpl;
    }

    /**
     * Enabled templates as idnumber => name for form menus.
     *
     * @return array<string, string>
     */
    public static function menu_options(): array {
        $options = [];
        foreach (template::get_records(['enabled' => 1], 'sortorder, name') as $tpl) {
            $options[$tpl->get('idnumber')] = format_string($tpl->get('name'));
        }
        return $options;
    }

    /**
     * Decoded config merged over the base skin defaults.
     *
     * @param template $tpl
     * @return array
     */
    public static function resolved_config(template $tpl): array {
        $decoded = json_decode((string) $tpl->get('config'), true) ?: [];
        return array_replace_recursive(config_schema::defaults($tpl->get('baseskin')), $decoded);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `bindev/phpunit.sh --filter template_manager_test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add mbsmoodle/public/mod/lesson/classes/local/template.php \
        mbsmoodle/public/mod/lesson/classes/local/template_manager.php \
        mbsmoodle/public/mod/lesson/tests/local/template_manager_test.php
git commit -m "feat(lesson): add design template persistent and manager"
```

---

## Task 4: Site default setting, mod_form selector, lang strings

**Files:**
- Modify: `mbsmoodle/public/mod/lesson/settings.php` (appearance block)
- Modify: `mbsmoodle/public/mod/lesson/mod_form.php:113` (after `displayleft` block)
- Modify: `mbsmoodle/public/mod/lesson/lang/en/lesson.php`
- Test: `mbsmoodle/public/mod/lesson/tests/behat/design_select.feature`

**Interfaces:**
- Consumes: `template_manager::menu_options()` (Task 3).
- Produces: form element `design` (auto-persisted to `lesson.design` by
  `lesson_add_instance`/`lesson_update_instance`), site default `mod_lesson/design`.

- [ ] **Step 1: Add lang strings**

In `lang/en/lesson.php` (alphabetical-ish, near other `design`/appearance strings):

```php
$string['design'] = 'Design template';
$string['design_help'] = 'Choose the visual design used to display this lesson\'s pages to learners. "Default" keeps the standard Moodle appearance.';
$string['design_default'] = 'Default';
$string['design_monsterwelt'] = 'Monsterwelt';
$string['design_skin_default'] = 'Default (standard Moodle)';
$string['design_skin_card'] = 'Card';
$string['design_idnumbertaken'] = 'This ID number is already used by another design template.';
$string['design_configinvalid'] = 'Invalid template configuration: {$a}';
$string['design_configinvalidjson'] = 'Template configuration is not valid JSON.';
$string['design_templates'] = 'Design templates';
$string['design_managetemplates'] = 'Manage lesson design templates';
$string['design_addtemplate'] = 'Add template';
$string['design_edittemplate'] = 'Edit template';
$string['design_deletetemplate'] = 'Delete template';
$string['design_deleteconfirm'] = 'Are you sure you want to delete the design template "{$a}"?';
$string['design_cannotdeletebuiltin'] = 'The built-in "default" template cannot be deleted.';
$string['design_name'] = 'Name';
$string['design_idnumber'] = 'ID number';
$string['design_baseskin'] = 'Base skin';
$string['design_config'] = 'Configuration (JSON)';
$string['design_enabled'] = 'Enabled';
$string['lesson:managetemplates'] = 'Manage lesson design templates';
```

- [ ] **Step 2: Add the site default in settings.php**

In `settings.php`, after the `displayleftif` setting block, add:

```php
    // +++ MBS-HACK(<author>): design templates feature.
    require_once($CFG->dirroot.'/mod/lesson/classes/local/template_manager.php');
    $designoptions = \mod_lesson\local\template_manager::menu_options();
    if (empty($designoptions)) {
        $designoptions = ['default' => get_string('design_default', 'lesson')];
    }
    $settings->add(new admin_setting_configselect_with_advanced('mod_lesson/design',
        get_string('design', 'lesson'), get_string('design_help', 'lesson'),
        ['value' => 'default', 'adv' => false], $designoptions));
    // --- MBS-HACK
```

- [ ] **Step 3: Add the per-lesson select in mod_form.php**

In `mod_form.php`, after the `displayleft` advanced block (around line 116) and
before the `displayleftif` options loop, add:

```php
        // +++ MBS-HACK(<author>): design templates feature.
        $designoptions = \mod_lesson\local\template_manager::menu_options();
        if (empty($designoptions)) {
            $designoptions = ['default' => get_string('design_default', 'lesson')];
        }
        $mform->addElement('select', 'design', get_string('design', 'lesson'), $designoptions);
        $mform->addHelpButton('design', 'design', 'lesson');
        $mform->setDefault('design', $lessonconfig->design ?? 'default');
        $mform->setType('design', PARAM_ALPHANUMEXT);
        // --- MBS-HACK
```

> `$lessonconfig` is `get_config('mod_lesson')` already loaded at the top of
> `definition()`. The `design` element name matches the `lesson.design` column, so
> `lesson_add_instance`/`lesson_update_instance` persist it automatically.

- [ ] **Step 4: Write the Behat test**

`tests/behat/design_select.feature`:

```gherkin
@mod @mod_lesson
Feature: Choose a lesson design template
  In order to change how a lesson looks
  As a teacher
  I need to select a design template in the lesson settings

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity | name      | course | idnumber |
      | lesson   | Test less | C1     | lesson1  |

  Scenario: The design selector appears and saves
    Given I am on the "Test less" "lesson activity editing" page logged in as admin
    And I expand all fieldsets
    When I set the field "Design template" to "Monsterwelt"
    And I press "Save and display"
    And I am on the "Test less" "lesson activity editing" page
    And I expand all fieldsets
    Then the field "Design template" matches value "Monsterwelt"
```

- [ ] **Step 5: Run the Behat test**

Run: `bindev/behat_init.sh` (once), then
`bindev/behat.sh --tags=@mod_lesson` filtered to this feature.
Expected: scenario passes.

- [ ] **Step 6: Commit**

```bash
git add mbsmoodle/public/mod/lesson/settings.php \
        mbsmoodle/public/mod/lesson/mod_form.php \
        mbsmoodle/public/mod/lesson/lang/en/lesson.php \
        mbsmoodle/public/mod/lesson/tests/behat/design_select.feature
git commit -m "feat(lesson): add design template selector and site default"
```

---

## Task 5: Capability + admin CRUD pages

**Files:**
- Modify: `mbsmoodle/public/mod/lesson/db/access.php` (new capability)
- Modify: `mbsmoodle/public/mod/lesson/settings.php` (admin_externalpage)
- Create: `mbsmoodle/public/mod/lesson/classes/local/form/template_form.php`
- Create: `mbsmoodle/public/mod/lesson/templates.php`
- Create: `mbsmoodle/public/mod/lesson/templateedit.php`
- Create: `mbsmoodle/public/mod/lesson/templatedelete.php`
- Test: `mbsmoodle/public/mod/lesson/tests/behat/manage_templates.feature`

**Interfaces:**
- Consumes: `template` (Task 3), `config_schema::baseskins()` (Task 2).
- Produces: admin page `lessontemplates` (URL `/mod/lesson/templates.php`), capability
  `mod/lesson:managetemplates` (CONTEXT_SYSTEM).

- [ ] **Step 1: Add the capability**

In `db/access.php`, inside the `$capabilities` array (wrap with MBS-HACK):

```php
    // +++ MBS-HACK(<author>): design templates feature.
    'mod/lesson:managetemplates' => array(
        'riskbitmask' => RISK_CONFIG | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        ),
    ),
    // --- MBS-HACK
```

- [ ] **Step 2: Register the admin external page in settings.php**

At the top of `settings.php` (these external pages must be added regardless of
`$ADMIN->fulltree`), before the `if ($ADMIN->fulltree)` block, add:

```php
// +++ MBS-HACK(<author>): design templates feature.
$ADMIN->add('modsettings', new admin_category('lessondesign',
    get_string('design_templates', 'lesson')));
$ADMIN->add('lessondesign', new admin_externalpage('lessontemplates',
    get_string('design_managetemplates', 'lesson'),
    new moodle_url('/mod/lesson/templates.php'),
    'mod/lesson:managetemplates'));
// --- MBS-HACK
```

> If `mod_lesson` already routes its main settings into `modsettings`, keep the
> external page under the same category; verify placement after the page loads.

- [ ] **Step 3: Create the moodleform**

`classes/local/form/template_form.php`:

```php
<?php
// ... GPL header ...
namespace mod_lesson\local\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add/edit form for a lesson design template.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('design_name', 'lesson'), ['size' => 48]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('design_idnumber', 'lesson'), ['size' => 32]);
        $mform->setType('idnumber', PARAM_ALPHANUMEXT);
        $mform->addRule('idnumber', null, 'required', null, 'client');

        $skins = [];
        foreach (\mod_lesson\local\config_schema::baseskins() as $key => $stringkey) {
            $skins[$key] = get_string($stringkey, 'lesson');
        }
        $mform->addElement('select', 'baseskin', get_string('design_baseskin', 'lesson'), $skins);

        $mform->addElement('textarea', 'config', get_string('design_config', 'lesson'),
            ['rows' => 10, 'cols' => 60]);
        $mform->setType('config', PARAM_RAW);
        $mform->setDefault('config', '{}');

        $mform->addElement('advcheckbox', 'enabled', get_string('design_enabled', 'lesson'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Server-side validation of the config JSON against the chosen skin.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $decoded = json_decode((string) $data['config'], true);
        if ($data['config'] !== '' && $decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors['config'] = get_string('design_configinvalidjson', 'lesson');
            return $errors;
        }
        [$valid, $verrors] = \mod_lesson\local\config_schema::validate($data['baseskin'], $decoded ?? []);
        if (!$valid) {
            $errors['config'] = get_string('design_configinvalid', 'lesson', implode(', ', $verrors));
        }
        return $errors;
    }
}
```

- [ ] **Step 4: Create the list page `templates.php`**

```php
<?php
// ... GPL header ...
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('lessontemplates');

$context = context_system::instance();
require_capability('mod/lesson:managetemplates', $context);

$PAGE->set_url(new moodle_url('/mod/lesson/templates.php'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('design_managetemplates', 'lesson'));

echo $OUTPUT->single_button(
    new moodle_url('/mod/lesson/templateedit.php'),
    get_string('design_addtemplate', 'lesson'), 'get');

$templates = \mod_lesson\local\template::get_records([], 'sortorder, name');
$table = new html_table();
$table->head = [
    get_string('design_name', 'lesson'),
    get_string('design_idnumber', 'lesson'),
    get_string('design_baseskin', 'lesson'),
    get_string('design_enabled', 'lesson'),
    get_string('actions'),
];
foreach ($templates as $tpl) {
    $editurl = new moodle_url('/mod/lesson/templateedit.php', ['id' => $tpl->get('id')]);
    $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));
    if ($tpl->get('idnumber') !== 'default') {
        $delurl = new moodle_url('/mod/lesson/templatedelete.php',
            ['id' => $tpl->get('id'), 'sesskey' => sesskey()]);
        $actions .= ' ' . html_writer::link($delurl,
            $OUTPUT->pix_icon('t/delete', get_string('delete')));
    }
    $table->data[] = [
        format_string($tpl->get('name')),
        s($tpl->get('idnumber')),
        s($tpl->get('baseskin')),
        $tpl->get('enabled') ? get_string('yes') : get_string('no'),
        $actions,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
```

- [ ] **Step 5: Create the add/edit page `templateedit.php`**

```php
<?php
// ... GPL header ...
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('lessontemplates');
require_capability('mod/lesson:managetemplates', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);
$pageurl = new moodle_url('/mod/lesson/templateedit.php', $id ? ['id' => $id] : []);
$PAGE->set_url($pageurl);

$template = $id ? new \mod_lesson\local\template($id) : null;

$form = new \mod_lesson\local\form\template_form($pageurl->out(false));
if ($template) {
    $form->set_data((object) [
        'id' => $template->get('id'),
        'name' => $template->get('name'),
        'idnumber' => $template->get('idnumber'),
        'baseskin' => $template->get('baseskin'),
        'config' => $template->get('config'),
        'enabled' => $template->get('enabled'),
    ]);
}

$returnurl = new moodle_url('/mod/lesson/templates.php');
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    if ($template) {
        $template->from_record($data);
        $template->update();
    } else {
        $template = new \mod_lesson\local\template(0, $data);
        $template->create();
    }
    redirect($returnurl, get_string('changessaved'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($id ? 'design_edittemplate' : 'design_addtemplate', 'lesson'));
$form->display();
echo $OUTPUT->footer();
```

- [ ] **Step 6: Create the delete page `templatedelete.php`**

```php
<?php
// ... GPL header ...
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('lessontemplates');
require_capability('mod/lesson:managetemplates', context_system::instance());

$id = required_param('id', PARAM_INT);
require_sesskey();

$template = new \mod_lesson\local\template($id);
$returnurl = new moodle_url('/mod/lesson/templates.php');

if ($template->get('idnumber') === 'default') {
    throw new moodle_exception('design_cannotdeletebuiltin', 'lesson', $returnurl);
}

$confirm = optional_param('confirm', 0, PARAM_BOOL);
if ($confirm) {
    require_sesskey();
    $template->delete();
    redirect($returnurl, get_string('changessaved'));
}

echo $OUTPUT->header();
$confirmurl = new moodle_url('/mod/lesson/templatedelete.php',
    ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->confirm(
    get_string('design_deleteconfirm', 'lesson', format_string($template->get('name'))),
    $confirmurl, $returnurl);
echo $OUTPUT->footer();
```

- [ ] **Step 7: Write the Behat test**

`tests/behat/manage_templates.feature`:

```gherkin
@mod @mod_lesson
Feature: Manage lesson design templates
  As an administrator
  I can create, edit and delete design templates

  Scenario: Create and delete a template
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Design templates > Manage lesson design templates" in site administration
    When I press "Add template"
    And I set the following fields to these values:
      | Name      | My Card      |
      | ID number | mycard       |
      | Base skin | Card         |
      | Configuration (JSON) | {} |
    And I press "Save changes"
    Then I should see "My Card"
    And I should see "mycard"
```

> Adjust the navigation node path to match where the external page actually renders
> (verify after Step 2).

- [ ] **Step 8: Run the Behat test**

Run: `bindev/behat.sh --tags=@mod_lesson` filtered to `manage_templates.feature`.
Expected: scenario passes.

- [ ] **Step 9: Commit**

```bash
git add mbsmoodle/public/mod/lesson/db/access.php \
        mbsmoodle/public/mod/lesson/settings.php \
        mbsmoodle/public/mod/lesson/templates.php \
        mbsmoodle/public/mod/lesson/templateedit.php \
        mbsmoodle/public/mod/lesson/templatedelete.php \
        mbsmoodle/public/mod/lesson/classes/local/form/template_form.php \
        mbsmoodle/public/mod/lesson/tests/behat/manage_templates.feature
git commit -m "feat(lesson): add admin CRUD for design templates"
```

> Note: adding a capability requires a version bump to trigger `update_capabilities`.
> Re-bump `version.php` to `2026063001` in this commit and run `bindev/upgrade.sh`.

---

## Task 6: Render multichoice via mustache for non-default skins

**Files:**
- Create: `mbsmoodle/public/mod/lesson/classes/output/question_page.php`
- Create: `mbsmoodle/public/mod/lesson/templates/pages/card/question.mustache`
- Create: `mbsmoodle/public/mod/lesson/scss/design-card.scss`
- Modify: `mbsmoodle/public/mod/lesson/renderer.php:196` (`display_page`)
- Modify: `mbsmoodle/public/mod/lesson/lib.php` (SCSS include via `styles.css` or callback)
- Test: `mbsmoodle/public/mod/lesson/tests/output/question_page_test.php`
- Test: `mbsmoodle/public/mod/lesson/tests/behat/card_render.feature`

**Interfaces:**
- Consumes: `template_manager` (Task 3), `lesson`, `lesson_page` (multichoice).
- Produces: `\mod_lesson\output\question_page` (`renderable, templatable`) whose
  `export_for_template(renderer_base $output): array` yields
  `{ formaction, sesskey, id, pageid, contents, multiple, answers: [{label, value, inputname, type, checked, disabled}], nav: {showback, showretry, shownext}, showprogress, palette: {...}, answercolumns }`.

- [ ] **Step 1: Write the failing test**

`tests/output/question_page_test.php`:

```php
<?php
namespace mod_lesson\output;

/**
 * Tests for question_page renderable.
 *
 * @package    mod_lesson
 * @covers     \mod_lesson\output\question_page
 */
final class question_page_test extends \advanced_testcase {
    public function test_export_for_template_lists_answers(): void {
        global $PAGE;
        $this->resetAfterTest();
        \lesson_install_builtin_templates();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $lessonrec = $generator->create_module('lesson',
            ['course' => $course->id, 'design' => 'monsterwelt']);
        $lesson = new \lesson($lessonrec);

        // Create a multichoice page with two answers.
        /** @var \mod_lesson_generator $lgen */
        $lgen = $generator->get_plugin_generator('mod_lesson');
        $page = $lgen->create_question_multichoice($lesson, ['answer' => ['A', 'B']]);

        $renderable = new question_page($lesson, $page, null);
        $output = $PAGE->get_renderer('mod_lesson');
        $data = $renderable->export_for_template($output);

        $this->assertCount(2, $data['answers']);
        $this->assertSame(sesskey(), $data['sesskey']);
        $this->assertStringContainsString('continue.php', $data['formaction']);
    }
}
```

> If `mod_lesson_generator` lacks `create_question_multichoice`, use the existing
> generator helper for lesson pages (check `tests/generator/lib.php`) and adapt the
> setup; the assertion targets remain the exported answer count and form fields.

- [ ] **Step 2: Run test to verify it fails**

Run: `bindev/phpunit.sh --filter question_page_test`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement the renderable**

`classes/output/question_page.php`:

```php
<?php
// ... GPL header ...
namespace mod_lesson\output;

use mod_lesson\local\template_manager;

/**
 * Renderable for a lesson question page under a non-default design skin.
 *
 * @package    mod_lesson
 * @copyright  2026 mebis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_page implements \renderable, \templatable {
    /** @var \lesson */
    protected $lesson;
    /** @var \lesson_page */
    protected $page;
    /** @var object|null */
    protected $attempt;

    /**
     * Constructor.
     *
     * @param \lesson $lesson
     * @param \lesson_page $page
     * @param object|null $attempt
     */
    public function __construct(\lesson $lesson, \lesson_page $page, $attempt) {
        $this->lesson = $lesson;
        $this->page = $page;
        $this->attempt = $attempt;
    }

    /**
     * Export data for the mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $CFG, $USER;

        $tpl = template_manager::get_for_lesson($this->lesson->properties());
        $config = template_manager::resolved_config($tpl);

        $answers = $this->page->get_used_answers();
        $multiple = !empty($this->page->properties()->qoption);
        $inputname = $multiple ? 'answer[]' : 'answerid';
        $textoptions = ['para' => false, 'noclean' => true];

        $hasattempt = isset($USER->modattempts[$this->lesson->id])
            && !empty($USER->modattempts[$this->lesson->id]);
        $useransrid = $hasattempt ? ($USER->modattempts[$this->lesson->id]->answerid ?? 0) : 0;

        $answerdata = [];
        foreach ($answers as $answer) {
            $answerdata[] = [
                'label' => format_text($answer->answer, $answer->answerformat, $textoptions),
                'value' => $answer->id,
                'inputname' => $inputname,
                'type' => $multiple ? 'checkbox' : 'radio',
                'checked' => ($answer->id == $useransrid),
                'disabled' => $hasattempt,
            ];
        }

        return [
            'formaction' => $CFG->wwwroot . '/mod/lesson/continue.php',
            'sesskey' => sesskey(),
            'id' => $output->get_page()->cm->id,
            'pageid' => $this->page->properties()->id,
            'contents' => $this->page->get_contents(),
            'multiple' => $multiple,
            'answers' => $answerdata,
            'answercolumns' => (int) ($config['answercolumns'] ?? 2),
            'showprogress' => !empty($config['showprogress']),
            'nav' => [
                'showback' => !empty($config['nav']['showback']),
                'showretry' => !empty($config['nav']['showretry']) && !empty($this->lesson->retake),
                'shownext' => !empty($config['nav']['shownext']),
            ],
            'palette' => $config['palette'] ?? [],
        ];
    }
}
```

> `lesson::properties()` returns the lesson stdClass (has `->design`, `->retake`).
> Confirm the accessor name in `locallib.php`; if it is a magic `__get`, pass
> `$this->lesson->properties` or build a small stdClass with `design` + `retake`.

- [ ] **Step 4: Run the unit test to verify it passes**

Run: `bindev/phpunit.sh --filter question_page_test`
Expected: PASS.

- [ ] **Step 5: Create the card mustache**

`templates/pages/card/question.mustache`:

```mustache
{{!
    Card design for a lesson question page.
    @template mod_lesson/pages/card/question
}}
<div class="lesson-design-card" style="--ld-primary: {{palette.primary}}; --ld-surface: {{palette.surface}}; --ld-text: {{palette.text}}; --ld-accent: {{palette.accent}};">
    <form method="post" action="{{formaction}}" class="lesson-design-card__form">
        <input type="hidden" name="sesskey" value="{{sesskey}}">
        <input type="hidden" name="id" value="{{id}}">
        <input type="hidden" name="pageid" value="{{pageid}}">
        <div class="lesson-design-card__prompt">{{{contents}}}</div>
        <div class="lesson-design-card__answers lesson-design-card__answers--cols{{answercolumns}}">
            {{#answers}}
                <label class="lesson-design-card__answer">
                    <input type="{{type}}" name="{{inputname}}" value="{{value}}"
                        {{#checked}}checked{{/checked}} {{#disabled}}disabled{{/disabled}}>
                    <span class="lesson-design-card__answer-label">{{{label}}}</span>
                </label>
            {{/answers}}
        </div>
        <div class="lesson-design-card__nav">
            {{#nav.showback}}<button type="submit" name="back" value="1" class="btn btn-secondary">{{#str}}back{{/str}}</button>{{/nav.showback}}
            {{#nav.showretry}}<button type="submit" name="retry" value="1" class="btn btn-secondary">{{#str}}reviewlesson, lesson{{/str}}</button>{{/nav.showretry}}
            {{#nav.shownext}}<button type="submit" class="btn btn-primary">{{#str}}nextpage, lesson{{/str}}</button>{{/nav.shownext}}
        </div>
    </form>
</div>
```

> The `back`/`retry` submit names must map to `continue.php` handling. In Phase 1
> render only `shownext` as a real submit if `back`/`retry` aren't yet wired in
> `continue.php`; wiring lesson navigation semantics (R1) is finalised in Phase 3.
> Keep `showback`/`showretry` config-gated so they can be hidden until wired.

- [ ] **Step 6: Add the SCSS and load it**

`scss/design-card.scss`:

```scss
.lesson-design-card {
    max-width: 720px;
    margin: 0 auto;
    background: var(--ld-surface, #fff);
    color: var(--ld-text, #2d2d44);
    border-radius: 1rem;
    padding: 2rem;

    &__prompt {
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        margin-bottom: 1.5rem;
    }

    &__answers {
        display: grid;
        gap: 1rem;
        &--cols2 { grid-template-columns: 1fr 1fr; }
        &--cols1 { grid-template-columns: 1fr; }
    }

    &__answer {
        display: flex;
        align-items: center;
        gap: .5rem;
        background: #fff;
        border: 2px solid #eee;
        border-radius: .75rem;
        padding: 1rem;
        cursor: pointer;
        input:checked + & ,
        &:has(input:checked) { border-color: var(--ld-accent, #00b894); }
    }

    &__nav {
        display: flex;
        justify-content: space-between;
        margin-top: 1.5rem;
    }
}
```

Load it by appending an import to the module `styles.css` (create if absent) or via
the theme SCSS pipeline. Simplest in-module approach — add to
`mbsmoodle/public/mod/lesson/styles.css` a compiled CSS copy, OR register a SCSS
callback. For Phase 1, compile once and include the resulting CSS in `styles.css`:

Run: `cd mbsmoodle/public/mod/lesson && npx sass scss/design-card.scss styles_card.css`
then append its contents to `styles.css`. (Document the source in a header comment.)

- [ ] **Step 7: Wire the renderer switch**

In `renderer.php`, replace `display_page()` body (lines ~196-203) with (MBS-HACK):

```php
    public function display_page(lesson $lesson, lesson_page $page, $attempt) {
        // +++ MBS-HACK(<author>): design templates feature.
        $tpl = \mod_lesson\local\template_manager::get_for_lesson($lesson->properties());
        // Phase 1: only multichoice uses the new pipeline; 'default' keeps legacy markup.
        if ($tpl->get('baseskin') !== 'default'
                && $page->properties()->qtype == LESSON_PAGE_MULTICHOICE) {
            $renderable = new \mod_lesson\output\question_page($lesson, $page, $attempt);
            // Trigger the question viewed event to match legacy behaviour.
            $event = \mod_lesson\event\question_viewed::create([
                'context' => \context_module::instance($this->page->cm->id),
                'objectid' => $page->properties()->id,
                'other' => ['pagetype' => $page->get_typestring()],
            ]);
            $event->trigger();
            return $this->render_from_template('mod_lesson/pages/' . $tpl->get('baseskin') . '/question',
                $renderable->export_for_template($this));
        }
        // --- MBS-HACK
        // We need to buffer here as there is an mforms display call.
        ob_start();
        echo $page->display($this, $attempt);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }
```

> `LESSON_PAGE_MULTICHOICE` is defined in `pagetypes/multichoice.php`/`locallib.php`;
> confirm the constant name. The legacy event trigger normally happens inside
> `multichoice::display()`, which we now bypass — hence re-triggering here.

- [ ] **Step 8: Write the Behat render test**

`tests/behat/card_render.feature`:

```gherkin
@mod @mod_lesson
Feature: Card design renders question pages
  As a learner
  I see the card layout when a lesson uses the Monsterwelt template

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  Scenario: Multichoice question renders as a card
    Given the following "activities" exist:
      | activity | name    | course | idnumber | design      |
      | lesson   | Cardles | C1     | lesson1  | monsterwelt |
    And the following "mod_lesson > pages" exist:
      | lesson  | qtype       | title | content        |
      | Cardles | multichoice | Q1    | Pick the right |
    And the following "mod_lesson > answers" exist:
      | page | answer | jumpto    | score |
      | Q1   | Right  | Next page | 1     |
      | Q1   | Wrong  | This page | 0     |
    When I am on the "Cardles" "lesson activity" page logged in as student1
    Then ".lesson-design-card" "css_element" should exist
    And I should see "Right"
    And I should see "Wrong"
```

> Adjust the generator step names (`mod_lesson > pages`/`answers`) to the actual
> data generators in `tests/generator/`; if unavailable, build the page via the UI
> as an admin first, then view as the student.

- [ ] **Step 9: Run the Behat test**

Run: `bindev/behat.sh --tags=@mod_lesson` filtered to `card_render.feature`.
Expected: scenario passes; `.lesson-design-card` present; answers visible.

- [ ] **Step 10: Verify the default look is unchanged**

Manually (or via an additional Behat scenario with `design=default`): a lesson on
the `default` template renders exactly as before (legacy `moodleform` path,
no `.lesson-design-card`).

- [ ] **Step 11: Commit**

```bash
git add mbsmoodle/public/mod/lesson/classes/output/question_page.php \
        mbsmoodle/public/mod/lesson/templates/pages/card/question.mustache \
        mbsmoodle/public/mod/lesson/scss/design-card.scss \
        mbsmoodle/public/mod/lesson/styles.css \
        mbsmoodle/public/mod/lesson/renderer.php \
        mbsmoodle/public/mod/lesson/tests/output/question_page_test.php \
        mbsmoodle/public/mod/lesson/tests/behat/card_render.feature
git commit -m "feat(lesson): render multichoice via card design template"
```

---

## Task 7: Quality gates

- [ ] **Step 1: Run the code checker on the plugin**

Run: `bindev/codechecker.sh /var/www/html/public/mod/lesson`
Expected: no new violations in the files this plan touched. Fix any reported.

- [ ] **Step 2: Run moodlecheck (PHPDoc)**

Run: `bindev/moodlecheck.sh /var/www/html/public/mod/lesson`
Expected: clean for new files. Fix any missing PHPDoc.

- [ ] **Step 3: Run the full module unit suite**

Run: `bindev/phpunit.sh mod_lesson`
Expected: PASS (no regressions).

- [ ] **Step 4: Commit any fixes**

```bash
git add -A mbsmoodle/public/mod/lesson
git commit -m "chore(lesson): satisfy code quality gates for design templates"
```

---

## Phase 2 (outline — separate plan)

Apply the renderable/mustache pipeline to the remaining question types and content
pages, removing the `qtype == LESSON_PAGE_MULTICHOICE` guard in `display_page()`:

- `content_page` renderable + `templates/pages/card/content.mustache`.
- Per-type export: `truefalse`, `matching`, `numerical`, `shortanswer`, `essay`
  (each needs its input shape: radios, text inputs, select pairs).
- A `default`-skin mustache set proving byte-identical output, then switch even
  `default` to the renderable path (retire the per-type `moodleform` display where
  safe), guarded by the regression check.

## Phase 3 (outline — separate plan)

- Template-owned navigation + progress wired into `continue.php` / lesson flow:
  finalise `← Zurück / ↻ Nochmal / Weiter →` semantics per R1 (retry re-attempts the
  current page, honouring `retake`/`maxattempts`; hidden otherwise).
- Admin UX: enable/disable + drag reorder on the list page; duplicate-template action.
- Full accessibility pass (keyboard, labels, RTL) and theme-agnostic styling review.

---

## Self-Review notes

- **Spec coverage:** D1–D8 and R1–R4 map to tasks — schema/CRUD (T1, T3, T5),
  config-only validation (T2), per-lesson + site default (T4), rendering refactor
  (T6), retry honouring retake rules (T6 nav gate + Phase 3), plain-text answers
  (T6 export — no icon slot), self-contained palette (T6 CSS vars from config).
- **Capability version re-bump** is called out in T5 (capabilities load on upgrade).
- **Open verification points** flagged inline (generator method names, `properties()`
  accessor, `LESSON_PAGE_MULTICHOICE` constant, admin nav node path, SCSS loading) —
  confirm against the code during implementation rather than assuming.
