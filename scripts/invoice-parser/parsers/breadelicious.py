import re
import sys
import datetime

# Amounts may be negative and are shown in parentheses on the Xero layout: (4.80)
NUMBER = r'\(?-?[\d,]+\.\d{2}\)?'

# <description> <qty> <unit price> [<tax>] <amount>
# The tax column is absent on zero-quantity lines, and "Tax on Sales" wraps so that
# only "Taxon" lands on the item line (pdfplumber strips intra-word spaces).
LINE_ITEM_RE = re.compile(
    r'^(?P<desc>.*?)\s+'
    r'(?P<qty>' + NUMBER + r')\s+'
    r'(?P<price>' + NUMBER + r')\s+'
    r'(?:(?P<tax>\d+(?:\.\d+)?%|Taxon|ZeroRated|Exempt|NoVAT)\s+)?'
    r'(?P<amount>' + NUMBER + r')$'
)

# "INCLUDES SALES ON TAX 13.5%  2.86" — the VAT already contained in the line amounts
INCLUDES_TAX_RE = re.compile(
    r'INCLUDES\s*SALES\s*ON\s*TAX\s*(\d+(?:\.\d+)?)%\s*(' + NUMBER + r')',
    re.IGNORECASE
)

TOTAL_RE = re.compile(r'TOTAL\s*EUR\s*(' + NUMBER + r')', re.IGNORECASE)

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


def _parse_xero_date(text):
    """Xero prints 'InvoiceDate' with the date on the following line: '9Aug2026'."""
    match = re.search(r'InvoiceDate.*?\n(\d{1,2}[A-Za-z]{3}\d{4})', text, re.DOTALL)
    if not match:
        match = re.search(r'InvoiceDate\s*(\d{1,2}[A-Za-z]{3}\d{4})', text)
    if not match:
        return 'Not found'
    raw = match.group(1)
    try:
        return datetime.datetime.strptime(raw, '%d%b%Y').strftime('%d/%m/%Y')
    except ValueError:
        return raw


def _parse_xero(text, filename, warnings):
    """
    Xero layout, in use since 2026-07-06.

    Line amounts are VAT-INCLUSIVE and the VAT contained in them is stated once per
    rate as "INCLUDES SALES ON TAX 13.5%". That stated figure is rounded per line by
    Xero, so it must be used verbatim rather than recomputed from the aggregate net.
    """
    invoice_date = _parse_xero_date(text)
    print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

    inv_match = re.search(r'INV-(\d+)', text)
    invoice_number = f"INV-{inv_match.group(1)}" if inv_match else 'Not found'
    print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

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
        if stripped.upper().startswith('INCLUDES') or stripped.upper().startswith('TOTALEUR'):
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

    Two layouts are in circulation: Fakturownia up to 2026-06-28, and Xero from
    2026-07-06 onwards. The Xero one prices everything VAT-inclusive.
    """
    print(f"[DEBUG] Parsing BreaDelicious invoice: {filename}", file=sys.stderr)

    try:
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
