# Finding: clicking a search result did nothing in the New request sheet

Date: 2026-09-26
Found by: Implementer (Opus), after cycle 14c was accepted
Status: FIXED in the working tree (not committed)

## What happened

**Owner:** "I'm trying to use the new request feature, searching and clicking on
product isn't selecting it."

**Reproduced and confirmed** in the browser before changing anything:
```
Alpine Expression Error: pick is not defined
Expression: "pick(p)"    button.shop-row
ReferenceError: pick is not defined
```

**Cause — a regression I introduced in cycle 14, step 2.** Moving the typeahead
into `product-typeahead.js` renamed `pick(p)` to `pickResult(p)`, and I updated
`pickFirst()` (which calls it internally) but left the one call site in
`resources/views/shop/partials/request-form.blade.php:48` still saying
`@click="pick(p)"`. So the scanner path — type/scan then Enter — kept working,
while clicking a result silently threw. The edit screen I wrote in the same cycle
used `pickResult(p)` correctly, so only the New request sheet was affected.

**Why nothing caught it.** The node exercise drove `pickFirst()`, not the click
handler. The feature tests assert markup but never asserted the handler *name*.
And my cycle 14 browser check stopped at "results appear" without clicking one —
the step that would have found it in seconds.

**Fix:** one line, `@click="pickResult(p)"`.

**Swept for others.** Extracted every Alpine call from the three product-picking
views and checked each against the methods its modules define:
```
request-form.blade.php (shopRequestForm): OK — every call resolves
request-edit.blade.php (shopRequestEdit): OK — every call resolves
find-product.blade.php (shopFindProduct): OK — every call resolves
```
(after discounting Blade helpers, the `x-data` factory names and JS built-ins).
`pick(p)` was the only break.

**Regression test** added to `staff_board_shows_actions_and_form`, asserting the
sheet renders `@click="pickResult(p)"`, `@keydown.enter.prevent="pickFirst()"`
and `@click="unpick()"` — the view/module contract that broke.

**Verified after the fix**, on a fresh load with the console cleared first:
```
firstPick    7394376615894
afterUnpick  ""
secondPick   7394376621680   ("Oatly Oat drink barista Organic 1l")
console (Error|TypeError|not defined): none
```
Pick, unpick and pick a different product all work; the hidden inputs fill and
the search box swaps for the picked row.

Suite after the fix: 17 failed / 567 passed, the identical 17; pint clean.

### Note for Planner

- **A renamed method on a shared module is invisible to these tests.** The suite
  asserts rendered strings and the node exercises drive the modules; neither
  checks that a handler named in a view exists on the data object. The sweep I ran
  here could be a small test — parse `resources/views/shop/**` for Alpine calls and
  assert each resolves against the registered module — which would have caught this
  at the moment of the rename, and would catch the next one.
- **My browser checks have been stopping one step short.** I verified the
  typeahead by seeing results appear, twice, without ever clicking one. Worth
  making "exercise the action, not just the render" explicit in the manual step of
  any plan that touches an interaction.
