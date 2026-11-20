# Electricity

## Overview

Managers and admins review electricity consumption and cost at **Financial → Electricity** (`/management/electricity`). The figures come from the electricity bills. Every bill, whoever the supplier, is stored in one provider-neutral shape so that months and suppliers can be compared.

Three ideas hold the section together:

1. **The MPRN is the key, not the supplier or the account.** The shop's supply point has kept its MPRN through three Flogas account numbers, two bill layouts and a change of customer name.
2. **Usage comes from the meter readings, not from the units a bill charges.** Bills estimate, later reverse the estimate, and re-bill earlier periods. The kWh a bill charges is therefore often not what was used in its period.
3. **Every bill is checked by arithmetic.** A bill read by a provider parser that passes every blocking check is confirmed by itself. Anything else, and everything read by AI, waits for a manager.

Only confirmed bills count in the figures.

## Screens

| Route | What it does |
|---|---|
| `management.electricity.index` | Overview: last 12 months against the 12 before, four charts, month-by-month table with each meter's share of the cost, meter names |
| `management.electricity.meter-split` | CSV of each meter's kWh, share and cost per month (for recharging The Space Above) |
| `management.electricity.bills.index` | Bills list, upload (several PDFs at once), **Load from accounts invoices** |
| `management.electricity.bills.show` | One bill beside its PDF: checks, readings, charges; **Correct figures**, **Confirm**, **Re-read with AI**, **Remove** |

All routes sit in the `management` group (`role:admin,manager`). There is no separate permission.

## The neutral bill shape

Produced by every reader (`ElectricityBillParser::parse()`, `ElectricityBillAiReader::read()`, the edit form) and consumed by `ElectricityBillValidator` and `ElectricityBillImportService::store()`.

- Header: `provider`, `mprn`, `account_number`, `bill_number`, `document_type` (`invoice` / `credit_note`), `issue_date`, `due_date`, `period_start`, `period_end`, `tariff`, `dg_group`, `mic_kva`.
- Totals: `net_total` (before VAT, **never includes an export credit**), `vat_total`, `export_credit_total` (negative), `gross_total` (this bill only, no balance brought forward).
- `charges[]`: `category`, `band`, `description` (as printed), `quantity`, `unit`, `rate`, `period_start`, `period_end`, `net_amount`, `vat_rate`.
- `readings[]`: `meter_serial`, `band`, `period_start`, `period_end`, `previous_reading`, `previous_type`, `present_reading`, `present_type`, `multiplier`, `kwh`, `is_reversal`.

Fixed vocabularies (`ElectricityBillValidator` constants):

| Field | Values |
|---|---|
| `category` | `energy`, `standing`, `pso_levy`, `electricity_tax`, `market` (I-SEM, capacity), `network` (MIC, DUoS, TUoS), `export_credit`, `adjustment` (a lump credit or debit), `other` (exit fees and the like) |
| `band` | `24h`, `day`, `night`, `peak` |
| read type | `actual`, `customer`, `estimate` |
| `unit` | `kwh`, `mwh`, `day`, `month`, `kva` |

## Tables

`2026_11_20_000001_create_electricity_tables.php`:

- `electricity_supply_points` (`mprn` unique, `mic_kva`, `dg_group`)
- `electricity_meters` (supply point, `serial`, `name`)
- `electricity_bills` (header, totals, `import_kwh`, `export_kwh`, `status`, `source`, `checks` json, `warnings` json, `invoice_id`, `file_path`, `file_hash`, `confirmed_by`, `confirmed_at`); unique on provider + bill number + document type, because Flogas credits a bill under the bill's own number
- `electricity_bill_charges`, `electricity_meter_readings`

A bill loaded from an accounts invoice has `invoice_id` and no `file_path`: its PDF is the invoice attachment. An uploaded bill is stored at `electricity/{year}/{sha256}.pdf` on the `private` disk.

## Reading a bill

`ElectricityBillImportService::importFromPdf()`:

1. Same file hash already loaded → duplicate.
2. `BillTextExtractor` runs `pdftotext -layout`.
3. The first parser in `ElectricityBillImportService::PARSERS` whose `supports()` is true reads it.
4. No parser, but the text has an MPRN → `ElectricityBillAiReader` (Claude reads the whole PDF) if it is set up.
5. Same provider + bill number already confirmed → duplicate (and the invoice is linked if it was not).
6. `store()` writes the bill, runs the checks and sets the status.

### Adding a provider

Write a class implementing `App\Services\Electricity\Contracts\ElectricityBillParser` and add it to `PARSERS`. Test it with text fixtures (`pdftotext -layout bill.pdf fixture.full.txt`), as `tests/Unit/FlogasBillParserTest.php` does. Until a parser exists, the AI reader handles the provider and every bill is reviewed by hand.

### Flogas quirks (`FlogasBillParser`)

- **Side panel.** Payment and contact text sits to the right of the tables and lands inside the table lines. The parser reads the tables from the left 370 points of the page (`cropWidth()`), header facts from the whole page. Fixtures therefore come in pairs: `.full.txt` and `.left.txt` (`pdftotext -layout -x 0 -y 0 -W 370 -H 3000`).
- **Two layouts.** To September 2025 (`fge_…` files): VAT shown as `R`/`Z`, a line's name may be on the row above its figures, dates as `16/07 31/07` without a year. Since then (`…_flri.PDF`): VAT as a percentage, the date range on the row below, names wrapped over up to three rows, ornate brackets `﴾ ﴿`.
- **2024 bills** print the MPRN and the billing period without labels.
- **Reversals.** A detached minus (`- 1388`, even `- -1440`) marks a row that takes back an estimate billed earlier (`is_reversal`). A plain negative (`-1286 kWh`) is an estimate going backwards and is not a reversal.
- **Chained rows.** A bill covering two reads prints two rows per meter. The first row's end date is missing in the older layouts, and some bills print the same dates on both rows of one meter while the other meter's rows are right. `repairChainedRows()` fills the end dates and copies misprinted dates from the meter that is in order, adding a warning to the bill.
- **Export credit** is a 0% line after the net total on newer bills. On the old layout it is a separate statement whose "net bill" is the credit (`Export Credit-342.72kWh @ €.185 to 21-07-25`); the parser moves it out of `net_total`.
- **Credit adjustments.** Old-layout lump credits (`Credit adjustment.`) cancel a whole earlier bill and carry that bill's number.
- **Header period** can be wrong (`13/06/25 - 13/06/25` on re-bills); the parser then takes the period from the lines.

## Checks (`ElectricityBillValidator`)

Blocking (`fail`): key details present; meter kWh add up to the energy units billed; units × rate = amount on each line; lines add up to the net total; net + VAT + export credit = bill total; categories, read types and reading dates valid.

Notes (`warn`, never block): readings follow on from the previous bill (an estimate the bill itself reverses does not count as a break); period follows the previous bill; total matches the linked accounts invoice.

Checks are stored on the bill and re-run when the bill is opened, after every upload and after an import, because the notes depend on the neighbouring bills.

## Usage and cost (`ElectricityUsageService`)

**kWh per month.** For each meter, every non-reversal reading row gives two points: the previous reading at the end of the day before the row starts, and the present reading on its end date. Estimates that a later bill reversed are dropped. Actual and customer reads are fixed points; the first and last point of the timeline are kept even if estimated. The kWh between two fixed points are spread evenly over the days between them. A month resting on an estimated end point is flagged `estimated` and corrects itself when an actual read is loaded.

**Cost per month.** Each charge line is spread over its own dates (the bill's period when it has none). Three refinements:

- A per-kWh line whose quantity equals the bill's meter-row total follows those rows. A bill that reverses an estimate then puts the correction in the days the estimate covered.
- A lump credit under the number of a bill on file is spread over that bill's period, cancelling it. If the bill it cancels is not on file the credit is left out and listed on the overview, since neither side should count.
- An export credit that only says "to <date>" starts the day after the previous export credit ended.

**Headline figures.** All-in cost per kWh = (net cost − `other`) ÷ metered kWh, over months a bill covers. Unit rate = energy lines ÷ kWh those lines charged. Fixed charges per day = standing charge + PSO levy.

**Meter split.** Each meter's share of a month's kWh is applied to that month's net cost and VAT. Export credit is not shared.

Nothing is pre-aggregated: there are a few dozen bills. Half-hourly or smart-plug data, when added, should get a daily summary table (see `sales-data-import-plan.md`).

## Getting bills in

- **Bulk upload.** `InvoiceCreationService::createFromParsedFile()` queues `ImportElectricityBill` (after commit, on the invoice parsing queue) when the supplier's `supplier_type` is `utility`. The job ignores PDFs that are not electricity bills and never fails the invoice.
- **Existing invoices.** `php artisan electricity:import-from-invoices [--supplier=ID] [--from=YYYY-MM-DD] [--dry-run]`, or the **Load from accounts invoices** button.
- **Upload** on the bills page, for bills that are not in the accounts.

## AI reading

Feature key `electricity_parsing` in **Tools → AI Diagnostics** ("Electricity Bill Reading"). Defaults: provider `anthropic`, model `claude-opus-5-5`. Only the Anthropic provider works, because the bill is sent as a PDF through `ClaudeAiService::complete()`. The prompt asks for the neutral shape exactly; `normalise()` forces types and vocabulary. AI results always wait for review.

## Tests

- `tests/Unit/FlogasBillParserTest.php` – eight real bills as text fixtures in `tests/Fixtures/electricity/`
- `tests/Unit/ElectricityBillValidatorTest.php`
- `tests/Feature/ElectricityTest.php` – access, import and duplicates, usage and cost rules, review flow, meter split CSV

## Not built yet

- Solar generation from the inverter (self-consumed = generated − exported; the export kWh on bills is the cross-check).
- ESB Networks smart-meter interval files by MPRN.
- Smart plugs per appliance. The real c/kWh could feed `config/kitchen.php` `electricity_rate`.
- Re-pricing the last 12 months of usage on a quoted rate card.
