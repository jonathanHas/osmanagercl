# Zebra tab: show and set the country of origin

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-10-22

Side plan (see `planimp.md`): reported in `docs/shop_new/implemented-zebra-country.md`.
Folds in `docs/shop_new/findings/2026-10-22-zebra-country.md`, which moves into
this cycle's archive folder on acceptance.

## Goal

On `/shop/labels/zebra` the print card always shows a label's country of
origin and lets anyone who can print change it. Picking a country saves at
once: it rewrites the saved label's country text (when the label has one) and
the product's country of origin on the till, so the next print, the next
person, the office Zebra page and the F&V pages all agree. Labels whose
country is not printed as text (graphics-only labels, or a word the app does
not recognise such as "Holland") are marked with an icon in the list and on
the card, so staff know the printed label will not change and the office has
to fix it.

Owner decisions (2026-10-22): save to both the label and the till record;
every `labels.print` user may change it; a native `shop-select`; an icon on
anything like Rocket imported whose country is not visible or available.

## Context

- `App\Services\ZebraLabelListService::grouped()` builds the three Zebra
  groups for the office page and the Shop list. Per product-linked label it
  calls `ZebraLabel::findCountryField($fields, $countryNames)` (exact match
  of a text field against `countries.name`) and `$product->vegDetails?->country?->name`,
  and only records `mismatches.country` when both exist and differ. The
  country itself is not in the array. Standalone labels get `mismatches` null
  and no country lookup, although some (parsnip) carry a country in text.
- `LabelAreaController::zebraList()` maps those arrays to the Shop JSON
  (`kind`, `group`, `id`, `name`, `label`, `product_code`, sizes,
  `default_copies`, `mismatches`, `print_url`).
- `ZebraLabel::replaceTextFields($zpl, [index => text])` rewrites text fields
  by the same index `extractTextFields()` uses; the office
  `zebra-labels.update-fields` endpoint (`labels.manage`) and the per-print
  `fields` override both rely on it.
- Country of origin on the till is `vegDetails.countryCode` (POS connection)
  → `countries.id` (main DB). `App\Models\VegDetails::upsertForProduct($code, [...])`
  updates the row keyed on `product`, or creates one with `countryCode 1,
  classId 1, unitId 1` defaults. The office F&V manage page writes it through
  `FruitVegController::updateCountry()` (`fruit-veg.country.update`), which
  also queues an F&V shelf label reprint — not wanted here (a bagged line
  gets a Zebra label, not a shelf label), so this plan writes `VegDetails`
  directly. The F&V pages select products by category (SUB1–SUB3), not by
  whether a `vegDetails` row exists, so creating one changes nothing there.
- `App\Models\Country`: `id`, `name` (unique), `code`, `sort_order`; 37 rows
  on dev (Netherlands, not Holland). `Country::vegDetails()` exists.
- Dev data (2026-10-22): of 13 product-linked Zebra labels, 5 carry a country
  in text with no till record, 1 says "Holland" (undetected), 2 have no
  country text, and 9 (incl. standalone) have **no text fields at all**
  (graphics-only ZPL). Only 3 products have a `vegDetails` row.
- The Shop card (`resources/views/shop/zebra.blade.php`) currently has a
  "Country differs" box with a "Print with till country" switch; `zebra.js`
  has `useTillCountry`, `countryText`, and `overrides()` handles both price
  and country. The design file `docs/design/shop-mode/screen-07b-zebra-labels.html`
  shows that switch; the select replaces it (owner decision, not a design
  round).
- Existing Shop select markup (Order review): `<select class="shop-input shop-select" x-model="…">`.
  `.shop-field` / `.shop-field__label` exist. Icons: `help` is the sprite's
  question mark; `.shop-ico--sm` is 20 px. No globe/eye-off icon exists and
  the sprite must stay byte-identical to `docs/design/shop-mode/shop-icons.svg`.
- Tests: `tests/Feature/Shop/ShopZebraTest.php` (accepted Labels cycle,
  uncommitted) builds the POS tables in sqlite including `vegDetails`, aliases
  `mysql` to the test DB, fakes `ZebraPrintService`. The `countries` table is
  migrated (`2025_07_26_151328_create_countries_table`) but empty in tests.
- PIN sessions: every endpoint a Shop screen calls must be in
  `config('shop.pin_session_routes')` (`zebra-labels.print` and
  `labels.zebra-list` already are).
- Baseline: the accepted Labels cycle is in the working tree, uncommitted
  (`docs/shop_new/plan.md` ACCEPTED). HEAD `58842899`. `php artisan test`
  → 16 failed, all pre-existing (`UdeaScrapingServiceTest` ×7,
  `CashReconciliationTest` ×3, `FruitVegLabelPrintingTest` ×2, `ProductTest` ×2,
  `TestScraperControllerTest` ×1, `BackupConfigurationTest` ×1).

## Constraints

- Shop view contract (`docs/shop_new/README.md`): `x-shop.*` components and
  `shop-*` classes only, no `<style>`/`<script>`, URLs via `data-*` or JSON,
  never `route()` in JS. CSS additions go between the APP ADDITIONS markers
  of `resources/css/shop.css`, prefixed `.shop `; the design block stays
  byte-identical to `docs/design/shop-mode/shop.css`.
- The POS database is written only through existing paths: `vegDetails` via
  `VegDetails::upsertForProduct()`, as the F&V page does. Nothing else on POS.
- `zebra-labels.update-fields` stays `labels.manage`: it can rewrite any text
  on a label. The new endpoint may only change the detected country field,
  to a name from `countries`.
- The office Zebra page and F&V pages render exactly as before; extra keys in
  the `grouped()` arrays are fine (the office view JSON-encodes them into
  Alpine and ignores unknown keys).
- No migration. Do not commit.

## Out of scope

- Re-saving graphics-only labels or "Holland" with a text country field
  (office job, owner).
- A country select on the office Zebra page, or any change to
  `fixMismatch()` there.
- Translated labels (no country handling).
- Adding sprite symbols.

## Steps

### 1. Service: country per label, and `setCountry()`

`app/Services/ZebraLabelListService.php`.

Refactor the loop body of `grouped()` into a private
`labelData(ZebraLabel $label, ?Product $product, array $countryNames): array`
so one label can be recomputed; `grouped()` output must stay identical except
for the new `country` key. Add to every Zebra array (standalone too):

```php
'country' => [
    'label'       => ?string,  // detected country text, e.g. 'France'; null when none recognised
    'label_id'    => ?int,     // countries.id for that name
    'field_index' => ?int,     // index for replaceTextFields(); null when none recognised
    'till'        => ?string,  // $product->vegDetails?->country?->name
    'till_id'     => ?int,
],
```

Standalone labels: run `findCountryField()` on their fields too (`till` null).
Use one `Country::orderBy('name')->get(['id', 'name'])` per call for names
and ids, instead of the current `pluck('name')`.

Add:

```php
/**
 * Set a label's country of origin: the label's country text (when it has one)
 * and the product's till record (when it has a product). Returns the label's
 * refreshed list array (same shape as grouped() items).
 */
public function setCountry(ZebraLabel $label, Country $country): array
```

- If the label's detected `field_index` is not null:
  `ZebraLabel::replaceTextFields($label->zpl_content, [$index => $country->name])`,
  save `zpl_content`.
- If `$label->product_code` is set and `$label->product` exists:
  `VegDetails::upsertForProduct($label->product_code, ['countryCode' => $country->id])`.
- Return `labelData($label->fresh(), $label->product?->fresh(['vegDetails.country']) …)`
  — however it is done, the returned array must come from the same code path
  the list uses, with `mismatches` recomputed (after a save to both, the
  country mismatch is gone).

Check: `php artisan tinker` →
`app(ZebraLabelListService::class)->grouped()['onTill'][0]['country']` has the
five keys; for "French Beans 200g" `label` is `Spain`, `field_index` 3; for
"Rocket imported 100g" `label` is null (Holland); for "Kale Curly 300g" all
null. Do not call `setCountry()` on dev data outside a test.

### 2. JSON and endpoint

`app/Http/Controllers/LabelAreaController.php::zebraList()`: pass `country`
through for Zebra items and add
`'country_url' => route('zebra-labels.set-country', $label['id'])`. Translated
items get `'country' => null, 'country_url' => null`.

`app/Http/Controllers/ZebraLabelController.php`: add

```php
public function setCountry(Request $request, ZebraLabel $zebraLabel, ZebraLabelListService $list): JsonResponse
{
    $data = $request->validate(['country_id' => 'required|integer|exists:App\Models\Country,id']);
    $item = $list->setCountry($zebraLabel, Country::findOrFail($data['country_id']));

    return response()->json(['success' => true, 'label' => $item]);
}
```

(Thin; shape the `label` exactly as `zebraList()` would, i.e. reuse the same
mapping — extract `zebraList()`'s per-item closure into a small shared
helper or a method on the service so both produce one shape. The Implementer
chooses where it lives; the Shop module replaces its row with this object.)

`routes/web.php`, inside the `zebra-labels.` group after `update-fields`:
`Route::patch('/{zebraLabel}/country', [ZebraLabelController::class, 'setCountry'])->name('set-country')->middleware('permission:labels.print');`

`config/shop.php`: add `zebra-labels.set-country` to the allow-list next to
`zebra-labels.print`.

Check: `php artisan route:list --name=zebra-labels.set-country` → PATCH,
`labels.print`. `GET /labels/zebra/list` on dev: every `kind: zebra` item has
`country` (five keys) and `country_url`; translated items have both null.

### 3. Shop controller and view

`app/Http/Controllers/Shop/ZebraController.php`: add
`'countries' => Country::orderBy('name')->get(['id', 'name'])`.

`resources/views/shop/zebra.blade.php`:

- Remove the "Country differs" card (`selected?.mismatches?.country` block
  with the switch and its hint).
- After the price card (before the `shop-notice` stock line), add:

```blade
<div class="shop-field" x-show="selected?.kind === 'zebra'">
    <label class="shop-field__label" for="zebra-country">Country of origin</label>
    <select id="zebra-country" class="shop-input shop-select" x-model="countryId" :disabled="busy" @change="setCountry()">
        <option value="">Choose…</option>
        @foreach ($countries as $country)
            <option value="{{ $country->id }}">{{ $country->name }}</option>
        @endforeach
    </select>
    <p class="shop-notice" x-show="selected && ! selected.country?.field_index && selected.country?.field_index !== 0" x-cloak><x-shop.icon name="help" size="sm" />Not printed on this label. Fix the label in the office.</p>
    <p class="shop-hint" x-show="countryHint" x-text="countryHint"></p>
</div>
```

  (Careful: `field_index` can be `0`; test `field_index == null` in a
  getter — `noCountryText` — rather than inline, and use that getter in both
  places.)

- In the list row, mark labels with no recognised country text. The meta
  span uses `x-text`, so give it a child instead:

```blade
<span class="shop-row__meta shop-row__meta--icon">
    <x-shop.icon name="help" size="sm" x-show="noCountryText(r)" />
    <span x-text="rowMeta(r)"></span>
</span>
```

  with `aria-hidden` left as the component sets it, and a visually hidden
  `<span class="shop-sr-only" x-show="noCountryText(r)">Country not printed on this label</span>`
  inside the meta so screen readers get it (`shop-sr-only` exists in the
  design CSS).

Check: `php artisan test --filter=ShopViewContractTest` green;
`grep -c "shop-select" resources/views/shop/zebra.blade.php` → 1;
`grep -c "useTillCountry\|Print with till country" resources/views/shop/zebra.blade.php` → 0.

### 4. Shop module

`resources/js/shop/zebra.js`:

- Remove `useTillCountry`, `countryText`, and the country branch of
  `overrides()` (price only now). Update the header comment.
- Add state `countryId: ''`.
- `open(l)`: `this.countryId = String(l.country?.label_id ?? l.country?.till_id ?? '')`.
- `noCountryText(r)`: `r?.kind === 'zebra' && (r.country?.field_index ?? null) === null`.
- `get countryHint()`: with `c = this.selected?.country`:
  `''` when no selection or not zebra; when `c.label && c.till && c.label !== c.till`
  → `Label says ${c.label} · till says ${c.till}`; when `c.label && ! c.till
  && this.selected.product_code` → `'Not on the till'`; else `''`.
- `async setCountry()`: ignore when `! this.countryId` or no selection; `busy`
  on; `fetch(this.selected.country_url, PATCH, JSON { country_id: Number(this.countryId) })`
  with the usual headers; on `success`: replace the matching item in
  `this.labels` (same `kind` and `id`) with `data.label`, set
  `this.selected` to it, toast `'Country of origin: ' + name` (name from the
  select's chosen option text or `data.label.country.till ?? .label`); on
  failure toast `'Could not save the country'` and reset `countryId` from the
  (unchanged) selection; `busy` off.
- `pill(r)` unchanged (a country mismatch still shows "Country differs"
  until it is fixed, which the select now does).

Check: `node --check`; node exercise with stubbed `$root.dataset` and `fetch`:
open a label with `country {label:'Spain', label_id: 2, field_index: 3, till:'Ireland', till_id: 1}`
→ `countryId` `'2'`, `countryHint` `Label says Spain · till says Ireland`,
`noCountryText` false; open one with `field_index null` → `noCountryText`
true, hint `''`; `setCountry()` posts `{country_id: 5}` to the item's
`country_url`, replaces the row, `selected.country.label` is the new name,
toast text starts `Country of origin:`; `overrides()` never contains a
country index. `grep -c "route(" resources/js/shop/zebra.js` → 0.

### 5. CSS

`resources/css/shop.css`, inside APP ADDITIONS, after the Labels rules:

```css
.shop .shop-row__meta--icon { display: inline-flex; align-items: center; gap: 4px; }
.shop .shop-row__meta--icon > .shop-ico { width: 16px; height: 16px; color: var(--shop-ink-3); }
```

Check: design-block `cmp` identical; `npm run build`.

### 6. Tests

`tests/Feature/Shop/ShopZebraTest.php`:

- `setUp()`: create `Country` rows Ireland, Spain, France, Netherlands (keep
  ids in properties). Add product `p4` "Green Beans 200g" (`000000000285`)
  with a `vegDetails` row `countryCode` = Ireland, on the till, and a Zebra
  label "French Beans 200g" whose ZPL has text fields
  `Organic Store`, `Green Beans`, `\152.95`, `Spain`, `Origin :` (price must
  match the product so only the country differs). Give parsnip a `France`
  field.
- Extend `test_list_groups_labels_reports_mismatches_and_includes_translations`
  (or add `test_list_carries_the_country_of_origin`): beans → `country`
  `{label: 'Spain', label_id, field_index: 3, till: 'Ireland', till_id}`,
  `mismatches.country.label_value` `Spain`, `country_url` =
  `route('zebra-labels.set-country', $beans)`; kale → all five null (no
  country text, no till record); parsnip → `label 'France'`, `till null`;
  halloumi → `country null`, `country_url null`.
- `test_set_country_rewrites_the_label_and_the_till_record`: employee PATCHes
  beans with Netherlands → 200 `success`; `label.country.label` and `.till`
  both `Netherlands`, `label.mismatches` null; `$beans->fresh()->zpl_content`
  contains `^FDNetherlands^FS` and not `^FDSpain^FS`; `VegDetails` for
  `000000000285` has `countryCode` = Netherlands id. The list afterwards
  shows "Matches till" data (no mismatch) for beans.
- `test_set_country_on_a_label_without_country_text_saves_the_till_only`:
  kale → ZPL unchanged, a `vegDetails` row now exists with the chosen
  country, response `label.country.label` null and `.till` the name.
- `test_set_country_on_a_standalone_label_changes_only_the_label`: parsnip →
  ZPL updated, `VegDetails::count()` unchanged.
- `test_set_country_validates_and_is_for_printers`: unknown `country_id` →
  422; barista → 403; employee → 200 (already covered above, assert once).
- `test_employee_can_open_the_zebra_screen`: also assert the select is there
  (`id="zebra-country"`) with the four option names, and that
  `Print with till country` is absent.
- `test_office_page_and_list_agree` still passes (office page unchanged).

`tests/Feature/Shop/ConfinePinSessionTest`: unchanged; the drift test passes
because the view names no new `route()`.

Check: `php artisan test --filter="ShopZebraTest|ShopLabelsTest|ShopViewContractTest|ConfinePinSessionTest"` green.

### 7. Docs, format, build

- `docs/features/shop-mode.md`, Zebra labels bullet: the card shows the
  country of origin with a select; changing it saves the label's country
  text and the till record (`zebra-labels.set-country`, `labels.print`);
  labels without recognised country text carry the `help` icon and only the
  till record changes.
- `docs/design/shop-mode/README.md`, the 07b line: the "Print with till
  country" switch was replaced by a Country of origin select (owner decision
  2026-10-22, `docs/shop_new/findings/2026-10-22-zebra-country.md`).
- `docs/shop_new/README.md` "Screens today": one clause.

Check: `./vendor/bin/pint --test --dirty` clean; `npm run build`.

## Verification

1. `php artisan route:list --name=zebra-labels.set-country` → PATCH, `labels.print`.
2. `php artisan test tests/Feature/Shop` green.
3. `php artisan test` → the identical 16 failures.
4. `git diff --stat resources/views/labels/ app/Http/Controllers/FruitVegController.php` → empty.
   `GET /labels/zebra` as `test` through the kernel renders the same 17
   labels (the office view ignores the new `country` key).
5. `grep -c "route("` → `zebra.js:0`; design block `cmp` identical; sprite
   `cmp` identical.
6. `pint --test --dirty` clean; build succeeds.
7. Browser on dev (`osmanager.local`, as `katelyn`): open Zebra tab → rows
   for Kale Curly, SB Spinach, Rocket imported and the chutney back labels
   show the help icon in the meta line; French Beans and Carrots imported do
   not. Tap French Beans → card shows the select preset to Spain and the hint
   "Not on the till"; pick Italy → toast "Country of origin: Italy", the
   select stays on Italy, hint gone; reopen the row → still Italy;
   `php artisan tinker`: the label's ZPL now has `^FDItaly^FS` and
   `vegDetails` for `000000000285` has Italy's id. **Then put it back**: pick
   Spain again, and delete the `vegDetails` row the check created
   (`VegDetails::where('product','000000000285')->delete()`), so dev data is
   as before. Tap Rocket imported → select preset blank, notice "Not printed
   on this label…"; pick Netherlands → toast, ZPL unchanged (still Holland),
   `vegDetails` row created → delete it. Console clean.
8. Owner on production: bag a line whose origin changed, pick the country,
   print one, check the printed label and the office Zebra page agree.

## Risks

- `replaceTextFields()` replaces by index among text fields that have a font
  (`^A`) and `^FD`; the index from `extractTextFields()` matches by
  construction (the office fix relies on it), but a label with two identical
  country strings would change only the first. Acceptable.
- `upsertForProduct()` creates a row with `classId 1`, `unitId 1` for a
  product that had none. Those defaults are what the F&V page would create
  too.
- A label whose country word is unrecognised ("Holland") keeps printing it
  after a change; the icon and notice say so. The office re-save is the fix.

## Review

(Planner fills this in after reading implemented-zebra-country.md and the diff.)
