import re
import sys

# Amounts carry thousands separators on the larger invoices: "€ 1,076.00"
MONEY = r'-?[\d,]+\.\d{2}'
RATE = r'\d{1,2}(?:\.\d{1,2})?'

# The VAT summary table is the authoritative figure on this layout. Each row reads
# "<rate name> <rate>% (<rate>%) € <net> € <vat>", and the right-hand totals block is
# interleaved onto the same extracted lines, so match the row rather than the whole line.
VAT_SUMMARY_RE = re.compile(
    r'\((?P<rate>' + RATE + r')%\)\s*€\s*(?P<net>' + MONEY + r')\s*€\s*(?P<vat>' + MONEY + r')'
)

TOTAL_NET_RE = re.compile(r'Total\s+Net\s+(' + MONEY + r')')
TOTAL_VAT_RE = re.compile(r'Total\s+VAT\s+(' + MONEY + r')')
# "TOTAL € 623.00" — the € is what distinguishes it from "Total Net" and "Total VAT"
GRAND_TOTAL_RE = re.compile(r'TOTAL\s*€\s*(' + MONEY + r')')

INVOICE_DATE_RE = re.compile(r'Invoice\s+Date\s+(\d{1,2}/\d{1,2}/\d{4})')
INVOICE_NUMBER_RE = re.compile(r'Invoice\s+Number\s+(\S+)')

# The line item table runs between these two rows. Bounding the scan this way keeps the
# summary rows and the bank details out of the fallback entirely.
ITEM_HEADER_RE = re.compile(r'^Code\s+Description\b')
ITEM_FOOTER_RE = re.compile(r'^VAT\s+Rate\b')

# <code> <description> <qty> <price> [<discount>] <vat %> <net>
# The Discount column is only printed when a line actually carries one.
LINE_ITEM_RE = re.compile(
    r'^(?P<desc>.*?\S)\s+'
    r'(?P<qty>' + MONEY + r')\s+'
    r'(?P<price>' + MONEY + r')\s+'
    r'(?:(?P<discount>' + MONEY + r')\s+)?'
    r'(?P<rate>' + RATE + r')\s+'
    r'(?P<net>' + MONEY + r')$'
)

RATE_KEYS = {'0': '0', '9': '9', '13.5': '13.5', '23': '23'}


def _amount(raw):
    """Convert '1,076.00' into a float."""
    return float(raw.strip().replace(',', ''))


def _rate_key(raw):
    """Normalise a printed rate ('23.00', '13.50', '0.00') to a VAT bucket key."""
    rate = raw.strip().rstrip('%')
    if '.' in rate:
        rate = rate.rstrip('0').rstrip('.')
    if rate == '':
        rate = '0'
    return RATE_KEYS.get(rate)


def _empty_buckets():
    return {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def _parse_summary(text, warnings):
    """Read the VAT summary table: one row per rate, giving net and VAT directly."""
    nets = _empty_buckets()
    vat_amounts = _empty_buckets()
    rows = 0

    for match in VAT_SUMMARY_RE.finditer(text):
        key = _rate_key(match.group('rate'))
        if key is None:
            warnings.append(f"Unrecognised VAT rate {match.group('rate')}% in the summary table")
            continue
        nets[key] += _amount(match.group('net'))
        vat_amounts[key] += _amount(match.group('vat'))
        rows += 1

    return nets, vat_amounts, rows


def _parse_line_items(text, warnings):
    """
    Fallback for an invoice with no readable VAT summary: sum the line nets by their VAT
    column. The invoice does not print VAT per line, so VAT is computed from the rate.
    """
    nets = _empty_buckets()
    rows = 0
    in_table = False

    for line in text.split('\n'):
        line = line.strip()
        if ITEM_HEADER_RE.match(line):
            in_table = True
            continue
        if ITEM_FOOTER_RE.match(line):
            break
        if not in_table or not line:
            continue
        match = LINE_ITEM_RE.match(line)
        if not match:
            continue
        key = _rate_key(match.group('rate'))
        if key is None:
            warnings.append(f"Unrecognised VAT rate {match.group('rate')}% on a line item")
            continue
        nets[key] += _amount(match.group('net'))
        rows += 1

    vat_amounts = {
        '0': 0.0,
        '9': round(nets['9'] * 0.09, 2),
        '13.5': round(nets['13.5'] * 0.135, 2),
        '23': round(nets['23'] * 0.23, 2),
    }
    return nets, vat_amounts, rows


def parse_invoice(text, filename):
    """
    Parser for bean2cup tech support limited invoices (coffee machine parts and servicing).

    One layout, unchanged across the archive since at least 2025-09-18. Line amounts are
    net and the invoice carries an explicit VAT summary table giving net and VAT per rate,
    which is what the parser reads; the line items are only a fallback. Parts are charged
    at 23% and call-out/labour at 13.5%, so most invoices span two rates.
    """
    print(f"[DEBUG] Parsing Bean2Cup invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        date_match = INVOICE_DATE_RE.search(text)
        invoice_date = date_match.group(1) if date_match else 'Not found'
        if date_match:
            # Already DD/MM/YYYY, but zero-pad so the dispatcher's strptime always matches
            day, month, year = date_match.group(1).split('/')
            invoice_date = f"{int(day):02d}/{int(month):02d}/{year}"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        number_match = INVOICE_NUMBER_RE.search(text)
        invoice_number = number_match.group(1) if number_match else 'Not found'
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        nets, vat_amounts, rows = _parse_summary(text, warnings)
        if rows:
            print(f"[DEBUG] VAT summary rows: {rows}", file=sys.stderr)
        else:
            warnings.append('VAT summary table not found; totalled the line items instead')
            nets, vat_amounts, rows = _parse_line_items(text, warnings)
            print(f"[DEBUG] Fell back to {rows} line items", file=sys.stderr)
            if not rows:
                warnings.append('No line items could be read from the invoice either')

        print(f"[DEBUG] Net by rate: {nets}", file=sys.stderr)
        print(f"[DEBUG] VAT by rate: {vat_amounts}", file=sys.stderr)

        net_sum = round(sum(nets.values()), 2)
        vat_sum = round(sum(vat_amounts.values()), 2)

        # Cross-check against the three figures the invoice prints for itself
        total_net_match = TOTAL_NET_RE.search(text)
        if total_net_match and abs(_amount(total_net_match.group(1)) - net_sum) > 0.01:
            warnings.append(
                f"VAT summary nets sum to €{net_sum:.2f} but the invoice states "
                f"€{_amount(total_net_match.group(1)):.2f}"
            )

        total_vat_match = TOTAL_VAT_RE.search(text)
        if total_vat_match and abs(_amount(total_vat_match.group(1)) - vat_sum) > 0.01:
            warnings.append(
                f"VAT summary VAT sums to €{vat_sum:.2f} but the invoice states "
                f"€{_amount(total_vat_match.group(1)):.2f}"
            )

        grand_total_match = GRAND_TOTAL_RE.search(text)
        if grand_total_match:
            total_amount = _amount(grand_total_match.group(1))
            if abs(net_sum + vat_sum - total_amount) > 0.01:
                warnings.append(
                    f"Net €{net_sum:.2f} plus VAT €{vat_sum:.2f} does not reach the invoice "
                    f"total €{total_amount:.2f}"
                )
        else:
            warnings.append('Invoice total not found; using net plus VAT')
            total_amount = round(net_sum + vat_sum, 2)

        print(f"[DEBUG] Total: {total_amount}", file=sys.stderr)

        is_credit_note = total_amount < 0 or 'CREDIT NOTE' in text.upper()

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Bean2Cup',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': is_credit_note,
            'VAT 0%': f"{nets['0']:.2f}",
            'VAT 9%': f"{nets['9']:.2f}",
            'VAT 13.5%': f"{nets['13.5']:.2f}",
            'VAT 23%': f"{nets['23']:.2f}",
            # VAT as printed on the invoice, so the dispatcher does not recompute it
            'VAT Amounts': {rate: round(value, 2) for rate, value in vat_amounts.items()},
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
