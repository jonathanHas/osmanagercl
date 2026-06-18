# IIHF (Independent) Goods Return Sheet

Generates a pre-filled IIHF "Goods Return Record" **PDF** from items selected on the delivery
matching page, so short/missing deliveries can be claimed back from Independent Irish Health Foods
without manual data entry. This is the PDF counterpart to the Excel-based
[Udea Deviation Report](./udea-deviation-report.md) and shares the same on-screen selection flow.

## Overview

**Navigation:** Deliveries → Match a delivery (`/delivery-legacy/match?delID=...&supplierID=...`)

**Purpose:** When an Independent (IIHF) delivery is scanned, some ordered items are often missing or
delivered short. IIHF requires these to be reported on their official "Goods Return Record" form to
issue a credit. This feature lets the user tick the affected rows on screen and download a filled-in
PDF of that exact form.

**Scope:** Independent suppliers only (`config('suppliers.external_links.independent.supplier_ids')`,
default `[37]`) and **desktop only** — all UI is hidden on mobile and for other suppliers.

## How it works

The match page has two tables that feed the sheet, each with a different "Quantity" meaning:

| Source table | On-screen heading | Quantity written |
|---|---|---|
| `$onInvoiceNotScanned` | **Missing – Not Scanned** | Expected quantity (nothing received) |
| `$criticalItems` (from `$matchedItems`) | **Critical Issues – Qty Mismatches** | Shortfall = `abs(Expected − Scanned)` |

The user ticks rows in either/both tables, clicks **Generate Returns Sheet**, and the browser
downloads the filled PDF. The selection checkboxes and JS are shared with the Udea report (each
checkbox carries a `source:barcode` token); values are **re-queried server-side** so the sheet can't
be tampered with via the form payload.

## Why an overlay (not cell-filling)

Unlike the Udea Excel template, the IIHF form is a **flat PDF with no fillable fields**, produced
from Excel 2007 with compressed object/xref streams (PDF v1.5). We therefore **overlay** our data
onto the official form at fixed coordinates:

- The official PDF is converted **once** with Ghostscript to PDF 1.4 (the free FPDI parser can't read
  v1.5 compressed streams) and stored as the template:
  `public/downloads/iihf-goods-return-template.pdf`.
  Command used:
  `gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dBATCH -dQUIET -sOutputFile=public/downloads/iihf-goods-return-template.pdf "<source>.pdf"`
- `App\Services\IihfGoodsReturnPdfService` imports that page with **FPDI** and stamps text with
  **FPDF** at calibrated points (top-left origin, matching `pdftotext -bbox`).

> The original form's Account Name / Account No were stored as **AcroForm field values**, which FPDI
> does not render — that's why they're overlaid rather than relying on the template (see below).

## What gets filled

**Per line item** (table rows start at the first printed row, 11 rows per page; extra items spill to
additional pages):

| Column | Source |
|--------|--------|
| Invoice No. | `orderNumber` (IIHF invoice ref) |
| Product Code | `supCode` |
| Description | `dbProductName ?? prodName` |
| Quantity | per-source rule above |
| Reason for Return | an **X** over code **A: Not delivered** |
| Value (Estimated Net) | `€ cost × qty` |
| VAT | product VAT rate as `%` (e.g. `0%`, `13.5%`, `23%`) |

**Fixed header/footer fields** (drawn on every page):

| Field | Value |
|-------|-------|
| Account Name | Mossfield Organic Store Ltd |
| IIHF Account No. | 20128 |
| Customer Contact Name | Jonathan Haslam |
| Date | delivery date (`deliveriesScan.dateUpload`, `d/m/Y`) |

Left blank for completion at pickup: **Signature**, **Accepted by IIHF Driver**, and the
**Driver Check** column.

## Implementation

| Layer | Location |
|---|---|
| Route | `routes/web.php` — `POST delivery-legacy/goods-return-sheet` → `delivery-legacy.goods-return-sheet` |
| Controller | `app/Http/Controllers/DeliveryLegacyController.php` — `goodsReturnSheet()` + helper `formatVatRate()` |
| Overlay service | `app/Services/IihfGoodsReturnPdfService.php` (coordinate constants `COL`, `REASON_X`, `STATIC_FIELDS`, `DATE_POS`) |
| View | `resources/views/delivery-legacy/match.blade.php` — shared `.deviation-select` checkboxes, "Generate Returns Sheet" button, `generateGoodsReturnSheet()` JS |
| Libraries | `setasign/fpdi`, `setasign/fpdf` |
| Template | `public/downloads/iihf-goods-return-template.pdf` (PDF 1.4) |

**Controller flow (`goodsReturnSheet`):** read `delID`/`supplierID`/`items[]`; re-query
`getMatchedItems()` + `getOnInvoiceNotScanned()`; resolve each `source:barcode` into a row
(invoice/code/description/qty/value/vat, reason `A`); stream the PDF as `goods-return-<date>.pdf`.

**Note:** `getOnInvoiceNotScanned()` was extended to also select `TAXES.RATE` so VAT can be filled
for missing items (the matched query already had it).

## Maintenance notes

- **Adjusting positions:** all overlay coordinates live as constants in `IihfGoodsReturnPdfService`.
  Re-calibrate against `pdftotext -bbox public/downloads/iihf-goods-return-template.pdf`.
- **Reissued form:** if IIHF sends a new form, downgrade it to PDF 1.4 with the Ghostscript command
  above, replace the template, and re-check the coordinates.
- **Reason code:** currently always **A (Not delivered)** for both tables; `REASON_X` maps A–F if a
  different default is ever needed.
- Account No / contact name / account name are hardcoded as `STATIC_FIELDS` constants (IIHF-specific).
