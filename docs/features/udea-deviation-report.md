# Udea Deviation Report

Generates a pre-filled Udea "deviation report" Excel file from items selected on the delivery
matching page, so short/missing deliveries can be claimed back from Udea without manual data entry.

## Overview

**Navigation:** Deliveries → Match a delivery (`/delivery-legacy/match?delID=...&supplierID=...`)

**Purpose:** When a Udea delivery is scanned, some ordered items are often missing or delivered
short. Udea requires these to be reported on their official deviation-report spreadsheet to issue a
credit. This feature lets the user tick the affected rows on screen and download an `.xlsx` with the
order number, article number, product name, amount and deviation reason already filled in.

**Scope:** Udea suppliers only (`config('suppliers.external_links.udea.supplier_ids')`, default
`[5, 44, 85]`) and **desktop only** — all UI is hidden on mobile and for non-Udea suppliers.

## How it works

The match page has two tables that feed the report, each with a different "Amount" meaning:

| Source table | On-screen heading | Amount written to report |
|---|---|---|
| `$onInvoiceNotScanned` | **Missing – Not Scanned** | Expected quantity (nothing received) |
| `$criticalItems` (from `$matchedItems`) | **Critical Issues – Qty Mismatches** | Shortfall = `abs(Expected − Scanned)` |

The user ticks rows in either/both tables, clicks **Generate from Selected**, and the browser
downloads the filled spreadsheet.

### Why values are re-queried server-side

The checkboxes only carry a `source:barcode` token (e.g. `pending:5901234123456`,
`mismatch:5400101234567`). Product names, order numbers and quantities are **recomputed on the
server** from the same POS queries the page used, so the report can't be tampered with via the form
payload and stays correct even if the page data is stale.

## The Excel template

**File:** `public/downloads/deviation-report-template-2025.xlsx` (sheet `Blad1`)

The template is loaded with `IOFactory::load()` and re-saved, which **preserves its styling, the
Deviation dropdown (data validation) and named ranges**. The customer number (`90202`) and store
name are baked into cells `D3`/`D4` of the template itself and are left untouched.

Header is **row 8**; data rows are written from **row 9**:

| Col | Header | Filled with |
|-----|--------|-------------|
| A | Delivery date* | `deliveriesScan.dateUpload`, formatted `d/m/Y` |
| B | Order NR | `orderNumber` (written as explicit string) |
| C | Art NR* | `supCode` (written as explicit string) |
| D | Product name* | `dbProductName ?? prodName` |
| E | Amount* | per-source rule above (whole number, or up to 3 decimals for kg) |
| F | Deviation* | literal `"Not recieved (Partially)"` (exact dropdown option) |
| G | extra information | left blank for the user to complete |

> Codes in columns B and C are written with `setCellValueExplicit(..., DataType::TYPE_STRING)` so
> they are not coerced into numbers / scientific notation.

## Implementation

| Layer | Location |
|---|---|
| Route | `routes/web.php` — `POST delivery-legacy/deviation-report` → `delivery-legacy.deviation-report` |
| Controller | `app/Http/Controllers/DeliveryLegacyController.php` — `deviationReport()` + helper `formatDeviationAmount()` |
| View | `resources/views/delivery-legacy/match.blade.php` — checkbox columns, "Generate from Selected" button, `generateDeviationReport()` / `toggleDeviationGroup()` JS |
| Library | `phpoffice/phpspreadsheet` (already a project dependency) |

**Controller flow (`deviationReport`):**
1. Read `delID`, `supplierID`, `items[]`; redirect back with an error if nothing is selected.
2. Re-query `getMatchedItems()` and `getOnInvoiceNotScanned()`; build `barcode → item` lookups.
3. Recompute the mismatch set using the same logic as the view
   (`expected = fmod(myOrder,1) != 0 ? myOrder : caseUnits * myOrder`).
4. Resolve each selected `source:barcode` into a row, computing the amount per source.
5. Load the template, write rows from row 9, and stream the file via `response()->streamDownload()`.

**View:**
- Each desktop table (Qty Mismatches and Missing) gains a leading checkbox column gated by
  `@if($isUdea)`, with a header "select all" (`toggleDeviationGroup`) and per-row
  `<input class="deviation-select" value="<source>:<barcode>">`.
- The purple **Generate from Selected** button sits beside the existing blank-template download link
  (both are kept). `generateDeviationReport()` collects ticked rows, builds a hidden POST form
  (CSRF + `delID` + `supplierID` + `items[]`) and submits it to trigger the download. A generated
  form is used (rather than wrapping the tables in a `<form>`) to avoid invalid nested forms with the
  existing complete-delivery form.

## Downloaded file naming

`deviation-report-<YYYY-MM-DD>.xlsx` (delivery date), falling back to the delivery ID when no scan
date is available.

## Notes / future

- The blank-template download button remains available for manual cases.
- If Udea reissues the template, replacing `public/downloads/deviation-report-template-2025.xlsx`
  is enough **provided the column layout (header on row 8) stays the same**.
- Possible enhancements: pre-fill column G ("extra information"), or choose a different default
  deviation reason for mismatch rows vs. fully-missing rows.
