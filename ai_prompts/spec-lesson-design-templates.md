# Lesson Design Templates — Technical Specification

**Status:** Draft for approval
**Date:** 2026-06-30
**Module:** `mod_lesson` (Moodle core activity, in the `mbsmoodle/` fork)
**Author:** (fill in)

> This is the agreed design produced from the brainstorming session. Once approved,
> turn it into a task-by-task plan with the `writing-plans` skill
> (`docs/plans/YYYY-MM-DD-lesson-design-templates.md`), then implement Phase 1 first.

---

## 1. Goal

Let a lesson choose a **visual design template** that changes how every page
(question pages **and** content/branch pages) is rendered to the learner.
"Default" reproduces the current Moodle look exactly. Additional templates are
**managed by admins through a CRUD admin interface** and selected **per lesson**.

## 2. Decisions locked in brainstorming

| # | Decision | Choice |
|---|---|---|
| D1 | Scope of pages | **All 6 question pagetypes** + content/branch pages |
| D2 | Template model | **Config-only (Model 1)** — admin CRUD edits *parameters*; markup comes from shipped, safe mustache files. No admin-authored raw markup. |
| D3 | Extensibility | **Admin CRUD**: admins create/edit/delete template *configurations* that extend a built-in base skin |
| D4 | Answer content | **Plain text only** — answer text is displayed as-is; no icon/emoji parsing or extra slot |
| D5 | Nav + progress | **Template-owned** navigation (`← Zurück / ↻ Nochmal / Weiter →`) and progress bar |
| D6 | Location | **In `mod_lesson` core** (fork edits marked with `MBS-HACK`) |
| D7 | Rendering | **Mustache + renderable/templatable refactor** of page display |
| D8 | Delivery | **Phased** — Phase 1 is a vertical slice on `multichoice` before the broad rollout |

## 3. Why config-only (D2) — security rationale

Admin-authored mustache/HTML stored in the DB is a server-side-template-injection
and XSS vector and is near-impossible to keep accessible. Instead a "template" is a
**named set of safe parameters** (palette, layout variant, icon toggle, nav style)
consumed by a fixed family of vetted mustache files shipped in the module. The
output is always escaped through Moodle's renderer/mustache pipeline. "Extensible"
= admins compose new looks from safe building blocks, not write code.

## 4. Data model

### 4.1 New table `lesson_design_template`

`mod/lesson/db/install.xml`:

| Field | Type | Notes |
|---|---|---|
| `id` | int, PK, autoincrement | |
| `name` | varchar(255) | Human label shown in pickers |
| `idnumber` | varchar(100) | Stable machine id (unique), used as CSS namespace + lookup |
| `baseskin` | varchar(100) | Which built-in mustache family it extends (e.g. `default`, `card`) |
| `config` | text | JSON of safe parameters (validated server-side against a schema) |
| `enabled` | tinyint(1), default 1 | Hidden templates can't be picked in new lessons |
| `sortorder` | int, default 0 | Ordering in pickers/admin list |
| `timecreated` | int | |
| `timemodified` | int | |

Unique index on `idnumber`.

### 4.2 New column on `lesson`

`mod/lesson/db/install.xml`, `lesson` table:

| Field | Type | Notes |
|---|---|---|
| `design` | varchar(100), NOT NULL, default `'default'` | Stores the chosen template `idnumber`. `'default'` = current look. |

Using the `idnumber` string (not the numeric FK) keeps existing lessons valid even
if a template row is deleted — unknown/disabled values fall back to `default` at
render time.

### 4.3 Built-in templates (seeded, not user-deletable)

- `default` — `baseskin=default`, empty config; **byte-identical to today's output**.
- `monsterwelt` — `baseskin=card`, config = the screenshot palette/layout (centered
  card, choice cards, template-owned nav + progress). Answer text rendered as-is.

Seed via `db/upgrade.php` / install with a guard so re-running is idempotent.
Built-ins are flagged (e.g. a reserved `idnumber` range or a `builtin` marker) so the
admin CRUD allows edit-config but blocks delete/rename of `default`.

### 4.4 Versioning / upgrade

- Bump `mod/lesson/version.php`.
- `mod/lesson/db/upgrade.php`: `add_field('design')`, `create_table` for
  `lesson_design_template`, seed built-ins, `upgrade_mod_savepoint(...)`.

## 5. Settings & capabilities

- **Per-lesson selection:** new `select` in the **Appearance** section of
  `mod/lesson/mod_form.php`, options built from enabled templates, default from
  site config; help button + lang strings.
- **Site default:** `admin_setting_configselect_with_advanced('mod_lesson/design', …)`
  in `mod/lesson/settings.php`, alongside the existing appearance defaults.
- **Admin CRUD page:** new pages under
  *Site administration → Plugins → Activity modules → Lesson → Design templates*
  (`admin_externalpage` + a controller in `mod/lesson/`), with list / add / edit /
  delete + enable/disable + reorder.
- **New capability:** `mod/lesson:managetemplates`, `CONTEXT_SYSTEM`,
  archetypes `manager` (and admin). Gates the CRUD page and all mutations.
  `riskbitmask` `RISK_CONFIG`.
- All CRUD writes go through `$DB` with `sesskey`/`require_capability`; the `config`
  JSON is validated against an allowlist schema before save.

## 6. Rendering architecture (D7)

### 6.1 The problem today

Each pagetype's `display()` (e.g. `lesson_display_answer_form_multichoice_singleanswer`
in `pagetypes/multichoice.php`) builds markup imperatively inside a `moodleform`
subclass and calls `$mform->display()`. The look is therefore hardcoded across six
files plus content/branch pages — a single template override is impossible now.

### 6.2 Target design

Introduce a renderer-driven, templatable pipeline at the existing choke point
`lesson_renderer::display_page()` (`mod/lesson/renderer.php`):

1. **Renderable per page kind** — new classes in
   `mod/lesson/classes/output/` implementing `renderable, templatable`, e.g.
   `question_page`, `content_page`, each `export_for_template()` producing a
   neutral data structure: `prompt` (with optional blank), `answers[]` (each
   `{ label, inputname, value, type, checked, disabled }`), `navigation`
   (`{ back, retry, next }`), `progress`, and `design` (`idnumber`, `baseskin`,
   resolved `config`).
2. **Template resolution** — a `template_manager` service loads the lesson's
   `design`, falls back to `default` if missing/disabled, and exposes the resolved
   `config` + the mustache name to render (`mod_lesson/pages/<baseskin>/question`).
3. **Mustache files** — `mod/lesson/templates/pages/default/*.mustache` reproduces
   current markup; `…/card/*.mustache` implements the new card look. Per-template
   parameters (colors, icon toggle, nav style) are passed as context, not markup.
4. **Form/CSRF** — answer submission still posts to `continue.php`; the mustache
   renders the same field names (`answerid`, `answer[]`, `id`, `pageid`, `sesskey`)
   the existing `check_answer()` expects, so backend logic is untouched.
5. **Answer content (D4)** — answer text is rendered as plain text (through Moodle's
   normal `format_text` escaping). No icon/emoji slot or parsing.
6. **Nav + progress (D5)** — `← Zurück` / `↻ Nochmal` / `Weiter →` and the progress
   bar are part of the template context. `Nochmal` re-attempts the current page
   **only when** the lesson's existing retake/attempt rules allow it; otherwise the
   control is hidden (see §11 R1).

### 6.3 "Default must not change"

The `default` baseskin mustache must emit markup equivalent to today's output
(same wrapper classes, radio structure, `answeroption` divs, action buttons). A
Behat/HTML-diff check guards this. Where a `moodleform` produced framework markup
that can't be reproduced 1:1, that pagetype keeps using the `moodleform` path under
`default` and only the **new** templates use the renderable path (hybrid fallback),
so we never regress the standard look.

## 7. Phasing (D8)

**Phase 1 — vertical slice (proves the whole stack):**
- `lesson_design_template` table + `design` column + upgrade + seed built-ins.
- Admin CRUD page + `mod/lesson:managetemplates` capability.
- Appearance `design` select + site default.
- Refactor **`multichoice` only** to the renderable/mustache pipeline with
  `default` (identical) and `monsterwelt` (`card`) templates, incl. icons + nav +
  progress.
- Tests: PHPUnit (model, template_manager, export_for_template, JSON validation),
  Behat (pick template, render card look, answer submits correctly), default-look
  regression check.

**Phase 2 — breadth:** roll the pipeline out to `truefalse`, `matching`,
`numerical`, `shortanswer`, `essay`, and content/branch pages.

**Phase 3 — polish:** finalize template-owned nav/progress everywhere, admin
reorder/enable UX, theme palette wiring, full a11y pass.

## 8. Non-functional requirements

- **Security:** allowlist-validate `config` JSON; escape all output; capability +
  `sesskey` on every CRUD mutation; `design` value validated against existing
  templates at render (fallback to `default`).
- **Accessibility:** answer cards are real radio/checkbox controls with visible text
  labels; keyboard navigable; nav buttons are `<button>`/links with discernible
  text; RTL ok.
- **i18n:** all new UI strings in `mod/lesson/lang/en/lesson.php`.
- **Performance:** one extra small query per lesson view (template lookup), cacheable.
- **Backward compatibility:** existing lessons default to `design='default'` → no
  visual change, no content migration.

## 9. Core edits requiring `MBS-HACK` markers

- `mod/lesson/db/install.xml`, `db/upgrade.php`, `version.php`
- `mod/lesson/db/access.php` (new capability)
- `mod/lesson/settings.php` (site default + admin externalpage)
- `mod/lesson/mod_form.php` (appearance select)
- `mod/lesson/renderer.php` (`display_page` switch)
- `mod/lesson/pagetypes/multichoice.php` (Phase 1), then remaining pagetypes (Phase 2)
- New (not hacks, additive): `mod/lesson/classes/output/*`,
  `mod/lesson/classes/local/template_manager.php`, `mod/lesson/templates/pages/**`,
  admin CRUD controllers/forms, `lang` strings.

## 10. Test plan

- **PHPUnit:** template CRUD + `idnumber` uniqueness; `config` schema validation
  (accept/reject); `template_manager` resolution + fallback; `export_for_template`
  for multichoice (answer text, checked/disabled state, nav/progress flags).
- **Behat:** admin creates/edits/deletes a template; teacher selects it on a lesson;
  learner sees the card look and a submitted answer is graded correctly; default
  template renders unchanged.
- **Quality gates:** `bindev/codechecker.sh <abs path>` and
  `bindev/moodlecheck.sh <abs path>` clean before finishing.

## 11. Resolved decisions

- **R1 — `↻ Nochmal` (retry):** re-attempts the **current page**, and **respects the
  lesson's existing retake / maximum-attempts rules**. When retakes aren't allowed,
  the control is hidden. No new retry semantics are introduced.
- **R2 — Answer content:** answers are **plain text only**. No emoji/icon parsing,
  no icon slot — the answer text is displayed as-is via `format_text`.
- **R3 — `config` schema (concrete allowlist):** validated server-side on save;
  unknown keys rejected. Per `baseskin`:
  - `default`: `config = {}` (no parameters; not editable).
  - `card`:
    - `palette`: object of hex-colour strings — `primary`, `surface`, `text`,
      `accent` (each `^#[0-9a-fA-F]{6}$`).
    - `answercolumns`: int, `1` or `2` (default `2`).
    - `nav`: object of booleans — `showback`, `showretry`, `shownext`.
    - `showprogress`: boolean.
  Any future `baseskin` ships its own allowlist alongside its mustache files.
- **R4 — Theme coupling:** templates are **self-contained**. The `card` palette comes
  from its own `config` colour tokens (with built-in defaults); it does **not** read
  theme SCSS variables.

---

### References
- Requester note: `mod/lesson/ai_prompts/start.md`
- Refined prompt: `mod/lesson/ai_prompts/start_refined.md`
- Repo policy: `.github/copilot-instructions.md` (§2 Moodle rules, §3.2 MBS-HACK, §4 workflows)
