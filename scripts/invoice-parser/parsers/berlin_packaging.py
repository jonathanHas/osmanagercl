import re
import sys

# Comma-aware from the outset: no sample yet exceeds €1,000, but a money pattern that
# cannot span a thousands separator is how the Klee Paper parser silently truncated a
# €1,572.82 invoice to €342.80 (see docs/development/known-issues.md).
MONEY = r'-?[\d,]+\.\d{2}'
RATE = r'\d{1,2}(?:\.\d+)?'

# <item> <description> <qty> <unit price> <vat %> €<tax> €<line total>
# Descriptions wrap onto a following line, but the figures always finish the first one, so
# anchoring on the trailing "<rate>% €<tax> €<total>" is what identifies an item row. The
# shipping row carries no quantity, which this tolerates by not matching the leading columns.
LINE_ITEM_RE = re.compile(
    r'(?P<rate>' + RATE + r')%\s+€\s?(?P<tax>' + MONEY + r')\s+€\s?(?P<total>' + MONEY + r')\s*$',
    re.MULTILINE
)

# "SUBTOTAL" on this layout is the sum of the gross line totals — it equals TOTAL INCL. VAT,
# not the net. The net is TOTAL EXCL. VAT.
SUBTOTAL_RE = re.compile(r'SUBTOTAL\s*€\s?(' + MONEY + r')')
TOTAL_EXCL_RE = re.compile(r'TOTAL\s+EXCL\.\s*VAT\s*€\s?(' + MONEY + r')')
TOTAL_INCL_RE = re.compile(r'TOTAL\s+INCL\.\s*VAT\s*€\s?(' + MONEY + r')')
AMOUNT_PAID_RE = re.compile(r'AMOUNT\s+PAID\s*€\s?(' + MONEY + r')')
# "VAT (IE VAT) 23% €22.42" — one row per rate
VAT_ROW_RE = re.compile(r'VAT\s*\([^)]*\)\s*(' + RATE + r')%\s*€\s?(' + MONEY + r')')

INVOICE_NUMBER_RE = re.compile(r'Order Number\s+(\S+)')
FALLBACK_NUMBER_RE = re.compile(r'\b(NR#\d+)')
# "Issue Date Sept. 5, 2026" — AP-style month names, so "Sept." not "Sep" and "March" in
# full. datetime's %b handles neither, hence the map keyed on the first three letters.
ISSUE_DATE_RE = re.compile(r'Issue Date\s+([A-Za-z]{3,9})\.?\s+(\d{1,2}),\s*(\d{4})')

MONTHS = {
    'jan': 1, 'feb': 2, 'mar': 3, 'apr': 4, 'may': 5, 'jun': 6,
    'jul': 7, 'aug': 8, 'sep': 9, 'oct': 10, 'nov': 11, 'dec': 12,
}

RATE_KEYS = {0.0: '0', 9.0: '9', 13.5: '13.5', 23.0: '23'}
RATE_TOLERANCE = 1.0


def _amount(raw):
    """Convert '1,234.56' into a float."""
    return float(raw.replace(',', '').strip())


def _empty_buckets():
    return {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def _bucket_for(rate, net, tax, warnings):
    """
    Which VAT bucket a line belongs to, snapping an unrecognised printed rate onto the
    nearest known one rather than discarding the line and its money with it.
    """
    if rate in RATE_KEYS:
        return RATE_KEYS[rate]

    if net:
        effective = tax / net * 100
        nearest = min(RATE_KEYS, key=lambda known: abs(known - effective))
        if abs(nearest - effective) <= RATE_TOLERANCE:
            warnings.append(
                f"Invoice prints a VAT rate of {rate:.2f}% but charges {effective:.2f}% "
                f"(€{tax:.2f} on €{net:.2f}); treated as {RATE_KEYS[nearest]}%"
            )
            return RATE_KEYS[nearest]

    warnings.append(
        f"Unrecognised VAT rate {rate:.2f}% on €{net:.2f} with €{tax:.2f} of VAT; "
        f"treated as 0% and needs checking by hand"
    )
    return '0'


def _parse_date(text):
    match = ISSUE_DATE_RE.search(text)
    if not match:
        return 'Not found'
    month = MONTHS.get(match.group(1)[:3].lower())
    if not month:
        return 'Not found'
    return f"{int(match.group(2)):02d}/{month:02d}/{match.group(3)}"


def _parse_line_items(text, warnings):
    """
    Bucket the line items by their own VAT column.

    Line totals are VAT-inclusive — the invoice prints tax and gross per line but no net —
    so the net is the difference. Quantity times unit price does not always reproduce it,
    because the unit price is rounded for display.
    """
    nets = _empty_buckets()
    vat_amounts = _empty_buckets()
    rows = 0

    for match in LINE_ITEM_RE.finditer(text):
        tax = _amount(match.group('tax'))
        gross = _amount(match.group('total'))
        net = round(gross - tax, 2)
        key = _bucket_for(float(match.group('rate')), net, tax, warnings)
        nets[key] += net
        vat_amounts[key] += tax
        rows += 1

    return (
        {rate: round(value, 2) for rate, value in nets.items()},
        {rate: round(value, 2) for rate, value in vat_amounts.items()},
        rows,
    )


def _parse_totals_block(text, warnings):
    """Fallback: the summary block states the net once and the VAT once per rate."""
    nets = _empty_buckets()
    vat_amounts = _empty_buckets()

    vat_rows = VAT_ROW_RE.findall(text)
    for rate_raw, vat_raw in vat_rows:
        vat = _amount(vat_raw)
        key = _bucket_for(float(rate_raw), vat / (float(rate_raw) / 100) if float(rate_raw) else 0.0,
                          vat, warnings)
        vat_amounts[key] += vat

    excl_match = TOTAL_EXCL_RE.search(text)
    if excl_match:
        # With a single VAT rate the whole net belongs to it; with several the split is not
        # stated, so this fallback can only be trusted for the single-rate case.
        rated = [rate for rate, value in vat_amounts.items() if value]
        nets[rated[0] if len(rated) == 1 else '0'] = _amount(excl_match.group(1))
        if len(rated) > 1:
            warnings.append(
                'Line items could not be read and the invoice spans several VAT rates; '
                'the net split needs checking by hand'
            )

    return (
        {rate: round(value, 2) for rate, value in nets.items()},
        {rate: round(value, 2) for rate, value in vat_amounts.items()},
        len(vat_rows),
    )


def parse_invoice(text, filename):
    """
    Parser for Berlin Packaging Ireland Ltd invoices (trading as The Packstock).

    Line totals are VAT-inclusive and each line states its own rate and tax, so the buckets
    are built from the lines and cross-checked against the summary block. Note that
    "SUBTOTAL" on this layout is the gross figure — it equals TOTAL INCL. VAT — and the net
    is TOTAL EXCL. VAT.
    """
    print(f"[DEBUG] Parsing Berlin Packaging invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        number_match = INVOICE_NUMBER_RE.search(text) or FALLBACK_NUMBER_RE.search(text)
        invoice_number = number_match.group(1) if number_match else 'Not found'
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        invoice_date = _parse_date(text)
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        nets, vat_amounts, rows = _parse_line_items(text, warnings)
        if not rows:
            warnings.append('No line items could be read; used the summary block instead')
            nets, vat_amounts, rows = _parse_totals_block(text, warnings)
            if not rows:
                warnings.append('No VAT figures could be read from the invoice')
        print(f"[DEBUG] {rows} rows | net {nets} | vat {vat_amounts}", file=sys.stderr)

        net_sum = round(sum(nets.values()), 2)
        vat_sum = round(sum(vat_amounts.values()), 2)

        # The summary block restates all of it; check the lines against every figure
        excl_match = TOTAL_EXCL_RE.search(text)
        if excl_match and abs(_amount(excl_match.group(1)) - net_sum) > 0.01:
            warnings.append(
                f"Line items total €{net_sum:.2f} net but the invoice states "
                f"€{_amount(excl_match.group(1)):.2f} excluding VAT"
            )

        stated_vat = round(sum(_amount(v) for _, v in VAT_ROW_RE.findall(text)), 2)
        if stated_vat and abs(stated_vat - vat_sum) > 0.01:
            warnings.append(
                f"Line items total €{vat_sum:.2f} VAT but the invoice states €{stated_vat:.2f}"
            )

        incl_match = TOTAL_INCL_RE.search(text)
        if incl_match:
            total_amount = _amount(incl_match.group(1))
            if abs(net_sum + vat_sum - total_amount) > 0.01:
                warnings.append(
                    f"Net €{net_sum:.2f} plus VAT €{vat_sum:.2f} does not reach the invoice "
                    f"total €{total_amount:.2f}"
                )
        else:
            warnings.append('TOTAL INCL. VAT not found; using net plus VAT')
            total_amount = round(net_sum + vat_sum, 2)
        print(f"[DEBUG] Total: {total_amount}", file=sys.stderr)

        # SUBTOTAL is gross on this layout. If it ever stops matching the inclusive total,
        # the layout has changed in a way that needs looking at rather than assuming.
        subtotal_match = SUBTOTAL_RE.search(text)
        if subtotal_match and abs(_amount(subtotal_match.group(1)) - total_amount) > 0.01:
            warnings.append(
                f"SUBTOTAL €{_amount(subtotal_match.group(1)):.2f} does not match the "
                f"inclusive total €{total_amount:.2f}; check which figure is the net"
            )

        parsed_data = {
            'Filename': filename,
            # Must match accounting_suppliers exactly. The invoice heads "Berlin Packaging
            # Ireland Ltd" but the supplier is recorded as "Berlin Packaging Ltd", and
            # returning the longer name would create a duplicate supplier.
            'Supplier': 'Berlin Packaging Ltd',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': total_amount < 0,
            'VAT 0%': f"{nets['0']:.2f}",
            'VAT 9%': f"{nets['9']:.2f}",
            'VAT 13.5%': f"{nets['13.5']:.2f}",
            'VAT 23%': f"{nets['23']:.2f}",
            # VAT as printed, per line, rather than recomputed from the aggregate
            'VAT Amounts': vat_amounts,
            'Total': f"{total_amount:.2f}",
            'Total_VAT': vat_sum,
        }
        if warnings:
            parsed_data['Parse_Warnings'] = warnings

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        raise
