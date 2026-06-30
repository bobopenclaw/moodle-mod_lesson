# Prompt: Specify a "Design Templates" feature for mod_lesson

> Use this prompt to drive a **specification / brainstorming** session (not implementation yet).
> The goal is to produce a clear, agreed technical spec for adding selectable visual
> **design templates** to the Moodle Lesson activity, then a task-by-task implementation plan.

---

## 1. Role & objective

You are a senior Moodle plugin engineer working on a forked Moodle (`mbsmoodle/`).
Produce a technical specification for a new feature in the core activity module
`mod/lesson`: the ability to choose a **visual design template** per lesson that
changes how **every question page is displayed** to the student.

Do **not** write final production code in this phase. First clarify scope, propose
an architecture, surface trade-offs, and list open decisions. Only after the design
is approved should an implementation plan (task-by-task) be written.

## 2. Feature summary (from the requester)

- In the lesson settings form there is a new option to choose a **design template**.
- One option is **"Default"** — the current Moodle-standard look, unchanged.
- Selecting another template changes the **look of every question** as it is presented
  to the learner (see the reference screenshot the requester provided: a centered
  card with a prompt sentence containing a blank, and answer options shown as large
  rounded "choice cards" with emoji/icons, plus `← Zurück`, `↻ Nochmal`, `Weiter →`
  navigation buttons and a progress bar).
- We must decide **how to implement this** cleanly.

## 3. Grounding — how question display currently works (verify before designing)

This is a **core module**. Per repo policy (`.github/copilot-instructions.md` §2.2),
avoid forking core where avoidable; if a core edit is unavoidable, mark it with the
`MBS-HACK` pattern (§3.2). Confirm the following by reading the code:

- `mod/lesson/mod_form.php` — settings form. The **Appearance** section
  (`header 'appearancehdr'`) is where existing display options live
  (`progressbar`, `ongoing`, `displayleft`, `slideshow`, `maxanswers`, …). A new
  `design`/`template` select would be added here, with a matching site default in
  `settings.php` and `admin_setting` defaults pattern (`*_adv`).
- `mod/lesson/db/install.xml` — the `lesson` table. A new persisted column
  (e.g. `design` varchar/int) is needed, plus a bump in `version.php` and an
  upgrade step in `db/upgrade.php` (`add_field` + `savepoint`).
- `mod/lesson/renderer.php` — `display_page()` buffers and echoes
  `$page->display($this, $attempt)`. This is the central rendering choke point.
- `mod/lesson/pagetypes/*.php` — each question type
  (`multichoice`, `truefalse`, `matching`, `numerical`, `shortanswer`, `essay`)
  implements `display()` and builds its own `moodleform` subclass
  (e.g. `lesson_display_answer_form_multichoice_singleanswer`) that adds raw
  `radio`/`html`/`header` elements and calls `$mform->display()`.
  **Key constraint:** the current markup is generated imperatively inside
  `moodleform` subclasses, not from mustache templates — so a redesign cannot be
  achieved by overriding a single template today.
- `mod/lesson/templates/*.mustache` — existing templates are only for edit/report
  action menus, **not** for student-facing question rendering.

## 4. Questions to resolve before proposing the design

Ask the requester (or state explicit assumptions) for each:

1. **Scope of "every question"** — all six pagetypes, or only multichoice/truefalse
   to start? Do branch/content pages also get the new look?
2. **Number of templates** — just `Default` + one new ("Monsterwelt"-style), or an
   extensible set? Should third-party plugins be able to register templates?
3. **Per-lesson vs site-wide** — confirm it is per-lesson (matches "lesson settings"),
   with a site default fallback.
4. **The emoji/icons per answer** in the screenshot — are these authored content,
   auto-derived, or purely decorative? Where would that data come from? (Today
   answers are plain HTML text; there is no icon field.)
5. **Navigation buttons** (`Zurück / Nochmal / Weiter`) and **progress bar** — are
   these part of the template, or do they reuse existing lesson navigation/progress?
6. **Theme coupling** — should templates live in `mod_lesson`, or be provided by a
   theme (e.g. the MBS theme)? Should colors come from theme SCSS variables?
7. **Accessibility / i18n** — keyboard nav, screen-reader labels, RTL, and string
   externalization for any new UI text.
8. **Backward compatibility** — existing lessons must keep the Default look with no
   data migration surprises (sensible default value for the new column).

## 5. Implementation approaches to evaluate (pros/cons required)

Compare at least these, then recommend one:

- **A. CSS-only theming** — add a CSS class on a wrapper (e.g.
  `<div class="lesson-design-monsterwelt">`) keyed off the selected template and
  restyle existing markup. *Lowest risk, no core form changes, but limited:
  cannot restructure DOM (cards, blanks, emoji) that the screenshot implies.*
- **B. Refactor question rendering to mustache templates + renderable/templatable** —
  introduce `lesson_question` renderables and per-template mustache files; switch
  the `moodleform` subclasses to emit data the renderer turns into HTML.
  *Cleanest long-term, enables real redesign, but larger refactor of core
  pagetypes — needs `MBS-HACK` markers and careful regression testing.*
- **C. Pluggable renderer / theme override** — use Moodle's renderer override
  mechanism (`theme/<x>/renderers.php` or `mod_lesson` renderer subclass) selected
  by the template setting. *Keeps core thinner, but selection-per-lesson via a
  theme renderer is awkward.*
- **D. Hybrid** — minimal core hook (a wrapper class + a renderer switch) plus
  CSS for "Default", and a templated path only for new designs.

For the recommended approach, specify: data model change, settings UI change,
the rendering switch point, where template assets (mustache/SCSS/JS) live, and the
fallback behavior.

## 6. Deliverables expected from this prompt

1. A short **assumptions & open-questions** list (answers to §4).
2. A **recommended architecture** (one of §5) with justification.
3. **Data model**: exact `lesson` table change, `install.xml` + `db/upgrade.php` +
   `version.php` bump, default value, and `settings.php` site default.
4. **Settings UI**: the new `mod_form.php` element, lang strings, help button.
5. **Rendering design**: the switch point, list of affected pagetypes, template/SCSS
   file layout, and how the Default template stays byte-identical to today.
6. **Non-functional**: accessibility, i18n, performance, and how it is tested
   (PHPUnit for data/model, Behat for the UI, per `.github/copilot-instructions.md` §4.2).
7. A **risk list**, especially every required core edit (to be wrapped in `MBS-HACK`).
8. Only after approval: a **task-by-task implementation plan** (use the
   `writing-plans` skill) with file paths, code, tests, and code-quality steps
   (`bindev/codechecker.sh`, `bindev/moodlecheck.sh`).

## 7. Constraints (must follow)

- Moodle Coding Style + PHPDoc on all new classes/methods/files.
- Core APIs first (`$DB`, `moodle_url`, `html_writer`, `get_string`, mustache,
  renderables/templatables) — no bespoke reinvention.
- Security: validate the template setting against an allowlist; escape all output;
  capability checks unchanged.
- Surgical changes: keep "Default" pixel-identical; do not refactor unrelated code.
- Mark any unavoidable core deviation with the `MBS-HACK(author): reason` pattern.
- Run code-quality tools before declaring done (see repo §4.3 / §5).

---

### Reference
Requester's original note: `mod/lesson/ai_prompts/start.md`
Screenshot: centered question card, blank-in-sentence prompt, four rounded answer
cards with emoji, and `Zurück / Nochmal / Weiter` navigation + progress bar.
