# Shop mode cycle 14b — Compose shared Alpine modules without the getter trap

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

Cycle 14 found that spreading a shared module into an Alpine data object (`...productTypeahead()`) evaluates the module's getters at construction, before Alpine has attached `$root`, and worked around it by turning the getter into a method plus a rule in the README. The owner wants the trap removed, not documented. This cycle adds a tiny `mix()` helper that composes modules by property descriptor, so getters stay getters and are evaluated lazily like everything else on an Alpine object; the two screens that spread modules use it, the typeahead's `searchUrl` goes back to being a getter, and the rule leaves the README. One cosmetic fix deferred from cycle 14 rides along: stocked lines already on a request show the package icon on the edit screen, not the "to source" sprout.

## Context

- `resources/js/shop/product-typeahead.js`: `searchUrl()` is a method with a comment explaining the spread problem; `search()` calls `this.searchUrl()`.
- `resources/js/shop/product-images.js`: `failed`, `hasImage()`, `imageFailed()`; no getters.
- `resources/js/shop/requests.js` and `resources/js/shop/request-edit.js` each begin their data object with `...productImages(), ...productTypeahead(),` and then their own members (several of which are getters, e.g. `get productCode()` in `requests.js`; those are fine because they are declared directly on the returned object literal).
- Why spread breaks getters: object spread and `Object.assign` read each source property's **value**, which for an accessor means calling the getter once and copying the result. `Object.defineProperties(target, Object.getOwnPropertyDescriptors(source))` copies the accessor itself. Alpine wraps the data object in a reactive proxy after construction; accessor properties on the object are read through the proxy with `this` bound to the component, so `this.$root` resolves at access time.
- `resources/views/shop/request-edit.blade.php` line 83: `<span class="shop-row__lead" x-show="! item.product"><x-shop.icon name="sprout" /></span>`; seeded lines have `product: null` even when `product_code` is set (the seed carries no image), so a stocked line shows the sourcing icon.
- README `docs/design/shop-mode/README.md` line ~67 describes the two modules and (per cycle 14) states the "methods, not getters" rule.
- Node checks in cycles 12–14 exercised these modules with `node -e` and a stubbed `$root`; do the same here.

## Constraints

- Do not commit, push or deploy.
- No behaviour change on any screen: the scanner-Enter pick, the merge rule, thumbnails and the sheet form all stay as they are. Tests are markup-level and must stay green unchanged.
- The helper is plain ESM with no dependencies; the design block of the stylesheet is untouched.

## Out of scope

- Adopting `mix()` in the other screen modules (they do not compose anything).
- Adding `image_url` to seeded edit lines (a later tidy).

## Steps

### 1. `mix()`
Files: `resources/js/shop/mix.js (new)`
What:
```js
/**
 * Compose Alpine data objects from shared parts without losing getters.
 *
 * Object spread reads each property's value, so a getter on a part would run
 * at construction — before Alpine has attached $root — and be copied as a
 * plain value. Copying property descriptors keeps accessors as accessors, so
 * `get searchUrl()` is evaluated when Alpine reads it, on the live component.
 * Later parts override earlier ones, as with spread.
 */
export default function mix(...parts) {
    const target = {};

    for (const part of parts) {
        Object.defineProperties(target, Object.getOwnPropertyDescriptors(part));
    }

    return target;
}
```
Check: `node -e` exercise: `const o = mix({ get a() { return this.b; } }, { b: 1, c() { return 2; } }); Object.getOwnPropertyDescriptor(o, 'a').get !== undefined` → true; `o.a === 1`, `o.c() === 2`; a getter that throws when called must **not** throw during `mix()` (prove laziness: `mix({ get boom() { throw new Error('early'); } })` does not throw; reading `.boom` does).

### 2. Use it, and restore the getter
Files: `resources/js/shop/product-typeahead.js`, `resources/js/shop/requests.js`, `resources/js/shop/request-edit.js`
What: `searchUrl()` becomes `get searchUrl()` again with a one-line comment ("read lazily: composed with mix(), so this runs on the live component"); `search()` uses `this.searchUrl`. Both screen modules become `export default (…) => mix(productImages(), productTypeahead(), { …own members… })`, own members unchanged (their own getters included). Delete the old spread comment.
Check: the cycle 12 Rev 2 scanner exercise against `requests.js` (construct with a `$root` stub assigned **after** construction: `const c = requestForm(null); c.$root = { dataset: { searchUrl: '/s' } }; global.fetch = …; await c.pickFirst()` with `query` set) → picks the product; the same shape against `request-edit.js` → `onPick` merge and `canRemove` outputs identical to cycle 14's; and `Object.getOwnPropertyDescriptor(c, 'searchUrl').get` is defined on both. `grep -c "\.\.\.product" resources/js/shop/*.js` → 0.

### 3. Edit screen placeholder icon
Files: `resources/views/shop/request-edit.blade.php`
What: replace the single sprout placeholder with two: `<span class="shop-row__lead" x-show="! item.product && item.product_code"><x-shop.icon name="package" /></span>` and `<span class="shop-row__lead" x-show="! item.product && ! item.product_code"><x-shop.icon name="sprout" /></span>`.
Check: `php artisan test --filter="ShopRequestsTest|ShopViewContractTest"` green; the rendered edit page contains both `#package` and `#sprout` references (add that to `edit_page_seeds_lines_and_posts_to_update`).

### 4. README, build, format
Files: `docs/design/shop-mode/README.md`, all touched
What: replace the "methods, not getters" sentence with: "Screen modules that reuse shared parts compose them with `mix()` (`resources/js/shop/mix.js`), which copies property descriptors, so shared parts may use getters like any other Alpine data; never spread a shared part into a data object." `npm run build`; `./vendor/bin/pint --test --dirty` (no PHP changes expected).
Check: `grep -n "getter" docs/design/shop-mode/README.md` shows only the new sentence; build succeeds.

## Verification

1. `node --check` on `mix.js`, `product-typeahead.js`, `requests.js`, `request-edit.js`; the exercises in steps 1–2 pass.
2. `php artisan test --filter="Shop|CustomerRequest"` → green, no test changed except the one assertion added in step 3.
3. `php artisan test` → 17 failed, the identical set; passed count unchanged (566).
4. `grep -c "route(" resources/js/shop/mix.js resources/js/shop/product-typeahead.js` → 0; design block `cmp` identical; contract greps clean.
5. `npm run build` succeeds.
6. Manual, signed in on the dev app: on the board, "New request" → type three letters in Item → results appear (proves `searchUrl` resolves lazily on the live component); scan a barcode + Enter → picked. On an existing request's Edit page, a stocked line shows the package icon; type in the add box → results appear.

## Risks

- **Alpine and accessor descriptors**: Alpine's `Alpine.data()` factories return plain objects and Alpine reads through its reactive proxy, so `this` inside a getter is the component; the screens already rely on this for their own getters (`get productCode()`), so nothing new is being assumed.
- **Descriptor copy and `failed: {}`**: `productImages()` returns a fresh object per call, so each component gets its own `failed` map, as before.


## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end and the diff of `mix.js`, the typeahead module, both composed screen modules, `product-images.js`, the thumbnail component, the edit view, the test and the README. Reran `php artisan test`: 17 failed / 566 passed, the identical set. Design block identical; no scripts or styles in Shop views.

**Steps 1–4: pass.** `mix()` is verbatim, the laziness proof passes (a throwing getter does not throw during composition), `searchUrl` is an accessor again on both screens and resolves on the live component, the merge and removal figures match cycle 14, each component keeps its own `failed` map, the edit screen shows `package` for stocked lines and `sprout` for free-text ones, and the README sentence is the intended one.

**Deviations.** 1 (optional chaining restored in the thumbnail component and a null guard in `imageFailed`): **accepted, and welcome**: cycle 14's component dropped the `?.` the markup it replaced had, so every board load logged six console errors while `picked` was null. Found by the implementer's own manual check; in scope for a cycle tidying that component. 2 (a second README sentence corrected for consistency): **accepted.**

**Notes for Planner.**
- `x-show` hides but does not skip an element's other bindings; `x-if` does. **Recorded in planimp.md** next to the Alpine `@` trap.
- `mix()` has two callers today: fine; it exists to remove a footgun.
- The `x-data` attribute string is not live state: noted for future manual checks.
- A stale word remains in the thumbnail component's doc comment ("scope that spreads"): cosmetic, fold into the next tidy.
- Nothing committed: correct.

**Manual check:** run by the implementer; the typeahead returned results with thumbnails from a freshly built page and the console was clean where it had six errors before.

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-shop-mode-cycle-14b/`.
