# Plan / Implement protocol

Two Claude Code sessions share this folder. They never share context, so the
files here are the only channel between them.

| Role        | Session      | Owns             | Never touches                        |
|-------------|--------------|------------------|--------------------------------------|
| Planner     | Fable 5.1    | `plan.md`        | Application code, `implemented.md`   |
| Implementer | Opus         | `implemented.md` | `plan.md`                            |

Both sessions read `planimp.md` (this file), `plan.md`, `implemented.md`, and
the project `CLAUDE.md` before doing anything. Neither commits, deploys, or
runs `deploy.sh` unless `plan.md` says so explicitly.

## The loop

```
Planner writes plan.md            status: READY
        │
        ▼
Implementer works, writes implemented.md     status: IN PROGRESS → DONE | BLOCKED
        │
        ▼
Planner reviews implemented.md + git diff, updates plan.md
        │
        ├─ status: ACCEPTED   → user archives both files, next task starts fresh
        └─ status: READY (revision N+1) → back to Implementer
```

The `Status:` line at the top of each file is the handshake. Check it before
you start. Do not act on a `plan.md` that is `DRAFT`, and do not review an
`implemented.md` that is still `IN PROGRESS`.

### Kickoff prompts for the user

Planner session:

```
Read docs/planImp/planimp.md. You are the Planner. <describe the task>
```

Implementer session:

```
Read docs/planImp/planimp.md. You are the Implementer. Implement docs/planImp/plan.md.
```

Planner review:

```
Read docs/planImp/planimp.md. You are the Planner. Review docs/planImp/implemented.md.
```

## Planner (Fable)

Your job is to think, not to type code. Spend the effort on reading the
codebase, finding the real constraints, and writing a plan the Implementer
can follow without guessing.

Do:

- Read the relevant code, migrations, tests, and docs before writing anything.
  Every file you name in the plan must exist, or be explicitly marked `(new)`.
- Run read-only commands freely: `git log`, `grep`, `php artisan route:list`,
  `php artisan tinker` with queries, `php artisan test --filter=...`.
- Write steps that are small, ordered, and each have a concrete check.
  "Add the column" is not a step. "Add nullable `published_at` timestamp to
  `rosters` via a new migration; `php artisan migrate` runs clean and
  `Schema::hasColumn('rosters','published_at')` is true" is a step.
- State what is out of scope. The Implementer will otherwise decide for you.
- Name the business rule or law behind a constraint (UK/Irish employment law
  matters in this app) so the Implementer doesn't "simplify" it away.
- Include short code snippets only where wording would be ambiguous.
  Snippets illustrate intent; the Implementer owns the final code.
- When reviewing, read the actual diff (`git diff`, `git status`), not just
  `implemented.md`. Check every acceptance criterion yourself. Rerun the
  verification commands.
- Read `implemented.md` to the end before accepting. `## Deviations` and
  `## Notes for Planner` come last and are where the Implementer records what
  the plan got wrong and what it noticed but did not touch. Every note gets an
  explicit decision in the `## Review` section: fixed now (as new steps in a
  bumped Revision, or a follow-up plan), deferred with a reason, or rejected
  with a reason. A review that does not mention the notes is not finished.
  (Cycle 7, 2026-09-24: two correct notes were missed because the report was
  read only part-way; a follow-up cycle was needed.)

Don't:

- Edit application code, even for a "trivial" fix. Put it in the plan.
- Write `implemented.md`. If it is wrong or missing, say so in the review
  section of `plan.md`.
- Leave open questions in a `READY` plan. Resolve them (ask the user, or
  decide and record the decision) or mark the plan `DRAFT`.

When the work is accepted, set `Status: ACCEPTED` and tell the user to
archive: `mkdir -p docs/planImp/archive/YYYY-MM-DD-<slug>` and move both
`plan.md` and `implemented.md` there. The next task starts with empty files.

### `plan.md` template

```markdown
# <Task title>

Status: DRAFT | READY | ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: YYYY-MM-DD

## Goal
One paragraph. What changes for the user of the app, and why.

## Context
What the Planner learned from the codebase that the Implementer needs:
relevant models, services, routes, existing patterns to copy, gotchas.

## Constraints
- Business rules that must hold (cite the doc or law).
- Things that must not change (public routes, existing data, API shapes).
- Commit / deploy policy for this task (default: do not commit).

## Out of scope
Explicit list. The Implementer must not do these even if tempting.

## Steps
### 1. <Short name>
Files: `app/...`, `database/migrations/... (new)`
What: exact change.
Check: command or observation that proves it worked.

### 2. ...

## Verification
Commands to run at the end, in order, with expected outcomes.
Example: `php artisan test --filter=RosterTest` → all pass.

## Risks
Where this could go wrong and what to watch for.

## Review
(Planner fills this in after reading implemented.md and the diff.)
- Criterion-by-criterion pass/fail.
- Deviations: each accepted or rejected, with a reason.
- Notes for Planner: each one fixed now, deferred (why), or rejected (why).
- Anything to redo, as new numbered steps in a bumped Revision.
```

## Implementer (Opus)

Your job is to execute the plan faithfully and report exactly what happened.
The plan was written by a session that read the code carefully. Trust it,
but verify each step's check before moving on.

Do:

- Start by recording the baseline in `implemented.md`: `git rev-parse --short HEAD`
  and the output of `git status --short`. The working tree may already be
  dirty; the reviewer needs to know which changes are yours.
- Set `Status: IN PROGRESS` immediately, then update `implemented.md` after
  each step, not at the end. If the session dies, the Planner should still be
  able to see how far you got.
- Follow the steps in order. Run every `Check:` and paste the real output
  (trimmed) into `implemented.md`. "Verified" with no evidence is not
  verified.
- Run the full `## Verification` section at the end and record the output,
  including failures.
- Deviate from the plan only when a step is impossible or clearly wrong as
  written. Make the smallest deviation that unblocks you, and record it
  under `## Deviations` with the reason. If the deviation would change
  behaviour the plan cares about, stop instead and set `Status: BLOCKED`.
- Set `Status: DONE` only when every step is done and verification ran.
  Otherwise `BLOCKED` with a clear statement of what is needed.

Don't:

- Touch anything under `## Out of scope`, or anything the plan doesn't
  mention. Noticed something worth fixing? Put it in `## Notes for Planner`.
- Edit `plan.md`. Disagreements go in `implemented.md`.
- Commit, push, or deploy unless the plan's Constraints section says to.
- Mark a step done because the code "should" work. Run the check.
- Delete or rewrite existing tests to make verification pass. Report the
  failure and set `BLOCKED`.

### `implemented.md` template

````markdown
# <Task title> — implementation

Status: IN PROGRESS | DONE | BLOCKED
Plan revision: 1
Implementer: Opus
Date: YYYY-MM-DD

## Baseline
HEAD: <short sha>
Pre-existing dirty files:
<git status --short output, or "clean">

## Steps
### 1. <Short name> — done | skipped | blocked
Changed: `app/...`
Check output:
```
<trimmed real output>
```

### 2. ...

## Deviations
What was done differently from the plan and why. "None" if none.

## Verification
Each command from the plan's Verification section with its actual result.

## Files changed
`git status --short` at the end, with pre-existing dirty files marked.

## Notes for Planner
Things noticed but not acted on. Questions. Suggested follow-ups.
````

## Why the split

Fable is the stronger reasoner and the more expensive session. Spending it on
reading the codebase, catching the constraint nobody mentioned, and reviewing
the diff is where it pays off. Opus is fast and reliable at executing a
well-specified plan. The protocol only works if the plan carries everything
the Implementer needs and the report carries everything the reviewer needs,
because neither session can ask the other a question.
