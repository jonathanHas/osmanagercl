import re
import sys
import datetime

# qty is bare or 1-2dp (8, 2.7, 8.00); price and amount are always 2dp.
# Credits are parenthesised on the Xero layouts: (4.80) means -4.80.
NUMBER = r'\(?-?[\d,]+(?:\.\d+)?\)?'
MONEY = r'\(?-?[\d,]+\.\d{2}\)?'

# <description> <qty> <unit price> [<tax>] <amount>
# The tax column carries "ZeroRated" on the legacy layout and is absent entirely on the
# current one. Anchoring on the trailing pair of 2dp numbers is what lets descriptions
# containing their own digits parse — "KALE, CURLY (1kg) - B146", "SALAD LEAF (1 kg) - B204".
LINE_ITEM_RE = re.compile(
    r'^(?P<desc>.*?\S)\s+'
    r'(?P<qty>' + NUMBER + r')\s+'
    r'(?P<price>' + MONEY + r')\s+'
    r'(?:(?P<tax>\d+(?:\.\d+)?%|ZeroRated|Zero Rated|Exempt|NoVAT|Taxon)\s+)?'
    r'(?P<amount>' + MONEY + r')$'
)

# Lines that look like item lines but are summary rows
SUMMARY_PREFIXES = ('SUBTOTAL', 'TOTAL', 'AMOUNTDUE', 'INCLUDES', 'BALANCEDUE')

LEGACY_TOTAL_RE = re.compile(r'TOTALEUR(' + MONEY + r')', re.IGNORECASE)
LEGACY_SUBTOTAL_RE = re.compile(r'Subtotal(' + MONEY + r')', re.IGNORECASE)

# On the current layout pdfplumber keeps the spaces. \b already stops "Total" from
# matching inside "Subtotal", so the two totals stay distinct.
NEW_TOTAL_RE = re.compile(r'\bTotal\s+(' + MONEY + r')')
NEW_SUBTOTAL_RE = re.compile(r'\bSubtotal\s+(' + MONEY + r')')

# INV-32108 on invoices, CN-xxxx should Beechlawn ever issue a Xero credit note
REFERENCE_RE = re.compile(r'\b((?:INV|CN)-\d+)')

# "31 Aug 2026" (current layout, spaces kept) and "27Jul2026" (legacy, spaces stripped)
DATE_RE = re.compile(r'\b(\d{1,2})\s*([A-Za-z]{3,9})\s*(\d{4})\b')

MONTHS = {
    'jan': 1, 'feb': 2, 'mar': 3, 'apr': 4, 'may': 5, 'jun': 6,
    'jul': 7, 'aug': 8, 'sep': 9, 'oct': 10, 'nov': 11, 'dec': 12,
}

RATE_KEYS = {'0': '0', '9': '9', '13.5': '13.5', '23': '23'}


def _amount(raw):
    """Convert '1,234.56' or '(4.80)' into a float, parentheses meaning negative."""
    value = raw.strip().replace(',', '')
    negative = value.startswith('(') and value.endswith(')')
    value = value.strip('()')
    number = float(value)
    return -number if negative else number


def _rate_key(tax_label):
    """Normalise a tax column label to one of the four VAT bucket keys."""
    if not tax_label:
        return '0'
    match = re.match(r'(\d+(?:\.\d+)?)%', tax_label)
    if not match:
        # "ZeroRated", "Exempt", "NoVAT", "Tax on Sales" — none carry VAT
        return '0'
    rate = match.group(1)
    if rate.endswith('.0'):
        rate = rate[:-2]
    return RATE_KEYS.get(rate, '0')


def _empty_buckets():
    return {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def _parse_date(day, month, year):
    """Build a date from the three captured parts, tolerating 'Sept' and 'September'.

    datetime's %b only accepts the three-letter form, so it cannot be used here — the
    current layout prints the due date as '20 Sept 2026'.
    """
    key = month[:3].lower()
    if key not in MONTHS:
        return None
    try:
        return datetime.date(int(year), MONTHS[key], int(day))
    except ValueError:
        return None


def _find_invoice_date(text, reference_match):
    """
    Both layouts print the issue date before the invoice number and the due date after it,
    so the issue date is the last date preceding the reference. Falls back to the earliest
    date in the document, the issue date always being earlier than the due date.
    """
    dates = []
    for match in DATE_RE.finditer(text):
        parsed = _parse_date(match.group(1), match.group(2), match.group(3))
        if parsed:
            dates.append((match.start(), parsed))

    if not dates:
        return 'Not found'

    if reference_match:
        before = [date for position, date in dates if position < reference_match.start()]
        if before:
            return before[-1].strftime('%d/%m/%Y')

    return min(date for _, date in dates).strftime('%d/%m/%Y')


def _collect_line_items(text):
    """Bucket the net line amounts by their tax column, returning them with a count."""
    buckets = _empty_buckets()
    lines_found = 0

    for line in text.split('\n'):
        line = line.strip()
        if not line:
            continue
        if line.replace(' ', '').upper().startswith(SUMMARY_PREFIXES):
            continue
        match = LINE_ITEM_RE.match(line)
        if not match:
            continue
        lines_found += 1
        buckets[_rate_key(match.group('tax'))] += _amount(match.group('amount'))

    return {rate: round(value, 2) for rate, value in buckets.items()}, lines_found


def _parse_legacy(text):
    """
    Legacy Xero layout, in use up to at least 2026-07-27.

    pdfplumber strips the intra-word spaces, so the labels arrive as 'InvoiceDate',
    'InvoiceNumber' and 'TOTALEUR'. Every line carries an explicit 'ZeroRated' tax column.
    """
    squashed = text.replace(' ', '')

    total_match = LEGACY_TOTAL_RE.search(squashed)
    subtotal_match = LEGACY_SUBTOTAL_RE.search(squashed)

    return {
        'total': _amount(total_match.group(1)) if total_match else None,
        'subtotal': _amount(subtotal_match.group(1)) if subtotal_match else None,
    }


def _parse_new(text):
    """
    Current Xero layout, in use since at least 2026-08-31.

    Spaces survive extraction, the tax column is gone entirely, and the totals block reads
    'Subtotal' / 'Total' / 'Amount due' rather than 'TOTALEUR'.
    """
    total_match = NEW_TOTAL_RE.search(text)
    subtotal_match = NEW_SUBTOTAL_RE.search(text)

    return {
        'total': _amount(total_match.group(1)) if total_match else None,
        'subtotal': _amount(subtotal_match.group(1)) if subtotal_match else None,
    }


def parse_invoice(text, filename):
    """
    Parser for Beechlawn Organic Farm invoices.

    Two layouts are in circulation. Beechlawn changed Xero template between 2026-07-27 and
    2026-08-31; the older one is dispatched on its 'TOTALEUR' total line. Everything
    Beechlawn supplies is certified organic produce and therefore zero-rated, and both
    layouts price their lines net, so the whole invoice lands in the 0% bucket.
    """
    print(f"[DEBUG] Parsing Beechlawn invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        is_legacy = 'TOTALEUR' in text.replace(' ', '').upper()
        print(f"[DEBUG] Layout: {'legacy' if is_legacy else 'current'}", file=sys.stderr)

        totals = _parse_legacy(text) if is_legacy else _parse_new(text)

        reference_match = REFERENCE_RE.search(text)
        invoice_number = reference_match.group(1) if reference_match else 'Not found'
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        invoice_date = _find_invoice_date(text, reference_match)
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        nets, lines_found = _collect_line_items(text)
        line_sum = round(sum(nets.values()), 2)
        print(f"[DEBUG] {lines_found} line items, net by rate: {nets}", file=sys.stderr)

        total_amount = totals['total']
        if total_amount is None:
            warnings.append('Invoice total not found; using the sum of the line items')
            total_amount = line_sum
        elif abs(line_sum - total_amount) > 0.01:
            warnings.append(
                f"Line items sum to €{line_sum:.2f} but invoice total is €{total_amount:.2f}"
            )

        # Beechlawn has never issued a VAT-bearing line. If one ever appears, Subtotal and
        # Total diverge and we cannot tell from the template alone whether Xero printed the
        # line amounts net or gross — so flag it for review rather than guess.
        subtotal = totals['subtotal']
        if subtotal is not None and abs(subtotal - total_amount) > 0.01:
            warnings.append(
                f"Subtotal €{subtotal:.2f} differs from total €{total_amount:.2f}; "
                f"this invoice appears to carry VAT and needs checking by hand"
            )

        if not lines_found:
            warnings.append('No line items could be read from the invoice')

        is_credit_note = total_amount < 0 or 'CREDITNOTE' in text.replace(' ', '').upper()

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Beechlawn',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': is_credit_note,
            'VAT 0%': f"{nets['0']:.2f}",
            'VAT 9%': f"{nets['9']:.2f}",
            'VAT 13.5%': f"{nets['13.5']:.2f}",
            'VAT 23%': f"{nets['23']:.2f}",
            # Stated rather than computed, so the dispatcher keeps the invoice's own figures
            'VAT Amounts': _empty_buckets(),
            'Total': f"{total_amount:.2f}",
            'Total_VAT': 0.0,
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
