# Shop mode cycle 7b — hover preview follow-ups

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-24

## Goal

Close two notes the Implementer raised at the end of cycle 7 and the Planner missed on review: the panel height estimate is a few pixels short, so a preview pinned to the bottom of the window can overhang it; and the scroll listener added in `init()` is never removed, which is harmless on this page today but is the first `window` listener in a shop module and would leak if the pattern were copied to a component that mounts and unmounts. Two small edits in one file, plus the matching test expectation.

## Context

Baseline: cycle 7 accepted and archived (`docs/planImp/archive/2026-09-24-shop-mode-cycle-7/`); cycles 3–7 remain uncommitted in the working tree. Record `git status --short` at the start.

**The two notes, from `implemented.md` (Notes for Planner):**
- "`PEEK_H = 300` is an estimate … `320` would be safely conservative." Measured from `resources/css/shop.css` `APP ADDITIONS`: padding 8 + 8, border 1 + 1, image 256, caption margin 8 and one line at 16 px × 1.4 ≈ 22.4, giving ≈ 304 px. The vertical clamp in `peekAt()` uses `window.innerHeight - PEEK_H - PEEK_EDGE`, so a panel pinned at the bottom currently overhangs by about 4 px.
- "The scroll listener is never removed. `init()` adds a `scroll` listener on `window` … Alpine's `destroy()` hook is the place."

**Code today** (`resources/js/shop/find-product.js`): constants at lines 18–21 (`PEEK_W = 272`, `PEEK_H = 300`, `PEEK_GAP = 12`, `PEEK_EDGE = 8`); `init()` at line ~44 does `window.addEventListener('scroll', () => this.unpeek(), { passive: true })` with an anonymous arrow, so nothing can remove it. Alpine calls a data object's `destroy()` method when the element it is attached to is removed from the DOM.

**Existing check to update:** the plan-7 node check expected `160 492` for a thumbnail at `top: 700` in an 800 px window (`800 − 300 − 8 = 492`). With `PEEK_H = 320` the same input yields `800 − 320 − 8 = 472`.

## Constraints

- Do not commit, push or deploy.
- Only `resources/js/shop/find-product.js` changes. No view, stylesheet, test-file or README change is needed (no test asserts the numeric clamp).
- Behaviour otherwise identical.

## Out of scope

- Measuring the real panel height at runtime (the panel is `display: none` until opened, so a measurement would need a second positioning pass; the conservative constant is enough).
- The "keyboard scroll inside a nested scroller" note from the same report: nothing on this screen scrolls independently.
- Any other screen or cycle.

## Steps

### 1. Conservative panel height
Files: `resources/js/shop/find-product.js`
What: change `const PEEK_H = 300;` to `const PEEK_H = 320;` and adjust the adjacent comment (if any) to say it is the panel's outer height rounded up (≈ 304 px measured).
Check: `node -e "import('./resources/js/shop/find-product.js').then(m => { const d = m.default(); d.failed = {}; global.window = { innerWidth: 1280, innerHeight: 800, matchMedia: () => ({ matches: true }) }; const el = { getBoundingClientRect: () => ({ left: 100, right: 148, top: 700 }) }; d.peekAt({ id: 'a', image_url: 'u' }, el); console.log(d.peek.x, d.peek.y) })"` prints `160 472`.

### 2. Removable scroll listener
Files: `resources/js/shop/find-product.js`
What: add state `onScroll: null`. In `init()`, replace the anonymous listener with `this.onScroll = () => this.unpeek(); window.addEventListener('scroll', this.onScroll, { passive: true });`. Add a `destroy()` method: `if (this.onScroll) { window.removeEventListener('scroll', this.onScroll); this.onScroll = null; }`. Alpine invokes `destroy()` automatically when the component's element is removed.
Check: `node -e "import('./resources/js/shop/find-product.js').then(m => { const d = m.default(); const calls = []; global.window = { addEventListener: (t, f) => calls.push(['add', t, f]), removeEventListener: (t, f) => calls.push(['remove', t, f]), matchMedia: () => ({ matches: false }) }; d.\$refs = {}; d.readHistory = () => []; d.init(); d.destroy(); console.log(calls.length, calls[0][1], calls[0][2] === calls[1][2]) })"` prints `2 scroll true` (the same function reference is added and removed). If `init()` touches other globals the stub lacks (for example `this.$refs.input`), stub those too; the assertion that matters is the last one.

### 3. Build and format
Files: the one file
What: `npm run build`; `./vendor/bin/pint --dirty` (no PHP changed; run it anyway so the report can say so).
Check: `npm run build` succeeds; `grep -c "PEEK_H = 320" resources/js/shop/find-product.js` → 1; `grep -c "removeEventListener" resources/js/shop/find-product.js` → 1.

## Verification

1. `php artisan test --filter=Shop` → all green (104).
2. `php artisan test` → 17 failed / 504 passed, the identical 17.
3. `git diff --stat` shows only `resources/js/shop/find-product.js` (plus the protocol files).
4. `grep -n "PEEK_H\|removeEventListener\|destroy()" resources/js/shop/find-product.js` shows the three edits.
5. Manual on the till PC: search "oat", hover a thumbnail on the last visible row near the bottom of the window; the preview's bottom edge stays inside the window.

## Risks

- **`destroy()` and Alpine versions.** Supported since Alpine 3.x; the app already runs Alpine 3 (`resources/js/app.js`). If `destroy` were ignored, behaviour is exactly today's.
- None otherwise; both changes are local constants and bookkeeping.

## Review

Reviewed 2026-09-24 by the Planner against the full `implemented.md` (read to the end), the file, and a rerun of both node checks and the test suites.

Criteria:
1. `PEEK_H = 320` — PASS; clamp check prints `160 472`.
2. Removable scroll listener — PASS; `onScroll` stored, `destroy()` removes the same reference and is idempotent; check prints `2 scroll true`.
3. Build and format — PASS.

Verification rerun by the Planner: `--filter=Shop` 104 passed; full suite 17 failed / 504 passed, the identical 17.

Deviations: none. The stray `readHistory` stub in the plan's check was the Planner's copy-paste from the stock-scan module; harmless, noted.

Notes for Planner, each decided:
- Verification 3 (`git diff --stat`) cannot see untracked files: **accepted, rejected as a check**. Future plans use `git status --short` plus a grep on the file, as this cycle's Verification 4 did, until the shop directories are committed.
- Cycles 3–7 uncommitted: **deferred to the owner**, with the Planner's recommendation to commit now. Seven accepted cycles in one dirty tree make every report's "mine vs pre-existing" split harder to trust.
- `PEEK_H` remains a constant: **deferred**. The caption is `white-space: nowrap`, so it cannot wrap today; measuring `offsetHeight` after the first open is the durable fix if that ever changes.
- `docs/planImp/planimp.md` modified during the session: **mine** (the Planner, at the owner's request), not the implementer's; correctly flagged.

Result: ACCEPTED. Archived by the Planner to `docs/planImp/archive/2026-09-24-shop-mode-cycle-7b/`. Next: delivery receiving on the legacy flow.
