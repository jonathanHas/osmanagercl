# KDS modifier badges: per-kind shapes on /kds, chosen on /coffee/metadata — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-23

## Baseline
HEAD: 828903a2
Pre-existing dirty files (none of these are mine):
```
 M app/Http/Controllers/CoffeeMetadataController.php   [metadata-page work described in plan Context]
 M docs/features/product-search.md                     [previous task + hover preview]
 M docs/planImp/implemented.md                         [previous task's report — see note below]
 M docs/planImp/plan.md                                [Planner's new plan]
 M resources/views/coffee/metadata.blade.php           [metadata-page work described in plan Context]
 M resources/views/components/product-search.blade.php [hover preview, out of scope here]
?? resources/views/components/product-search/          [hover preview, out of scope here]
```

`CoffeeMetadataController.php` and `coffee/metadata.blade.php` were already
dirty when I started — that is the uncommitted Alpine-modal / KDS-preview work
the plan's Context says to treat as the baseline. I build on it and revert
nothing. `product-search*` files are the unrelated hover-preview change and I
do not touch them.

Note on this file: it previously held the finished *product-search* report
(`Status: DONE`, plus a note appended 2026-09-22). I copied it to the session
scratchpad as `implemented-product-search-PREVIOUS.md` before overwriting, and
the version as committed at HEAD is still available via
`git show 828903a2:docs/planImp/implemented.md`. The Planner has already
replaced `plan.md` with this task, so the previous pair was never archived
under `docs/planImp/archive/`.

## Steps

### 0. Preserve previous report, record baseline — done
See Baseline above.

### 1. Column, model constant, backfill — done
Changed: `database/migrations/2026_09_23_000001_add_badge_kind_to_coffee_product_metadata.php` (new),
`app/Models/CoffeeProductMetadata.php` (`badge_kind` in `$fillable`, `BADGE_KINDS`
const with the twelve kinds in picker order, `badgeFamily()`).
The backfill patterns live in a private `BACKFILL` const on the migration, in
the plan's order, and run through Eloquent so the same code works on MySQL and
SQLite.
Check output:
```
$ php artisan migrate
   INFO  Running migrations.
  2026_09_23_000001_add_badge_kind_to_coffee_product_metadata .. 156.43ms DONE

$ php artisan tinker --execute='...option rows with badge_kind...'
Extra Hot     Extra Hot                          => null
Hot Milk      Hot Milk                           => null
Cocoa         Cocoa                              => null
Decaf         Decaf                              => decaf
Extra Shot    Espresso Extra Shot                => shot
ON ICE        ON ICE                             => ice
Singl         single shot                        => null
Small Cup     Small Cup                          => null
Almond        Milk Alternative Almond            => almond
Alt Milk      Milk Alternative                   => null
Coconut       Milk Alternative Coconut           => coconut
Oat           Milk Alternative OAT               => oat
2Go Cup       2GoCup Cup                         => null
2Go Return    2GoCup Cup Return                  => null
Cup Disc      Cup Discount                       => null
Sit In        Sit In                             => null
Takeaway      Take Away                          => null
Caramel       Syrup Caramel                      => caramel
Hazel         Syrup Hazelnut                     => hazelnut
Syrup         Syrup                              => null
Van           Syrup Vanilla                      => vanilla
```
Exactly the nine mappings the plan's Context lists, `null` for the other twelve.
`Sit In` and `Take Away` confirm the `\bice\b` word boundary was the right call
(neither "Service"-style substring matched).

### 2. Carry the kind through `card_items` — done
Changed: `app/Models/KdsOrder.php` — the two `pluck()` calls collapsed into one
`->get(['product_id','type','short_name','badge_kind'])->keyBy('product_id')`,
each modifier now `['label' => …, 'kind' => …]`, native POS ATTRIBUTES values
mapped to `['label' => …, 'kind' => null]`, docblock rewritten to describe the
new shape and the one-query rule.
Check output (a real order that already carries ON ICE + Oat on a Latte):
```
$ php artisan tinker --execute='...latest order with option lines...'
order id: 39147
Array
(
    [0] => Array
        (
            [id] => 79676
            [product_name] => Latte
            [quantity] => 1
            [kind] => drink
            [modifiers] => Array
                (
                    [0] => Array ( [label] => ON ICE  [kind] => ice )
                    [1] => Array ( [label] => Oat     [kind] => oat )
                )
            [notes] =>
        )
)
```

### 3. Shared badge assets: CSS, SVG sprite, JS renderer, Blade component — done
Changed: `resources/views/kds/_modifier-badge-assets.blade.php` (new),
`resources/views/components/kds/modifier-badge.blade.php` (new),
`app/Models/CoffeeProductMetadata.php` (`BADGE_DECOS`, see Deviations #3).

The partial carries `.mb` / `.mb__bg` / `.mb__deco`, four `.mb--f-*` placement
classes, twelve `.mb--{kind}` colour classes (`--mb-crema` / `--mb-dash` on
shot and decaf), a `#mb-sprite` of four shape symbols plus five deco symbols
with `style="fill:var(--mb-fill);…"`, and the `window.kdsModifierBadgeHtml()`
renderer with its own local `esc()`. Both files open with a LOCKSTEP comment
naming the other. Kind lookup in the JS goes through
`Object.prototype.hasOwnProperty` so a stray `kind` of `"constructor"` from the
JSON cannot reach the class-name interpolation.
Check output:
```
$ php artisan tinker --execute='...Blade::render of the component...'
--- decaf ---
<span class="mb mb--f-shot mb--decaf"><svg class="mb__bg" aria-hidden="true"><use href="#mb-shape-shot"/></svg><span class="mb__label">Decaf</span></span>

--- unknown ---
<span class="item__mod">Decaf</span>

--- null ---
<span class="item__mod">Takeaway</span>

--- oat (decos) ---
<span class="mb mb--f-milk mb--oat"><svg class="mb__bg" …><use href="#mb-shape-milk"/></svg><svg class="mb__deco" style="width:0.42em;height:0.42em;right:-0.62em;bottom:0.05em" …><use href="#mb-deco-dot-a"/></svg><svg class="mb__deco" style="width:0.24em;…" …><use href="#mb-deco-dot-b"/></svg><svg class="mb__deco" style="width:0.28em;…" …><use href="#mb-deco-dot-b"/></svg><span class="mb__label">Oat</span></span>

--- xss ---
… <span class="mb__label">&lt;b&gt;x&lt;/b&gt;</span></span>
```
`kind="decaf"` gives `mb--f-shot`, `mb--decaf`, `#mb-shape-shot` and `>Decaf<`;
`kind="nope"` and a null kind both give the plain chip; the label is escaped.

### 4. Render badges on /kds — done
Changed: `resources/views/kds/_item.blade.php` (the `@foreach` chip becomes
`<x-kds.modifier-badge>`, with an `is_array()` guard so a legacy string entry
still renders), `resources/views/kds/index.blade.php`
(`family=Public+Sans:wght@800` added to the existing fonts `<link>`;
`@include('kds._modifier-badge-assets')` between `</style>` and `#kds-root`;
`.group__list` gap 2px → 8px, `.item` padding `8px 4px 8px 0` → `10px 4px 16px 0`,
`.item__main` gap `6px 8px` → `12px 10px`, `.item__mods` gap `5px 6px` → `10px 12px`,
plus `.item--done .mb` / `.item--done .mb__label`; `modifiersHtml()` now
normalises through a new `normaliseMods()` — `{label,kind}`, bare string, and the
legacy POS key/value object — and maps through `kdsModifierBadgeHtml()`).
Check output (server render of `_item.blade.php` for live order 39147, a Latte
with ON ICE + Oat):
```
<span class="item__mods">
  <span class="mb mb--f-ice mb--ice"><svg class="mb__bg" …><use href="#mb-shape-ice"/></svg><svg class="mb__deco" style="width:0.95em;…;transform:rotate(18deg)" …><use href="#mb-deco-cube"/></svg><svg class="mb__deco" style="…rotate(-16deg)" …><use href="#mb-deco-cube-plain"/></svg><span class="mb__label">ON ICE</span></span>
  <span class="mb mb--f-milk mb--oat"><svg class="mb__bg" …><use href="#mb-shape-milk"/></svg>…3 dots…<span class="mb__label">Oat</span></span>
</span>
```
```
Public Sans link: yes
include present:  yes
old chip JS gone: yes
```
Visual/SSE confirmation is Verification 4.

### 5. Badge picker and live previews on /coffee/metadata — done
Changed: `app/Http/Controllers/CoffeeMetadataController.php` (`index()` passes
`badgeKinds`; `badge_kind` validated with
`Rule::in(array_keys(CoffeeProductMetadata::BADGE_KINDS))` in `update()` and
`store()`; added to `update()`'s `only([...])`; `store()`'s
`create($request->all())` replaced with an explicit `only([...])`),
`resources/views/coffee/metadata.blade.php` (Badge column between Group and
Order with `<select id="badge_kind_{id}">` — "Plain chip" plus one `<optgroup>`
per family — beside a server-rendered `<span id="badge_preview_{id}">`;
`previewBadge(id)` on the select's `onchange` and the short-name `oninput`;
`updateMetadata()` sends `badge_kind: value || null`; modal gains
`<select x-model="badgeKind">`, `badgeKind` in state / `open()` / `submit()`,
and its preview span is now
`x-html="kdsModifierBadgeHtml(badgeKind, shortName.trim() || '…')"`;
`.kds-preview .item__mod` styled like the KDS chip; option help text extended;
Geist + Public Sans `<link>` and `@include('kds._modifier-badge-assets')` after
the existing `<style>`). The optgroups come from a `$badgeKindsByGroup` built once
at the top of the view with `groupBy('group', preserveKeys: true)`, which keeps
the `BADGE_KINDS` order (Temperature, Milk, Syrups, Espresso).
No badge column on the coffee-types table.
Check output (server render of the real page as a logged-in user; 21 option rows
of which 9 are badged):
```
bytes: 346326
mb-sprite                  2
kdsModifierBadgeHtml       4
Public+Sans                1
previewBadge(              43   (21 rows x 2 handlers + the definition)
optgroup label="Milk"      22   (21 row selects + the modal select)
badge_kind_                23
Plain chip                 23
create_badge_kind          2
mb--oat                    2    (1 badge + 1 CSS rule)
mb--f-milk                 4    (oat/almond/coconut + 1 CSS rule)
mb--caramel / mb--ice / mb--decaf / mb--shot   2 each
item__mod                  16
```
Interactive confirmation is Verification 4.

### 6. Tests — done
Changed: `tests/Feature/KdsModifierBadgeTest.php` (new). `RefreshDatabase`, POS
`:memory:` with `PRODUCTS(ID, NAME, DISPLAY, CATEGORY)` seeded with one
category-081 product so the "missing metadata" panel has something to list,
`admin()` and `barista()` helpers (the latter granted `kds.access` explicitly —
`Role::hasPermission()` has no admin bypass), and a `seedOrder()` fixture of
Latte (native `['milk' => 'Whole']`) + Oat (`badge_kind` `oat`) + Takeaway (no
badge). The fixture does not set `kind` on the items because the column defaults
to `drink`, which is what makes the options fold.
Check output:
```
$ php artisan test --filter=KdsModifierBadgeTest
   PASS  Tests\Feature\KdsModifierBadgeTest
  ✓ badge kind is validated on store and update                          2.40s
  ✓ card items carry badge kind for folded options                       0.12s
  ✓ kds page renders badge and plain chip                                0.21s
  ✓ metadata page shows badge picker                                     0.12s
  ✓ component falls back to plain chip for unknown kind                  0.03s

  Tests:    5 passed (29 assertions)
```

### 7. Docs — done
Changed: `docs/features/kds-coffee-system.md` (new "### Modifier badges"
subsection after "Managing Metadata": the twelve kinds in a family table, that
the label is the short name, that "Plain chip" is the fallback for service
options, what the migration backfilled, the design project and component, the
two render paths with the lockstep warning, and the `card_items` modifier shape.
"Managing Metadata" gained a bullet pointing at it),
`docs/FEATURES_INDEX.md` (one "Modifier Badges" bullet in the Coffee KDS block).
Check output:
```
$ grep -n "Modifier badges" docs/features/kds-coffee-system.md
166:### Modifier badges

$ grep -n -i "badge" docs/FEATURES_INDEX.md   # KDS section hit
941:- **Modifier Badges**: Per-modifier shapes and colours on the card (ice cube,
     milk puddle, syrup drip, espresso crema), chosen per option on `/coffee/metadata`
```
(The other `badge` hits in FEATURES_INDEX.md are pre-existing and in unrelated
sections.)

## Deviations

1. **Step 3, `<symbol>` needed `overflow="visible"`.** The design's shapes sit
   in plain `<svg>` elements with `overflow:visible`; the milk path
   (`…C120 32 106 40 88 37 C72 42…`) overshoots its own `viewBox="0 0 120 40"`
   and relies on that. A `<symbol>` establishes its own viewport and clips at
   the viewBox, so the puddle came out shaved. Every symbol now carries
   `overflow="visible"` as a presentation attribute (attributes are cloned into
   the `<use>` shadow tree; a document CSS rule is not reliably applied there),
   with a `#mb-sprite symbol` CSS rule as belt and braces. No visual difference
   from the design — this is what makes it match.

2. **Step 3, `--mb-dash` for `shot` is `none`, not `0`.** The plan suggested
   `0`; `renderVals()` in the design uses the string `none`. Used `none` so the
   two are identical.

3. **Step 3, milk decorations need two symbols and a shared geometry const.**
   The design's three milk circles are `r=8.5 stroke-width=2` once and
   `r=8 stroke-width=2.5` twice, which one `mb-deco-dot` symbol cannot express —
   so `mb-deco-dot-a` and `mb-deco-dot-b`. Separately, the plan asks for the
   deco offsets to be "inline `style` on each deco `<svg>` so the JS and the
   Blade component can share the exact same strings". Rather than copy those
   strings into two files, they live in a new
   `CoffeeProductMetadata::BADGE_DECOS` const that both paths read. Same
   rendered markup, but the sharing is enforced rather than hoped for. This is
   the only addition to the model beyond `BADGE_KINDS`.

4. **Step 5, `.item__mod` had to be styled page-wide, not just inside
   `.kds-preview`.** The plan says "add `.kds-preview .item__mod { … }` or switch
   the class". I did the former and the browser check caught it: the *table*
   previews (`#badge_preview_{id}`) sit outside `.kds-preview`, so every
   plain-chip row rendered as unstyled black text. The rule is now a page-level
   `.item__mod` with literal colours (`--kp-accent` is scoped to `.kds-preview`),
   which covers the table and the modal. `.kds-preview__mod` is untouched.

5. **Verification 4 could not use a 390px viewport.** Chrome would not size its
   inner width below 500px on this box. Constrained `#kds-root` to 390px instead
   and measured there — see Verification 4 for what that showed.

Nothing else differs. Step 1's backfill list, Step 2's one-query rule, Step 4's
spacing numbers, Step 6's five tests and Step 7's doc sections are as written.

## Verification

1. `./vendor/bin/pint --test` on the five PHP files → **pass**
   ```
   PASS ........................................................... 5 files
   ```

2. `php artisan test --filter='KdsModifierBadgeTest|CustomerRequestTest|ProductSearchApiTest'` → **all pass**
   ```
   Tests:    37 passed (261 assertions)
   ```

3. `php artisan test` → **no new failures**
   ```
   Tests:    17 failed, 392 passed (1584 assertions)
   ```
   The same 17 the previous task recorded (`UdeaScrapingServiceTest` ×7,
   `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
   `TestScraperControllerTest` ×1, `WasteLogTest` ×2). Passing count went
   387 → 392, which is exactly the five new tests. None of the failures touch
   KDS or coffee metadata.

4. **Browser, logged in, against the live data.** Test order created with the
   plan's tinker snippet (`BADGE_TEST`, Latte + ON ICE + Oat + Caramel +
   Extra Shot + Takeaway); `card_items` folded all five onto the Latte with
   kinds `ice, oat, caramel, shot, null`.

   - **/kds desktop** — all five render as the design intends: pale-blue ice
     cube with its two floating cubes and white highlights, cream Oat puddle
     with three droplets, orange Caramel pill with the drop falling below it,
     dark Extra Shot with crema, and Takeaway as the plain orange chip. So
     `var()` does inherit into the `<use>` shadow tree (the first risk in the
     plan), and the milk puddle is not clipped (Deviations #1).
   - **Two render paths agree.** Rather than complete a real order to force an
     SSE refresh, I compared them directly in the page: for each of the five
     modifiers, the server-rendered node and
     `kdsModifierBadgeHtml(kind, label)` normalised through the DOM are
     **identical** (5/5). Then I repainted the card's `.item__mods` from the
     live `/kds/orders` payload through `modifiersHtml()` and re-zoomed — the
     result is pixel-identical to first paint.
     (A first comparison reported a mismatch; that was my method, not the
     markup — `outerHTML` re-serialises `<use …/>` as `<use …></use>`, a
     20-character difference across an Oat badge's four `<use>` elements.
     Comparing both sides through the DOM resolves it.)
   - **Done state** — tapping the line dims all four badges to 0.45 and strikes
     through their labels, matching the plain chip.
   - **Narrow width** — Chrome would not go below a 500px inner width, so
     `#kds-root` was constrained to 390px. Badges wrap to two rows, **no
     horizontal scroll** on the page or inside the card, and the Caramel drip
     clears the green "Complete order" bar with room to spare.
   - **/coffee/metadata** — 21 badge pickers, 9 rendered badges, 12 plain chips.
     Milk group shows Oat/Almond/Coconut as puddles and Alt Milk as a plain
     chip; Syrups show Caramel/Hazel/Van as dripping pills. Switching Alt Milk
     to Whole updated the preview instantly (`mb mb--f-milk mb--whole`,
     label "Alt Milk"), Update persisted it (`badge_kind = 'whole'` in the DB)
     and it came back selected after a reload with the server-rendered preview
     matching. **Restored to Plain chip afterwards**, since the plan's Context
     lists Alt Milk as `null`; confirmed `NULL` in the DB.
   - **Add Metadata modal** — opened on the one product missing metadata
     ("Happy Hour Coffee & Sausage Roll"), switched to Option, picked Vanilla,
     typed a short name: the KDS preview shows the dripping syrup pill labelled
     "Vanilla" on the sample drink, and the help text carries the new sentence.
     Cancelled without creating anything.
   - **Cleanup** — `BADGE_TEST` order and its 6 items deleted, 0 orphan items
     left, browser tab closed.

5. `git status --short` — only this plan's files plus the pre-existing dirty ones.

## Files changed

`[pre-existing]` = dirty before I started, not mine.

```
 M app/Http/Controllers/CoffeeMetadataController.php          [pre-existing, + my badge_kind changes]
 M app/Models/CoffeeProductMetadata.php
 M app/Models/KdsOrder.php
 M docs/FEATURES_INDEX.md
 M docs/features/kds-coffee-system.md
 M docs/features/product-search.md                            [pre-existing]
 M docs/planImp/implemented.md
 M docs/planImp/plan.md                                       [pre-existing / Planner's]
 M resources/views/coffee/metadata.blade.php                  [pre-existing, + my badge picker]
 M resources/views/components/product-search.blade.php        [pre-existing]
 M resources/views/kds/_item.blade.php
 M resources/views/kds/index.blade.php
?? database/migrations/2026_09_23_000001_add_badge_kind_to_coffee_product_metadata.php
?? resources/views/components/kds/                            [modifier-badge.blade.php]
?? resources/views/components/product-search/                 [pre-existing]
?? resources/views/kds/_modifier-badge-assets.blade.php
?? tests/Feature/KdsModifierBadgeTest.php
```

Nothing committed, pushed or deployed. No writes to the POS database. The
primary (MySQL) DB got the new nullable column, the nine backfilled rows, and
the Alt Milk round-trip described above, which was restored.

## Notes for Planner

- **Deviation #4 is the one worth your eye.** It is a real bug the plan's
  wording would have shipped: scoping the plain-chip rule to `.kds-preview`
  leaves the twelve unbadged rows in the *options table* rendering as bare
  black text. Only the browser step caught it — every automated check passed
  with it broken, because the markup was right and only the CSS was missing.
  Worth remembering that `assertSee('<span class="item__mod">…')` proves the
  chip is emitted, not that it looks like a chip.

- **The 3-digit-ish question again, in a new place.** At 390px the modifiers
  wrap to two rows and the Caramel drip, which hangs ~1.35em below its badge,
  lands in the row gap beside the badge below it rather than under empty space.
  It reads fine (I looked), but if a future order has a syrup badge directly
  above another badge the drip will overlap that badge's top edge. The fix
  would be a larger row gap on `.item__mods` for syrup-bearing rows, which
  needs a rule I did not want to invent. Flagging rather than acting.

- **`.item__mods` is a `<span>` server-side and a `<div>` in the JS path.**
  Pre-existing (`_item.blade.php` vs `modifiersHtml()`), untouched by this task,
  and harmless since both are `display:inline-flex`. Mentioning it because it
  briefly looked like a lockstep failure while I was verifying.

- **Badge size is fixed at 15px** (`--mb-fs`), between the old chip's 14px and
  the design's `md` 17px. The variable is there if you want to tune it per
  context; the plan put `size` out of scope so I did not expose it.

- **`whole`, `soy` and `mocha` have no live rows.** They exist because the plan
  fixes the enum to the design's twelve. The backfill patterns for them are in
  the migration and will do nothing on this data.

- **Possible follow-up:** the two render paths now share their geometry through
  `BADGE_KINDS`/`BADGE_DECOS`, but the wrapper markup is still written twice.
  A single PHP function returning the badge HTML, called by the component and
  serialised into the JS bundle, would close that gap entirely. Not worth it
  today; the comparison in Verification 4 is cheap to re-run if it drifts.

- **Still uncommitted in this tree from before this task:** the
  `x-product-search` hover-preview work (`components/product-search/`,
  `product-search.blade.php`, part of `docs/features/product-search.md`) and the
  metadata-page modal/preview work that this task built on. Untouched by me
  except where this plan required it.

- **The previous task was never archived.** `plan.md` was overwritten with this
  task while the product-search pair was still `READY`/`DONE` with an empty
  Review section. Its report is at
  `git show 828903a2:docs/planImp/implemented.md` and a copy of the
  working-tree version (with the hover-preview note) is in this session's
  scratchpad as `implemented-product-search-PREVIOUS.md`. If you want it
  archived properly, it will have to come from one of those.
