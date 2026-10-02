import re
import sys

# Comma-aware, as a money pattern that cannot span a thousands separator is how the Klee
# Paper parser silently truncated a €1,572.82 invoice (see docs/development/known-issues.md).
# No "€" is printed against any figure on this layout.
MONEY = r'-?[\d,]+\.\d{2}'

# "<qty> <description> <unit price> <line total> [<rrp>]" — line totals are net, and every
# Sonett product is standard-rated, so all of it lands in the 23% bucket. Some invoices
# fill the RRP column and some leave it blank, so a row ends in two or three figures and
# position alone cannot say which is the line total (see _line_total).
LINE_ITEM_RE = re.compile(
    r'^(?P<qty>\d+)\s+(?P<desc>.+?)(?P<figures>(?:\s+' + MONEY + r'){2,3})\s*$',
    re.MULTILINE
)

# The net subtotal has no label: it is the figure on its own line directly above the VAT row.
NET_RE = re.compile(r'^(' + MONEY + r')\s*\n\s*V\.A\.T\.\s*23%', re.MULTILINE)
VAT_RE = re.compile(r'V\.A\.T\.\s*23%\s+(' + MONEY + r')')
TOTAL_RE = re.compile(r'Final amount in Euro\s*\n\s*(' + MONEY + r')')

# "No. 2603" on older invoices, "No. S26-0824-2606" from August 2026
INVOICE_NUMBER_RE = re.compile(r'^No\.\s*(\S+)', re.MULTILINE)
# "3 April 2026" on a line of its own. Anchoring on the line start skips the
# "to be paid before 3 May 2026" due date further down.
DATE_RE = re.compile(r'^(\d{1,2})\s+([A-Za-z]{3,9})\.?\s+(\d{4})\s*$', re.MULTILINE)

MONTHS = {
    'jan': 1, 'feb': 2, 'mar': 3, 'apr': 4, 'may': 5, 'jun': 6,
    'jul': 7, 'aug': 8, 'sep': 9, 'oct': 10, 'nov': 11, 'dec': 12,
}


def _amount(raw):
    """Convert '1,234.56' into a float."""
    return float(raw.replace(',', '').strip())


def _line_total(qty, figures):
    """
    The line total is the figure that equals quantity times the figure before it (the unit
    price); the RRP, when printed, bears no such relation. Returns None when no adjacent
    pair fits, e.g. a discounted line, so the caller can flag it.
    """
    for unit, total in zip(figures, figures[1:]):
        if abs(qty * unit - total) <= 0.01:
            return total
    return None


def _parse_line_items(text, warnings):
    lines_net = 0.0
    rows = 0
    for match in LINE_ITEM_RE.finditer(text):
        qty = int(match.group('qty'))
        figures = [_amount(f) for f in match.group('figures').split()]
        total = _line_total(qty, figures)
        if total is None:
            # Best guess from position: the RRP, when present, is last
            total = figures[1] if len(figures) == 3 else figures[-1]
            warnings.append(
                f"Line \"{match.group(0).strip()}\": quantity times unit price does not match "
                f"any figure; took €{total:.2f} as the line total"
            )
        lines_net += total
        rows += 1
    return round(lines_net, 2), rows


def _parse_date(text):
    for day, month_name, year in DATE_RE.findall(text):
        month = MONTHS.get(month_name[:3].lower())
        if month:
            return f"{int(day):02d}/{month:02d}/{year}"
    return 'Not found'


def parse_invoice(text, filename):
    """
    Parser for Sonett Ireland invoices (Frank van Gent, Cornamona).

    A single-page, single-rate layout: net line totals, an unlabelled net subtotal, one
    23% VAT row and the "Final amount in Euro". The stated subtotal is authoritative; the
    line items are only a cross-check, because a freight charge may appear without the
    quantity column the item pattern needs.
    """
    print(f"[DEBUG] Parsing Sonett invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        number_match = INVOICE_NUMBER_RE.search(text)
        invoice_number = number_match.group(1) if number_match else 'Not found'
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        invoice_date = _parse_date(text)
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        lines_net, rows = _parse_line_items(text, warnings)
        print(f"[DEBUG] {rows} line items totalling {lines_net}", file=sys.stderr)

        net_match = NET_RE.search(text)
        if net_match:
            net = _amount(net_match.group(1))
            if rows and abs(lines_net - net) > 0.01:
                warnings.append(
                    f"Line items total €{lines_net:.2f} but the invoice states a net of €{net:.2f}"
                )
        else:
            warnings.append('Net subtotal not found; using the sum of the line items')
            net = lines_net

        vat_match = VAT_RE.search(text)
        if vat_match:
            vat = _amount(vat_match.group(1))
        else:
            warnings.append('VAT 23% row not found; calculated it from the net')
            vat = round(net * 0.23, 2)

        if abs(net * 0.23 - vat) > 0.02:
            warnings.append(f"VAT €{vat:.2f} is not 23% of the net €{net:.2f}")

        total_match = TOTAL_RE.search(text)
        if total_match:
            total_amount = _amount(total_match.group(1))
            if abs(net + vat - total_amount) > 0.01:
                warnings.append(
                    f"Net €{net:.2f} plus VAT €{vat:.2f} does not reach the invoice "
                    f"total €{total_amount:.2f}"
                )
        else:
            warnings.append('Final amount not found; using net plus VAT')
            total_amount = round(net + vat, 2)
        print(f"[DEBUG] Net {net} | VAT {vat} | Total {total_amount}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            # Must match accounting_suppliers exactly: recorded as "Sonett", not the
            # "Sonett Ireland" of the letterhead
            'Supplier': 'Sonett',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': total_amount < 0,
            'VAT 0%': '0.00',
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': f"{net:.2f}",
            'VAT Amounts': {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': vat},
            'Total': f"{total_amount:.2f}",
            'Total_VAT': vat,
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
