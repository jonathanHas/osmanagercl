import re
import sys
import datetime

# Amounts may be negative and are shown in parentheses on the Xero layout: (4.80)
NUMBER = r'\(?-?[\d,]+\.\d{2}\)?'

# Quantities are 2dp on the 2026-07 Xero template (6.00) but bare on the 2026-10 one (12)
QUANTITY = r'\(?-?[\d,]+(?:\.\d+)?\)?'

# <description> <qty> <unit price> [<tax>] <amount>
# The tax column is absent on zero-quantity lines, and "Tax on Sales" wraps so that
# only "Taxon" lands on the item line (pdfplumber strips intra-word spaces).
LINE_ITEM_RE = re.compile(
    r'^(?P<desc>.*?)\s+'
    r'(?P<qty>' + QUANTITY + r')\s+'
    r'(?P<price>' + NUMBER + r')\s+'
    r'(?:(?P<tax>\d+(?:\.\d+)?%|Taxon|ZeroRated|Exempt|NoVAT)\s+)?'
    r'(?P<amount>' + NUMBER + r')$'
)

# The VAT already contained in the line amounts, matched against space-stripped text:
#   2026-07 template: "INCLUDES SALES ON TAX 13.5%  2.86"
#   2026-10 template: "Includes tax 13.5% of 5.95"
INCLUDES_TAX_RE = re.compile(
    r'INCLUDES\s*(?:SALES\s*ON\s*)?TAX\s*(\d+(?:\.\d+)?)%\s*(?:OF\s*)?(' + NUMBER + r')',
    re.IGNORECASE
)

# "TOTAL EUR 96.00" (2026-07) or "Total 171.40" (2026-10), on a line of its own so that
# "Subtotal" never matches. Matched against space-stripped text.
TOTAL_RE = re.compile(r'^TOTAL(?:EUR)?(' + NUMBER + r')$', re.IGNORECASE | re.MULTILINE)

# The 2026-10 template sets the hyphen in an icon font, so pdfplumber returns a
# private-use character in its place: "INV\ue0880467". parse_invoice() normalises it
# to "-" first (see ICON_MINUS); the optional separator still covers older text.
INVOICE_NUMBER_RE = re.compile(r'\bINV[^\w\s]?(\d+)')

# "11 Oct 2026" on the 2026-10 template; month map rather than %b so "Sept" parses
DATE_RE = re.compile(r'\b(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})\b')
MONTHS = {
    'jan': 1, 'feb': 2, 'mar': 3, 'apr': 4, 'may': 5, 'jun': 6,
    'jul': 7, 'aug': 8, 'sep': 9, 'oct': 10, 'nov': 11, 'dec': 12,
}

# Zero-width characters that some extractors leave in front of the header values
INVISIBLE_RE = re.compile('[\u200b-\u200d\ufeff]')

# The 2026-10 template also sets the minus sign of credit lines in that icon font:
# "1 \ue0884.80 0% \ue0884.80" is a -4.80 line
ICON_MINUS = '\ue088'

# Map a rate as printed on the invoice onto the four buckets Laravel understands
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
        # "Taxon"(Sales), "ZeroRated", "Exempt", "NoVAT" — none carry VAT
        return '0'
    rate = match.group(1)
    if rate.endswith('.0'):
        rate = rate[:-2]
    return RATE_KEYS.get(rate, '0')


def _empty_buckets():
    return {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def _parse_xero_date(text, reference_match):
    """
    The 2026-07 template prints 'InvoiceDate' with the date on the following line:
    '9Aug2026'. The 2026-10 one has an 'Amount due / Due date / Issue date / Invoice
    number' header row with '18 Oct 2026  11 Oct 2026  INV-0467' beneath it, so the
    issue date is the last date before the invoice number, falling back to the earliest
    date in the document.
    """
    match = re.search(r'InvoiceDate.*?\n(\d{1,2}[A-Za-z]{3}\d{4})', text, re.DOTALL)
    if not match:
        match = re.search(r'InvoiceDate\s*(\d{1,2}[A-Za-z]{3}\d{4})', text)
    if match:
        raw = match.group(1)
        try:
            return datetime.datetime.strptime(raw, '%d%b%Y').strftime('%d/%m/%Y')
        except ValueError:
            return raw

    dates = []
    for date_match in DATE_RE.finditer(text):
        key = date_match.group(2)[:3].lower()
        if key not in MONTHS:
            continue
        try:
            parsed = datetime.date(int(date_match.group(3)), MONTHS[key], int(date_match.group(1)))
        except ValueError:
            continue
        dates.append((date_match.start(), parsed))

    if not dates:
        return 'Not found'
    if reference_match:
        before = [date for position, date in dates if position < reference_match.start()]
        if before:
            return before[-1].strftime('%d/%m/%Y')
    return min(date for _, date in dates).strftime('%d/%m/%Y')


def _parse_xero(text, filename, warnings):
    """
    Xero layouts: the original template from 2026-07-06, and Xero's newer one seen from
    2026-10-11 (INV-0467). Both share the same column order and arithmetic.

    Line amounts are VAT-INCLUSIVE and the VAT contained in them is stated once per
    rate as "INCLUDES SALES ON TAX 13.5%" / "Includes tax 13.5% of". That stated figure
    is rounded per line by Xero, so it must be used verbatim rather than recomputed from
    the aggregate net.
    """
    inv_match = INVOICE_NUMBER_RE.search(text)
    invoice_number = f"INV-{inv_match.group(1)}" if inv_match else 'Not found'
    print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

    invoice_date = _parse_xero_date(text, inv_match)
    print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

    total_match = TOTAL_RE.search(text.replace(' ', ''))
    total_amount = _amount(total_match.group(1)) if total_match else 0.0
    print(f"[DEBUG] Total: {total_amount}", file=sys.stderr)

    # VAT stated on the invoice, per rate
    vat_amounts = _empty_buckets()
    for rate, amount in INCLUDES_TAX_RE.findall(text.replace(' ', '')):
        vat_amounts[_rate_key(f"{rate}%")] += _amount(amount)
    print(f"[DEBUG] Stated VAT: {vat_amounts}", file=sys.stderr)

    # Gross line amounts, bucketed by the tax column
    gross = _empty_buckets()
    unlabelled_gross = 0.0
    for line in text.split('\n'):
        line = line.strip()
        if not line:
            continue
        stripped = line.replace(' ', '')
        if stripped.upper().startswith(('INCLUDES', 'TOTAL', 'SUBTOTAL')):
            continue
        match = LINE_ITEM_RE.match(line)
        if not match:
            continue
        amount = _amount(match.group('amount'))
        if match.group('tax') is None:
            unlabelled_gross += amount
        gross[_rate_key(match.group('tax'))] += amount

    gross = {rate: round(value, 2) for rate, value in gross.items()}
    print(f"[DEBUG] Gross by rate: {gross}", file=sys.stderr)

    # Every line amount is gross, so the buckets must reconcile to TOTAL EUR.
    line_sum = round(sum(gross.values()), 2)
    if total_amount and abs(line_sum - total_amount) > 0.01:
        warnings.append(
            f"Line items sum to €{line_sum:.2f} but invoice total is €{total_amount:.2f}; "
            f"assigning the difference to 0%"
        )
        rated_sum = round(sum(v for r, v in gross.items() if r != '0'), 2)
        gross['0'] = round(total_amount - rated_sum, 2)
    elif not total_amount:
        warnings.append('TOTAL EUR not found on invoice')
        total_amount = line_sum

    if round(unlabelled_gross, 2) != 0.0:
        warnings.append(
            f"€{unlabelled_gross:.2f} of line items had no tax rate and were treated as 0%"
        )

    # Net = gross - the VAT the invoice says is included in it
    nets = {rate: round(gross[rate] - vat_amounts[rate], 2) for rate in gross}
    print(f"[DEBUG] Net by rate: {nets}", file=sys.stderr)

    return {
        'Invoice Date': invoice_date,
        'Invoice Number': invoice_number,
        'Credit Note': total_amount < 0,
        'nets': nets,
        'vat_amounts': vat_amounts,
        'Total': total_amount,
    }


def _parse_fakturownia(text, filename, warnings):
    """
    Fakturownia layout, used up to 2026-06-28.

    Carries an explicit VAT summary table whose rows read Net / Rate / VAT / Gross.
    """
    date_match = re.search(r"Issue date:\s*(\d{4}-\d{2}-\d{2})", text)
    invoice_date = 'Not found'
    if date_match:
        year, month, day = date_match.group(1).split('-')
        invoice_date = f"{day}/{month}/{year}"
    print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

    invoice_number = 'Not found'
    num_match = re.search(r'Invoice No\.\s*\n?\s*(\d{4}/\d+)', text)
    if num_match:
        invoice_number = num_match.group(1)
    print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

    nets = _empty_buckets()
    vat_amounts = _empty_buckets()

    # Net -> Rate -> VAT -> Gross, on one line
    summary_matches = re.findall(
        r'(?<!\d)(\d{1,6}[.,]\d{2})\s+(0|9|13\.5|23)\s+(\d{1,6}[.,]\d{2})\s+(\d{1,6}[.,]\d{2})',
        text
    )
    print(f"[DEBUG] VAT Summary Matches: {summary_matches}", file=sys.stderr)

    for net, rate, vat_amount, gross in summary_matches:
        try:
            nets[rate] = float(net.replace(',', ''))
            vat_amounts[rate] = float(vat_amount.replace(',', ''))
        except ValueError as error:
            print(f"[DEBUG] Skipping malformed VAT line: {net}, {rate} — {error}", file=sys.stderr)

    total_amount = round(sum(nets.values()) + sum(vat_amounts.values()), 2)
    total_match = re.search(r'Total gross price EUR\s+([\d,]+\.\d{2})', text)
    if total_match:
        stated_total = float(total_match.group(1).replace(',', ''))
        if abs(stated_total - total_amount) > 0.01:
            warnings.append(
                f"VAT summary sums to €{total_amount:.2f} but invoice total is €{stated_total:.2f}"
            )
        total_amount = stated_total

    return {
        'Invoice Date': invoice_date,
        'Invoice Number': invoice_number,
        'Credit Note': total_amount < 0,
        'nets': nets,
        'vat_amounts': vat_amounts,
        'Total': total_amount,
    }


def parse_invoice(text, filename):
    """
    Parser for BreaDelicious Organic Bakery invoices.

    Layouts in circulation: Fakturownia up to 2026-06-28, then Xero from 2026-07-06,
    whose template changed again by 2026-10-11. Both Xero templates price everything
    VAT-inclusive. Only Fakturownia prints "Issue date:" with a colon.
    """
    print(f"[DEBUG] Parsing BreaDelicious invoice: {filename}", file=sys.stderr)

    try:
        text = INVISIBLE_RE.sub('', text).replace(ICON_MINUS, '-')
        warnings = []
        if 'Issue date:' in text:
            print("[DEBUG] Layout: Fakturownia (legacy)", file=sys.stderr)
            result = _parse_fakturownia(text, filename, warnings)
        else:
            print("[DEBUG] Layout: Xero", file=sys.stderr)
            result = _parse_xero(text, filename, warnings)

        nets = result['nets']
        vat_amounts = result['vat_amounts']

        parsed_data = {
            'Filename': filename,
            'Supplier': 'BreaDelicious',
            'Invoice Number': result['Invoice Number'],
            'Invoice Date': result['Invoice Date'],
            'Tax Free': False,
            'Credit Note': result['Credit Note'],
            'VAT 0%': f"{nets['0']:.2f}",
            'VAT 9%': f"{nets['9']:.2f}",
            'VAT 13.5%': f"{nets['13.5']:.2f}",
            'VAT 23%': f"{nets['23']:.2f}",
            # VAT as stated on the invoice, so the dispatcher does not recompute it
            'VAT Amounts': {rate: round(value, 2) for rate, value in vat_amounts.items()},
            'Total': f"{result['Total']:.2f}",
            'Total_VAT': round(sum(vat_amounts.values()), 2),
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
