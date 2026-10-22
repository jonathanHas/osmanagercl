# Zebra tab: country of origin is invisible until it prints

Date: 2026-10-22
Found by: owner, after the Labels cycle was accepted (2026-10-21)
Status: FOLDED into `docs/shop_new/plan-zebra-country.md` (2026-10-22). Owner decided: save to both; every `labels.print` user; native select; an icon on labels whose country is not visible or available.

## What happens

On `/shop/labels/zebra` the print card says nothing about country of origin
unless the label's country text and the till's country *both* exist *and*
differ. Staff bagging produce cannot see which country the label will print,
and cannot change it. The office has two ways to change it that the Shop tab
does not reach:

- Office Zebra page (`/labels/zebra`): the print modal's "Country mismatch"
  box has a fix button that writes the till's country into the saved label
  (`zebra-labels.update-fields`, `labels.manage` only).
- Office F&V manage page (`/fruit-veg/manage`): a country dropdown per
  product writes `vegDetails.countryCode` on the POS (`fruit-veg.country.update`,
  `fruit_veg.manage`, which every shop-floor role holds) and queues an F&V
  shelf label reprint (`VegPrintQueue`, reason `country_updated`).

## Why

`ZebraLabelListService::grouped()` only reports `mismatches.country` when
`ZebraLabel::findCountryField()` finds a text field that exactly equals a name
in the `countries` table **and** the product's `vegDetails.country` is set
**and** the two differ. The Shop card (and the office modal) render only that
mismatch. The country itself is never part of the JSON.

On dev the bagged-produce labels are mostly outside both conditions:

| Label | Country text on the label | Till (`vegDetails`) |
|-------|---------------------------|---------------------|
| Spinach Imported, French Beans, lambs lettuce, Carrots imported, parsnip | France / Spain / France / Italy / France | no `vegDetails` row |
| Rocket imported 100g | "Holland" — **not in `countries`** (Netherlands is), so not detected | no row |
| Spinach_Mossfield, Rocket_Mossfield | none (name, weight, price only) | Ireland / no row |
| Kale Curly 300g, slieve Bloom Kale, SB Spinach 280g, SB Chard 270g, Mixed Salad, the four chutney labels | **no text fields at all** (graphics-only ZPL) | mostly no row |

Only 3 of the 13 linked products have a `vegDetails` row at all; the bagged
lines are not managed on the F&V page, so the "till country" is usually
empty and the label's own text is the only country there is.

## Options

1. **Show, and change for this print only.** The list JSON carries
   `country: { label, till, field_index }` for every label; the card always
   shows "Country of origin: France" (or "Not printed on this label"); a
   `shop-select` of the 37 countries overrides the field for the print, like
   "Print with till price". Nothing is saved. Cheapest; but the next person
   sees the old country again and the office stays stale.
2. **Show, and save to the label.** As 1, plus picking a country rewrites the
   saved label's country field through a new `labels.print`-level endpoint
   (`zebra-labels.set-country`, PATCH, validates the name against `countries`
   and only touches the detected country field; `update-fields` stays
   `labels.manage` because it can rewrite any text). The till record is left
   alone.
3. **Show, and save to both the label and the till record.** As 2, plus
   `VegDetails::upsertForProduct($code, ['countryCode' => $id])` so the F&V
   side agrees and the existing "Matches till" check becomes meaningful for
   these products. Reusing `fruit-veg.country.update` would also queue an F&V
   shelf label reprint, which a bagged line does not want; the new endpoint
   should write `vegDetails` directly and not queue.

Labels with **no text fields** (graphics-only) cannot print a country under
any option. The card should say so ("This label has no country text; fix it
in the office"), and the owner re-saves those labels with a text country
field when they next need one. Not a Shop job.

## Recommendation

Option 3, one `shop-select` on the card under the price/country boxes:

- Field label "Country of origin", options = `countries` by name, selected =
  the label's country text (else the till's, else blank "Choose…").
- Hint under it: "Label says France · till says Ireland" when they differ,
  "Not on the till" when there is no record, nothing when they agree.
- Changing it saves at once (toast "Country of origin: Spain"), updates the
  row's pill, and the next Print uses it. No extra button: the F&V manage
  page's dropdown saves on change too, so staff already know the gesture.
- The existing "Print with till country" switch becomes redundant once the
  select exists and goes; "Print with till price" stays.
- Permission `labels.print` (the person bagging knows the origin). The new
  route goes on the PIN allow-list.
- "Holland": re-save Rocket imported with "Netherlands" in the office, or add
  "Holland" to `countries`. Owner's call; the finding does not need it.

Design: no Claude Design round needed. The card already exists; this is one
`shop-field` + `shop-select` (as on Order review's sort) and a hint line.
If the owner wants a sheet-style picker instead of the native select, that
is a design question for the project.

## Owner decisions needed

1. Save to the label only (option 2) or to the label and the till record
   (option 3, recommended)?
2. May every `labels.print` user change it, or `labels.manage` only?
3. Native `shop-select` (recommended) or a bottom-sheet list?

## What it does not affect

- The accepted Labels cycle: nothing in it is wrong; this is a gap the
  design did not cover because the brief described country only as a
  mismatch.
- The office pages: unchanged under every option (option 3 writes the same
  `vegDetails` column the F&V page writes).
- Price mismatch handling, translated labels, the spool line.
