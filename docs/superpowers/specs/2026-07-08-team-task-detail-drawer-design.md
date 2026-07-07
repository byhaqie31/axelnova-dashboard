# Team tasks: read-only task detail drawer

**Date:** 2026-07-08
**Branch:** feat/quotation-builder-v2
**Status:** Design approved — proceeding to implementation

## Problem

On `/team/tasks`, kanban cards line-clamp `description` to 2 lines
([tasks/index.vue](../../../frontend/app/pages/team/tasks/index.vue) `.kanban-card-desc`),
so a teammate can't read the full brief/description that was pasted into a task. There's
no detail view anywhere on the team side.

## Decisions (confirmed with user)

- **Content:** full detail — untruncated description + all meta (status, priority, deadline,
  duration estimate, pay + payment state, assignee, created-by, created/completed/paid dates).
- **Notes:** show the append-only notes/activity log read-only (already in the payload;
  [Team/TasksController.php:131](../../../backend/app/Http/Controllers/Api/V1/Team/TasksController.php#L131)
  appends timestamped lines).
- **Actions:** include the *one* contextual action the card offers, pinned at the drawer
  bottom (Pick up / Start / Complete… + Release / none). Still no editable fields — not a form.

## Approach

A new presentational component `app/components/team/TaskDetailDrawer.vue` mirroring the
existing §12.13 right-side slideover (scoped CSS, `translateX(100%)` enter, scrim + Escape).
The team tasks page owns selection + action state; the drawer renders detail and emits action
events. Built as a component (not a 3rd inline copy) so `/team/calendar` can reuse it later.

**Not** promoting the slideover CSS to `main.css` or refactoring the admin copies — separate
refactor, out of scope.

## Design

### `TaskDetailDrawer.vue`
- **Props:** `task: TaskRecord | null`, `busy: boolean`, `variant: 'pool' | 'startable' | 'in_progress' | 'done'`.
- **Emits:** `close`, `pickup`, `start`, `complete`, `release`.
- **Structure:** `Teleport to body` → `<Transition name="slideover">` → `v-if="task"` scrim +
  `<aside>` panel. Same cached-DOM-during-leave behavior as the page's Complete dialog, so
  bindings never dereference a null task during the exit animation.
- **Header:** title + `StatusPill(status, type='task')` + close button.
- **Meta rows:** priority chip (taskPriorityMeta) · deadline w/ overdue danger tint · duration
  estimate · `TaskPayBadge(state, amount)` · assignee ("Assigned to X" / "Unassigned") ·
  created-by · created / completed / paid dates (each shown only when present).
- **Description:** full, `white-space: pre-wrap`; empty → "No description provided."
- **Notes / activity:** full `notes`, `pre-wrap`; empty → section hidden.
- **Footer (pinned, only when variant has an action):** contextual button(s) by variant —
  `pool`→Pick up (emit `pickup`), `startable`→Start (`start`), `in_progress`→Complete…
  (`complete`) + Release (`release`), `done`→none. Buttons disabled while `busy`.
- Full-width on mobile, `max-width: 480px` desktop. Respects `prefers-reduced-motion`.

### Team tasks page wiring ([tasks/index.vue](../../../frontend/app/pages/team/tasks/index.vue))
- State: `detailTask = ref<TaskRecord | null>(null)`, `detailVariant = ref<Variant>('pool')`.
- `openDetail(task, variant)` sets both.
- Each `<article class="kanban-card">` gets `@click="openDetail(t, <variant>)"`, `role="button"`,
  `tabindex="0"`, Enter/Space handler, and a hover affordance. Existing action buttons get
  `@click.stop` so acting doesn't also open the drawer.
- Render `<TaskDetailDrawer :task="detailTask" :variant="detailVariant" :busy="actingId !== null"
  @close="detailTask = null" @pickup="claim(detailTask!)" @start="start(detailTask!)"
  @complete="openComplete(detailTask!)" @release="release(detailTask!)" />`.
- Extend the existing `onKeyStroke('Escape')`: close the Complete dialog first, else the drawer.
- The card description line-clamp stays (cards stay scannable; full text lives in the drawer).

## Out of scope

- Promoting the slideover shell to `main.css` / refactoring admin's copies.
- `/team/calendar` adoption (the component is built to allow it later, but not wired now).
- Any editable/task-mutation UI beyond the existing contextual actions.

## Verification

- `nuxt typecheck` + `eslint` clean.
- Manual: click a card → drawer shows full description + notes; action buttons act without
  opening the drawer; Complete… opens its dialog layered above; Escape/scrim/✕ close in the
  right order; verify light + dark and a narrow viewport.

## Notes

- Per standing user preference, spec and code are **not** committed until requested.
