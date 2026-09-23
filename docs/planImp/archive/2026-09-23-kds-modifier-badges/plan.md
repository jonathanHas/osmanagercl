# KDS modifier badges: per-kind shapes on /kds, chosen on /coffee/metadata

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-23

## Goal

On `/kds` every option folded into a drink line is today the same orange
`item__mod` chip, so a barista has to read each one. The Claude Design project
"KDS Modifier Icons" (`ModifierBadge.dc.html`, project
`47420d2a-5362-4f13-bc11-44b10205f5bc`) gives each modifier family its own
shape and colour: pale-blue ice cube for ON ICE, a tinted milk puddle for milks,
a dripping pill for syrups, an espresso cup with crema for shots (dashed
outline = decaf). After this task an option row on `/coffee/metadata` carries a
"Badge" choice (one of the design's twelve kinds, or none), the KDS card renders
that badge with the option's short name as the label, and the metadata page
previews the badge live in both the options table and the "Add Metadata" modal.
Options with no badge keep the existing plain chip, so nothing regresses for
Service/Takeaway-type options that have no design counterpart.

## Context

### The design (read from the project via DesignSync)

`ModifierBadge.dc.html` is a single inline-flex `<span>` (height `1.9em`,
padding `0 0.8em`, font Public Sans 800, `white-space: nowrap`,
`isolation: isolate`) with one absolutely-positioned background SVG per family
at `z-index:-1` and an inner label `<span>`. Props: `kind` (enum), `label`,
`size` (`sm` 14px / `md` 17px / `lg` 24px), `deco` (bool, default true).
Kind → family and colours, copied exactly from `renderVals()`:

| kind | family | fill | stroke | ink | extra |
|---|---|---|---|---|---|
| `ice` | ice | `#dcf0fb` | `#7fbfe3` | `#0c4867` | bottom band `#c3e3f5`, white highlight strokes |
| `oat` | milk | `#f6eedb` | `#d6c49c` | `#5e4722` | |
| `almond` | milk | `#f5e6d6` | `#d4b394` | `#6a3f1e` | |
| `soy` | milk | `#f8f5e4` | `#cfc79a` | `#57501d` | |
| `coconut` | milk | `#ffffff` | `#c9c3b8` | `#3d3a35` | |
| `whole` | milk | `#ffffff` | `#b9c3cc` | `#2c3844` | |
| `caramel` | syrup | `#dd963f` | `#b06d1c` | `#3a1d04` | |
| `vanilla` | syrup | `#f4e3ad` | `#cdb065` | `#54400c` | |
| `hazelnut` | syrup | `#8a5733` | `#6a3f20` | `#ffffff` | |
| `mocha` | syrup | `#4a2c1c` | `#2f1b10` | `#ffffff` | |
| `shot` | shot | `#3a2416` | `#24150c` | `#ffffff` | crema `#c98c55`, dash `none` |
| `decaf` | shot | `#f1e8de` | `#6b4a33` | `#4a2f1c` | crema `#e3cdb3`, dash `5 3` |

Family SVGs (viewBox, position relative to the span, paths) — reproduce these
verbatim; the paths are the design:

- **ice** `viewBox="0 0 120 40" preserveAspectRatio="none"`, at
  `left:-0.15em; top:-0.1em; width:calc(100% + 0.3em); height:calc(100% + 0.2em)`:
  `<rect x="1" y="1" width="118" height="38" rx="7" fill stroke stroke-width="1.5" vector-effect="non-scaling-stroke"/>`,
  `<path d="M1 29 H119 V32 Q119 39 112 39 H8 Q1 39 1 32Z" fill="#c3e3f5"/>`,
  `<path d="M8 7 H34" stroke="#ffffff" stroke-width="3" stroke-linecap="round" vector-effect="non-scaling-stroke"/>`,
  `<path d="M8 13 V20" stroke="#ffffff" stroke-width="3" stroke-linecap="round" vector-effect="non-scaling-stroke" opacity="0.8"/>`.
  Deco: a `20×20` cube (`rect x=1.5 y=1.5 w=17 h=17 rx=4 fill=#eaf7fe stroke=#7fbfe3 sw=1.5` + `path M5 5.5 H10` white sw 2 round) at
  `width:0.95em; height:0.95em; top:-0.5em; right:-0.4em; rotate(18deg)` and a
  second cube (rect only, sw 1.8) at `width:0.65em; height:0.65em; bottom:-0.35em; left:-0.35em; rotate(-16deg)`.
  Label `letter-spacing:0.02em`.
- **milk** `viewBox="0 0 120 40" preserveAspectRatio="none"`, at
  `left:-0.3em; top:-0.2em; width:calc(100% + 0.6em); height:calc(100% + 0.4em)`:
  `<path d="M9 21 C3 10 18 2 36 5 C52 0 78 3 94 4 C111 5 119 13 115 21 C120 32 106 40 88 37 C72 42 46 39 30 38 C13 41 2 32 9 21Z" fill stroke stroke-width="1.5" vector-effect="non-scaling-stroke"/>`,
  `<ellipse cx="30" cy="11" rx="13" ry="2.6" fill="#ffffff" opacity="0.85"/>`.
  Deco: three circles (`cx=10 cy=10`, fill/stroke = the kind's) at
  `r=8.5 sw=2, width/height 0.42em, right:-0.62em; bottom:0.05em`,
  `r=8 sw=2.5, 0.24em, right:-0.78em; bottom:0.52em`,
  `r=8 sw=2.5, 0.28em, left:-0.5em; top:-0.2em`.
- **syrup** `viewBox="0 0 120 46" preserveAspectRatio="none"`, at
  `left:-0.1em; top:-0.1em; width:calc(100% + 0.2em); height:calc(100% + 0.8em)`:
  `<path d="M10 2 H110 Q118 2 118 10 V28 Q118 36 110 36 H92 Q89 36 89 39 V40 Q89 44 85.5 44 Q82 44 82 40 V39 Q82 36 79 36 H36 Q33 36 33 39 V41 Q33 46 28.5 46 Q24 46 24 41 V39 Q24 36 21 36 H10 Q2 36 2 28 V10 Q2 2 10 2Z" fill stroke stroke-width="1.5" vector-effect="non-scaling-stroke"/>`,
  `<path d="M10 8 H48" stroke="#ffffff" stroke-width="3" stroke-linecap="round" opacity="0.5" vector-effect="non-scaling-stroke"/>`.
  Deco: a falling drop `viewBox="0 0 10 14"`,
  `<path d="M5 0.8 C6.5 3.5 9.2 6.5 9.2 9.2 A4.2 4.2 0 0 1 0.8 9.2 C0.8 6.5 3.5 3.5 5 0.8Z" fill stroke stroke-width="1"/>`
  at `width:0.34em; height:0.48em; right:calc(29% - 0.17em); bottom:-1.35em`.
- **shot** `viewBox="0 0 120 40" preserveAspectRatio="none"`, at
  `left:-0.1em; top:-0.1em; width:calc(100% + 0.2em); height:calc(100% + 0.2em)`:
  `<rect x="1" y="1" width="118" height="38" rx="10" fill stroke stroke-width="1.5" stroke-dasharray vector-effect="non-scaling-stroke"/>`,
  `<path d="M1.5 11 Q1.5 1.5 11 1.5 H109 Q118.5 1.5 118.5 11 C102 14 92 8 76 11 C60 14 46 8 30 11 C18 13 10 9 1.5 11Z" fill=crema/>`.
  No deco. Label `letter-spacing:0.02em`.

`support.js` is the generic design-component runtime (React shim, template
parser). Nothing in it is ported. `KDS Modifier Icons.dc.html` is the showcase:
it places badges inline after the drink name on a card with `gap: 14px 12px`
in the row and `22px` between items — the badges need more vertical room than
the current chips (see Step 5).

### The app today

- **Chips come from metadata.** `KdsOrder::getCardItemsAttribute()`
  (`app/Models/KdsOrder.php:163`) folds any item whose
  `coffee_product_metadata.type` is `option` into the previous drink's
  `modifiers` as a plain string (`short_name`, falling back to
  `display_name`). Native POS `ATTRIBUTES` modifiers on the drink line itself
  are also flattened to strings. This array is what the server-rendered card
  (`resources/views/kds/_item.blade.php`) and the JSON payloads
  (`KdsController::getOrders()` and `stream()`, both `'items' => $order->card_items`)
  carry. The JS re-renderer `modifiersHtml()` in
  `resources/views/kds/index.blade.php:804` still tolerates the old
  key/value object shape; keep that tolerance.
- **Two render paths, already duplicated by design.** Server first paint uses
  `_item.blade.php`; every SSE/poll update rebuilds cards from JSON via
  `itemHtml()` in `kds/index.blade.php`. The chip markup exists in both. This
  task keeps that structure but makes the badge markup a single JS function
  plus a single Blade component, both fed from one PHP constant.
- **Metadata page** (`resources/views/coffee/metadata.blade.php`,
  `CoffeeMetadataController`) has uncommitted work in the tree on
  `828903a2`: an Alpine modal replacing the old `prompt()` flow, a live "KDS
  preview" that copies the `.item__mod` chip CSS as `.kds-preview__mod`, a
  `<datalist>` of group names, and `kds_name`/`sampleDrinkName` from the
  controller. **Treat that uncommitted work as the baseline** and build on it;
  do not revert it. Table rows are updated per row by `updateMetadata(id)`
  reading inputs with ids `short_name_{id}`, `type_{id}`, `group_name_{id}`,
  `display_order_{id}`, `is_active_{id}` and sending `PUT /coffee/metadata/{id}`.
- **Schema.** `coffee_product_metadata` (migration
  `2025_08_16_000001`): `product_id` (unique), `product_name`, `type` enum
  coffee|option, `short_name` (20), `group_name` (50, nullable),
  `display_order`, `is_active`. No styling column. Primary DB is MySQL live,
  SQLite `:memory:` under PHPUnit (`phpunit.xml`), so the migration must work on
  both.
- **Live option rows** (2026-09-23) and the badge they should get after
  backfill: ON ICE→`ice`; Oat (`Milk Alternative OAT`)→`oat`; Almond→`almond`;
  Coconut→`coconut`; Caramel (`Syrup Caramel`)→`caramel`; Van (`Syrup
  Vanilla`)→`vanilla`; Hazel (`Syrup Hazelnut`)→`hazelnut`; Extra Shot
  (`Espresso Extra Shot`)→`shot`; Decaf→`decaf`. Everything else (Extra Hot,
  Hot Milk, Small Cup, Cocoa, Singl, Alt Milk, Takeaway, 2Go Cup, 2Go Return,
  Cup Disc, Sit In, Syrup) stays `null` = plain chip. 9 rows backfilled.
- **Fonts.** `/kds` loads Geist + Geist Mono from Google Fonts in the view. The
  metadata page loads nothing (admin layout only). The design's label font is
  Public Sans 800.
- **Auth.** `/coffee/*` sits in the `auth` group with no permission;
  `/kds/*` requires `permission:kds.access`. Tests grant permissions by
  creating a `Role`, `Permission::firstOrCreate([...])`, `givePermissionTo`,
  then `User::factory()->create(['role_id' => ...])`; copy the helpers in
  `tests/Feature/CustomerRequestTest.php:62-85`. `Role::hasPermission()` has no
  admin bypass, so grant `kds.access` explicitly.
- **POS in tests.** `CoffeeMetadataController::index()` queries
  `pos.PRODUCTS` (`ID`, `NAME`, `DISPLAY`, `CATEGORY`), and
  `KdsController::index()` only opens the POS PDO. Under PHPUnit POS is SQLite
  `:memory:`; a test that hits `/coffee/metadata` must create `PRODUCTS` the
  way `CustomerRequestTest::setUp()` does (add a nullable `DISPLAY` column).
- **No existing tests** cover coffee metadata or the KDS views.
- `php artisan kds:test-orders` creates orders whose items have
  `product_id` `TEST_PRODUCT_n`, which match no metadata, so it is useless for
  seeing badges. Verification step 4 gives a tinker snippet instead.

## Constraints

- **Badge kinds are the design's twelve, exactly**, with the design's colours
  and paths. No extra kinds, no renamed kinds; the enum is the contract with
  the design project so a future re-import lines up.
- **Label is always the option's `short_name`** (the design's default labels
  such as "ON ICE"/"EXTRA SHOT" are not used; the shop already chose short
  names). Max 20 chars, existing rule.
- **No badge → existing chip, unchanged.** Options without a kind, native POS
  attribute modifiers, and metadata-less options render the current
  `.item__mod` exactly as today.
- **JSON shape change is deliberate and contained:** `card_items[*].modifiers`
  goes from `string[]` to `{label: string, kind: string|null}[]`. The only
  consumers are `_item.blade.php` and `modifiersHtml()`; both are in this plan.
  `compact_display`, `grouped_items`, `CoffeeOrderGroupingService` and the SSE
  signature are untouched.
- **KDS render cost:** `getCardItemsAttribute()` already does two
  `whereIn` plucks per order; fold the new column into those (one pluck of
  `badge_kind`, or one `get()` of the three columns), never a per-item query.
  The SSE loop calls this every second per active order.
- Blade/CSS/SVG only; no Vite/npm changes. Alpine is already loaded by the admin
  layout.
- Do not commit, push or deploy. Run `./vendor/bin/pint` on changed PHP files.

## Out of scope

- The design's `size` and `deco` props as runtime options. One size on the
  card (see Step 3), decorations always on.
- Badges for coffee types (drink lines). Only option-typed rows get a badge
  field.
- Any change to `CoffeeOrderGroupingService`, `compact_display`, the completed
  panel, or the KDS order/status workflow.
- Editing badges anywhere other than `/coffee/metadata`.
- A dark-mode variant of the badge (the KDS card is always the light beige
  theme; the metadata previews sit on a white `.kds-preview` panel).
- Touching the uncommitted `x-product-search` hover work also present in the
  tree.

## Steps

### 1. Column, model constant, backfill
Files: `database/migrations/2026_09_23_000001_add_badge_kind_to_coffee_product_metadata.php` (new),
`app/Models/CoffeeProductMetadata.php`

What:
- Migration `up()`: `$table->string('badge_kind', 20)->nullable()->after('group_name')`,
  then backfill in PHP (not SQL, so it is engine-neutral): for every row with
  `type = 'option'` and `badge_kind` null, match `product_name`
  case-insensitively in this order and set the first hit:
  `decaf`→`decaf`, `extra shot`→`shot`, `oat`→`oat`, `almond`→`almond`,
  `soy`/`soya`→`soy`, `coconut`→`coconut`, `caramel`→`caramel`,
  `vanilla`→`vanilla`, `hazelnut`→`hazelnut`, `mocha`→`mocha`,
  `/\bice\b/i`→`ice`. `down()` drops the column.
- Model: add `badge_kind` to `$fillable`. Add
  ```php
  /** kind => [family, group label shown in the picker] — the twelve kinds of ModifierBadge.dc.html */
  public const BADGE_KINDS = [
      'ice' => ['family' => 'ice', 'group' => 'Temperature'],
      'oat' => ['family' => 'milk', 'group' => 'Milk'], 'almond' => ..., 'soy' => ..., 'coconut' => ..., 'whole' => ...,
      'caramel' => ['family' => 'syrup', 'group' => 'Syrups'], 'vanilla' => ..., 'hazelnut' => ..., 'mocha' => ...,
      'shot' => ['family' => 'shot', 'group' => 'Espresso'], 'decaf' => ...,
  ];
  public static function badgeFamily(?string $kind): ?string  // null for null/unknown
  ```
  Keep insertion order as listed (it is the picker order).

Check: `php artisan migrate` runs clean; then
`php artisan tinker --execute='foreach (App\Models\CoffeeProductMetadata::where("type","option")->orderBy("group_name")->get() as $m) echo str_pad($m->short_name,12)," => ",$m->badge_kind ?? "null","\n";'`
shows exactly the nine mappings listed in Context ("Live option rows") and
`null` for the rest.

### 2. Carry the kind through `card_items`
Files: `app/Models/KdsOrder.php` (`getCardItemsAttribute()`)

What: replace the two plucks with one
`CoffeeProductMetadata::whereIn('product_id', $productIds)->get(['product_id','type','short_name','badge_kind'])->keyBy('product_id')`.
Each modifier becomes `['label' => (string) $label, 'kind' => $meta?->badge_kind]`.
Native POS attribute values become `['label' => $v, 'kind' => null]`. Update
the docblock above the method. Nothing else in the returned array changes.

Check: `php artisan tinker --execute='$o = App\Models\KdsOrder::with("items")->latest()->first(); print_r($o?->card_items);'`
prints `modifiers` entries as `[label => ..., kind => ...]` arrays (or an
empty array if no order has options; the Step 6 tests cover the shape).

### 3. Shared badge assets: CSS, SVG sprite, JS renderer, Blade component
Files: `resources/views/kds/_modifier-badge-assets.blade.php` (new),
`resources/views/components/kds/modifier-badge.blade.php` (new)

What — the assets partial, included once per page by Steps 4 and 5:
- A `<style>` block for `.mb` (the design span: `position:relative;
  display:inline-flex; align-items:center; justify-content:center; height:1.9em;
  padding:0 0.8em; font-family:'Public Sans','Geist',system-ui,sans-serif;
  font-weight:800; font-size:var(--mb-fs,15px); line-height:1;
  white-space:nowrap; isolation:isolate; color:var(--mb-ink)`), `.mb__bg` and
  `.mb__deco` (`position:absolute; z-index:-1; overflow:visible;
  pointer-events:none`), per-family placement classes `.mb--f-ice`,
  `.mb--f-milk`, `.mb--f-syrup`, `.mb--f-shot` setting the `.mb__bg` offsets
  from Context, and per-kind colour classes `.mb--oat { --mb-fill:#f6eedb;
  --mb-stroke:#d6c49c; --mb-ink:#5e4722 }` etc. for all twelve, with
  `--mb-crema` and `--mb-dash` on `shot`/`decaf` (`--mb-dash: 0` for shot,
  `5 3` for decaf). Ice and shot labels get `letter-spacing:0.02em`.
- A hidden `<svg width="0" height="0" style="position:absolute" aria-hidden="true">`
  sprite with `<symbol>`s `mb-shape-ice`, `mb-shape-milk`, `mb-shape-syrup`,
  `mb-shape-shot` (each with the design's `viewBox` and
  `preserveAspectRatio="none"`) and deco symbols `mb-deco-cube`,
  `mb-deco-cube-plain`, `mb-deco-dot`, `mb-deco-drop`. Inside the symbols use
  CSS variables rather than attributes for the parametrised colours:
  `style="fill:var(--mb-fill);stroke:var(--mb-stroke)"`,
  `style="fill:var(--mb-crema)"`, `style="stroke-dasharray:var(--mb-dash)"`.
  Custom properties inherit into `<use>` shadow trees, which is what makes one
  sprite serve all kinds. Fixed colours (ice band, white highlights, deco cube
  fill `#eaf7fe`) stay as attributes.
- A `<script>` defining
  `window.KDS_BADGE_KINDS = @js(\App\Models\CoffeeProductMetadata::BADGE_KINDS)`
  and `window.kdsModifierBadgeHtml(kind, label)`: returns the plain
  `<span class="item__mod">…</span>` when `kind` is falsy or not in
  `KDS_BADGE_KINDS`; otherwise
  ```html
  <span class="mb mb--f-{family} mb--{kind}">
    <svg class="mb__bg"><use href="#mb-shape-{family}"/></svg>
    {deco svgs for the family, each <svg class="mb__deco" style="...offsets..."><use href="#mb-deco-..."/></svg>}
    <span class="mb__label">{escaped label}</span>
  </span>
  ```
  Escape the label (copy the `escapeHtml` helper locally; do not depend on
  `kds/index.blade.php`'s one). Deco offsets per family are the ones in
  Context; put them in inline `style` on each deco `<svg>` so the JS and the
  Blade component can share the exact same strings.
- The Blade component `<x-kds.modifier-badge :kind="..." :label="..." />`
  (`@props(['kind' => null, 'label'])`) renders the identical markup server-side
  using `CoffeeProductMetadata::badgeFamily($kind)`; unknown/null kind renders
  `<span class="item__mod">{{ $label }}</span>`. Add a comment at the top of
  both files saying the other must be changed in lockstep.

Check: `php artisan tinker --execute='echo Illuminate\Support\Facades\Blade::render("<x-kds.modifier-badge kind=\"decaf\" label=\"Decaf\" />");'`
prints markup containing `mb--f-shot`, `mb--decaf`, `#mb-shape-shot` and
`>Decaf<`; with `kind="nope"` it prints `<span class="item__mod">Decaf</span>`.

### 4. Render badges on /kds
Files: `resources/views/kds/_item.blade.php`, `resources/views/kds/index.blade.php`

What:
- Font link: add `family=Public+Sans:wght@800` to the existing Google Fonts
  `<link>` (keep Geist families).
- `@include('kds._modifier-badge-assets')` once, right after the `<style>`
  block and before `<div class="kds" id="kds-root">`.
- `_item.blade.php`: `@foreach($item['modifiers'] as $m)` →
  `<x-kds.modifier-badge :kind="$m['kind'] ?? null" :label="$m['label'] ?? (string) $m" />`.
  The `?? (string) $m` guard keeps a legacy string working.
- `modifiersHtml(mods)`: normalise each entry to `{label, kind}` (string →
  `{label: s, kind: null}`; legacy `{k: v}` object → one entry per value with
  `kind: null`; object with `label` → as is) and map through
  `kdsModifierBadgeHtml(m.kind, m.label)`.
- Spacing so decorations and syrup drips do not collide (design context uses
  22px between items): `.item { padding: 10px 4px 16px 0 }`,
  `.group__list { gap: 8px }`, `.item__main { gap: 12px 10px }`,
  `.item__mods { gap: 10px 12px }`. Done state: add
  `.item--done .mb { opacity: 0.45 } .item--done .mb__label { text-decoration: line-through }`
  next to the existing `.item--done .item__mod` rule.

Check: with the Verification-4 order on screen, `/kds` shows Latte with a
blue ice cube "ON ICE", a cream puddle "Oat", an orange dripping "Caramel"
and a dark crema cup "Extra Shot", plus a plain orange "Takeaway" chip; tapping
the line dims them all; an SSE refresh (complete another order from a second
tab) re-renders them identically. No horizontal scroll at 390px width.

### 5. Badge picker and live previews on /coffee/metadata
Files: `resources/views/coffee/metadata.blade.php`,
`app/Http/Controllers/CoffeeMetadataController.php`

What:
- Controller `index()`: pass `'badgeKinds' => CoffeeProductMetadata::BADGE_KINDS`
  to the view. `update()` and `store()`: add
  `'badge_kind' => ['nullable', 'string', Rule::in(array_keys(CoffeeProductMetadata::BADGE_KINDS))]`
  and include `badge_kind` in `update()`'s `only([...])`. In `store()` replace
  `create($request->all())` with `create($request->only([...]))` listing the
  validated keys (this also stops arbitrary fields reaching the model).
- View head: the same Google Fonts `<link>` as `/kds` (Public Sans 800 + Geist
  400/600/700) and `@include('kds._modifier-badge-assets')` once, after the
  existing `<style>` block.
- Options table: new "Badge" column between Group and Order. Cell contents:
  a `<select id="badge_kind_{id}">` with `<option value="">Plain chip</option>`
  then one `<optgroup label="{group}">` per group in `$badgeKinds` order
  (Temperature, Milk, Syrups, Espresso) with `<option value="{kind}">` labelled
  by the capitalised kind, selected per row; beside it a
  `<span id="badge_preview_{id}">` rendered server-side with
  `<x-kds.modifier-badge :kind="$option->badge_kind" :label="$option->short_name" />`.
  Give the cell a 14px-ish base so the 15px badge sits comfortably; leave 12px
  top padding for the ice deco cube.
- JS: `updateMetadata(id)` reads `badge_kind_{id}` (when present) and sends
  `badge_kind: value || null`. Add a `previewBadge(id)` called from the
  select's `onchange` and the short-name input's `oninput` that sets
  `badge_preview_{id}.innerHTML = kdsModifierBadgeHtml(kind, shortName)`.
- Coffee-types table: no badge column (drinks never get one). When
  `handleTypeChange()` flips a row to `option`, nothing extra is needed: the
  row is re-rendered on reload and `updateMetadata` sends no `badge_kind`.
- Create modal: under the Group field (inside the `x-show="type === 'option'"`
  block) add a `<select x-model="badgeKind">` built from `@js($badgeKinds)`
  with the same "Plain chip" + optgroups. In the KDS preview, replace the
  `<span class="kds-preview__mod" x-text="...">` with
  `<span x-html="kdsModifierBadgeHtml(badgeKind, shortName.trim() || '…')"></span>`.
  Reset `badgeKind = ''` in `open()`, and send `badge_kind: this.badgeKind || null`
  in `submit()`. The `.kds-preview__mod` CSS can stay for the plain-chip case
  (rename is not required; `kdsModifierBadgeHtml` emits `item__mod`, so add
  `.kds-preview .item__mod { ...same rules as .kds-preview__mod... }` or switch
  the class — either, but the plain preview must still look like the KDS chip).
- Under the preview, extend the option-mode help text: "Pick a badge to give
  this option its own shape and colour on the KDS. Leave it as Plain chip for
  service options like Takeaway."

Check: on `/coffee/metadata`, the Milk group shows Oat/Almond/Coconut with
puddle badges and Alt Milk with a plain chip; changing Alt Milk's badge to
Whole updates the preview instantly and Update persists it (reload shows
Whole selected). Add Metadata on a missing product → Option → badge Vanilla →
the modal preview shows the dripping pill with the typed short name.

### 6. Tests
Files: `tests/Feature/KdsModifierBadgeTest.php` (new)

What (`RefreshDatabase`; POS `:memory:` with `PRODUCTS(ID, NAME, DISPLAY, CATEGORY)`
created in `setUp()` per `CustomerRequestTest`; helpers `admin()` and
`barista()` where barista's role has `kds.access`):
- `test_badge_kind_is_validated_on_store_and_update`: POST
  `/coffee/metadata` with `badge_kind: 'oat'` → 200 and row has `oat`; with
  `'purple'` → 422; PUT with `badge_kind: null` clears it.
- `test_card_items_carry_badge_kind_for_folded_options`: metadata Latte
  (`coffee`, pid `L`), Oat (`option`, `oat`, pid `O`), Takeaway (`option`,
  no badge, pid `T`); `KdsOrder` (status `new`, `order_time` now) with items
  L (native `modifiers` `['milk' => 'Whole']`), O, T. Assert
  `card_items[0]['modifiers'] === [['label'=>'Whole','kind'=>null],['label'=>'Oat','kind'=>'oat'],['label'=>'Takeaway','kind'=>null]]`
  and `count(card_items) === 1`.
- `test_kds_page_renders_badge_and_plain_chip`: same fixture, GET `/kds` as
  barista → 200, `assertSee('mb--oat', false)`, `assertSee('mb--f-milk', false)`,
  `assertSee('<span class="item__mod">Takeaway</span>', false)`; and
  `GET /kds/orders` JSON has `active.0.items.0.modifiers.1.kind === 'oat'`.
- `test_metadata_page_shows_badge_picker`: GET `/coffee/metadata` as admin →
  200, sees `badge_kind_{oatRow->id}`, sees `<option value="oat" selected`,
  and the missing-metadata product from POS `PRODUCTS` is listed.
- `test_component_falls_back_to_plain_chip_for_unknown_kind`: `Blade::render`
  with `kind="nope"` contains `class="item__mod"` and not `class="mb`.

Check: `php artisan test --filter=KdsModifierBadgeTest` → 5 pass.

### 7. Docs
Files: `docs/features/kds-coffee-system.md`, `docs/FEATURES_INDEX.md`

What: in `kds-coffee-system.md` under "Mobile Order Grouping → Managing
Metadata" add a "Modifier badges" subsection: the twelve kinds by family, that
the label is the short name, that "Plain chip" is the fallback, where the
design lives (Claude Design project `47420d2a-5362-4f13-bc11-44b10205f5bc`,
`ModifierBadge.dc.html`), and that `_modifier-badge-assets.blade.php` and
`components/kds/modifier-badge.blade.php` must change together. In
`FEATURES_INDEX.md` add one bullet under "Coffee KDS" for per-modifier badges.

Check: `grep -n "Modifier badges" docs/features/kds-coffee-system.md` hits;
`grep -n -i "badge" docs/FEATURES_INDEX.md` hits once in the KDS section.

## Verification

1. `./vendor/bin/pint --test app/Models/CoffeeProductMetadata.php app/Models/KdsOrder.php app/Http/Controllers/CoffeeMetadataController.php database/migrations/2026_09_23_000001_add_badge_kind_to_coffee_product_metadata.php tests/Feature/KdsModifierBadgeTest.php` → pass.
2. `php artisan test --filter='KdsModifierBadgeTest|CustomerRequestTest|ProductSearchApiTest'` → all pass.
3. `php artisan test` → the only failures are the 17 pre-existing ones
   (documented in the previous task's `implemented.md`; the two `ProductTest`
   ones among them). No new failures.
4. Live browser check. Create one order whose lines match real metadata:
   ```
   php artisan tinker --execute='
   $m = fn($s) => App\Models\CoffeeProductMetadata::where("short_name",$s)->firstOrFail();
   $o = App\Models\KdsOrder::create(["ticket_id"=>"BADGE_TEST","ticket_number"=>999001,"person"=>"TEST","status"=>"new","order_time"=>now()]);
   foreach ([["Latte","coffee"],["ON ICE","option"],["Oat","option"],["Caramel","option"],["Extra Shot","option"],["Takeaway","option"]] as [$s,$t]) {
     $r = $m($s); $o->items()->create(["product_id"=>$r->product_id,"product_name"=>$r->product_name,"display_name"=>$r->product_name,"quantity"=>1]);
   }
   echo "order ", $o->id, "\n";'
   ```
   (If "Latte" is not a coffee-type short name on this box, use the first row
   of `CoffeeProductMetadata::getCoffeeTypes()`.) Open `/kds` on a phone-width
   viewport and desktop: expected as in Step 4's check. Then
   `/coffee/metadata`: expected as in Step 5's check. Finally delete the test
   order: `App\Models\KdsOrder::where("ticket_id","BADGE_TEST")->first()?->items()->delete(); App\Models\KdsOrder::where("ticket_id","BADGE_TEST")->delete();`
5. `git status --short` shows only the files named in this plan plus the
   pre-existing dirty files from the baseline.

## Risks

- **`<use>` + CSS variables.** Custom properties do inherit into `<use>`
  shadow trees in current Chrome/Safari/Firefox, but `stroke-dasharray` must be
  set via `style=` (a CSS property), not the attribute, or `var()` is ignored.
  If a kind renders unfilled, that is the first thing to check.
- **Overflowing decorations.** The syrup drop hangs ~1.8em below the badge box
  and the ice cube ~0.5em above. `isolation:isolate` plus `z-index:-1` keeps
  them behind the label but they still need the extra `.item` padding from
  Step 4; check the last item in a card does not clip against the green CTA.
- **Font fallback.** If Public Sans fails to load, `'Geist'` 700 is the
  fallback; badges still work, just less heavy.
- **Payload shape.** Any other reader of `card_items[*].modifiers` would break;
  `grep -rn "modifiers" app resources` on 2026-09-23 shows only the two render
  paths in this plan and the test-order generator (which writes native
  key/value modifiers on items, still supported).
- **Two copies of the badge markup** (JS + Blade). The lockstep comment and
  the tests on both paths are the guard; a mismatch would show as a badge
  changing shape after the first SSE refresh.

## Review
Accepted on the user's instruction on 2026-09-23 after the Implementer reported DONE. Closed without a criterion-by-criterion Planner review of the diff.
