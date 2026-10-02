# Finding: the "Find by name" list seen empty once

**Seen:** by the Implementer during the delivery follow-ups browser pass
(2026-10-02), once, straight after navigating to the scan page: `results: 0`
and no search request. Not reproduced in four further opens. The owner has not
seen it.

**Investigated by the Planner, 2026-10-02**, on dev, open Mossfield session
`778fb73c…` (read-only; opening the list writes nothing). Twelve page loads in
same-origin iframes, clicking "No barcode? Find by name" at different moments:

| Click | Runs | Result |
|---|---|---|
| Before Alpine had started (the button is in the HTML, so it is clickable from first paint) | 3 | Nothing happens: `manual` stays false, no request, `results` 0 |
| 5 to 400 ms after Alpine had started | 9 | List opens with 10 products, one request, HTTP 200, every time |

No console errors or unhandled rejections in any run.

## What the one-off was

The first row matches the report exactly (0 results, no request). The click
landed in the gap between the page painting and Alpine starting; on dev that
gap ended 65–130 ms after the iframe began loading. The tap does nothing and a
second tap works. The same is true of every Alpine button on every Shop
screen; it is not specific to this list, and a person is unlikely to tap that
fast. It is not a fault in the search.

## A real weakness found while looking

`product-typeahead.js` `search()` catches every failure and sets
`results = []`. With the search made to fail (request rejected), the open
card shows only its title and the filter box: no products, no message. With an
empty filter even "No products match" does not show. So on the shop floor any
of these looks like "the list is empty":

- the tablet's wifi drops for a moment;
- the session has expired, so the endpoint answers with the login page (HTML,
  which fails JSON parsing);
- a server error.

This is the recurring cause worth fixing, and it applies to the other screens
that use the typeahead (New request, request edit), which fail silently in the
same way.

There is also no guard against two searches answering out of order (the
slower, older one would overwrite the newer). Not seen, but the same fix can
cover it.

## Options

1. **Say so and offer a retry (recommended).** `search()` records a failure
   (`searchFailed = true`) instead of only emptying the list; the delivery
   list shows "Could not load products" with a "Try again" button. Ignore a
   response that is not from the latest request. Small, JS and one view.
2. Also hide the "No barcode?" button until Alpine has started (`x-cloak`),
   so an early tap cannot be lost. One attribute; optional.
3. Do nothing: an empty list is recoverable by closing and reopening it.

## What it does not affect

Nothing is saved or lost by an empty list; scanning and typed quantities are
unaffected.
