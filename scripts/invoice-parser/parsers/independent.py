import re
import sys

# Independent print UK/US style thousands separators: "1,354.42"
MONEY = r'[\d,]+\.\d{2}'
RATE = r'\d{1,2}\.\d{2}'

# The VAT summary block. Its header is what bounds the row scan, so that a permissive row
# regex cannot pick up a product line by accident:
#   Tax Code Rate Taxable Tax  DRS Totals        Gross Total: 2,241.03
#   0   0.00  1,354.42  0.00   DRS 15c 15 2.25   Tax:           181.70
#   1  23.00    652.44 150.09  DRS 25c  0 0.00
#   2  13.50    234.17  31.61                    Total:       2,422.73
SUMMARY_HEADER_RE = re.compile(r'Tax\s*Code.*\bRate\b.*\bTaxable\b')
SUMMARY_END_RE = re.compile(r'VAT\s*Reg\s*No')

# <tax code> <rate> <taxable> <tax>
ROW_RE = re.compile(r'(?m)^\s*\d+\s+(' + RATE + r')\s+(' + MONEY + r')\s+(' + MONEY + r')')
# Scanned invoices drop the tax code column. Only used when the summary header cannot be
# located, so this one stays pinned to the rates we know to avoid matching product lines.
ROW_OCR_RE = re.compile(r'(?m)^(0\.00|9\.00|13\.50|23\.00)\s+(' + MONEY + r')\s+(' + MONEY + r')')

GROSS_TOTAL_RE = re.compile(r'Gross\s+Total:\s*(' + MONEY + r')')
STATED_TAX_RE = re.compile(r'\bTax:\s*(' + MONEY + r')')
# "Gross Total:" ends in "Total:", so the grand total has to exclude that prefix
TOTAL_RE = re.compile(r'(?<!Gross )\bTotal:\s*(' + MONEY + r')')

INVOICE_NUMBER_RE = re.compile(r'Invoice\s*No[:.]?\s*(\S+)')
INVOICE_DATE_RE = re.compile(r'Invoice\s+Date[:\s]*(\d{2}/\d{2}/\d{4})')

# Bucket key by rate as a number, so a mis-printed rate can be snapped onto the nearest
RATE_KEYS = {0.0: '0', 9.0: '9', 13.5: '13.5', 23.0: '23'}
# How far a derived rate may sit from a known one before we refuse to guess
RATE_TOLERANCE = 1.0


def _amount(raw):
    """Convert '1,354.42' into a float. Independent use comma for thousands throughout."""
    return float(raw.replace(',', ''))


def _empty_buckets():
    return {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def _summary_region(text):
    """
    Return just the VAT summary block, or None when its header cannot be found.

    Bounding the scan is what lets the row regex accept any rate: product lines sit above
    the header and so can never be mistaken for a summary row.
    """
    header = SUMMARY_HEADER_RE.search(text)
    if not header:
        return None
    region = text[header.start():]
    end = SUMMARY_END_RE.search(region)
    return region[:end.start()] if end else region


def _bucket_for(rate, taxable, tax, warnings):
    """
    Decide which VAT bucket a summary row belongs to.

    Normally the printed rate is one we recognise. One invoice in the archive prints its
    standard-rate row as 22.50 while charging 23% (37.90 on 164.75), so when the printed
    rate is unknown the rate actually charged is derived from the figures and snapped onto
    the nearest known one. Dropping the row instead would silently lose the money.
    """
    if rate in RATE_KEYS:
        return RATE_KEYS[rate]

    if taxable:
        effective = tax / taxable * 100
        nearest = min(RATE_KEYS, key=lambda known: abs(known - effective))
        if abs(nearest - effective) <= RATE_TOLERANCE:
            warnings.append(
                f"Invoice prints a VAT rate of {rate:.2f}% but charges {effective:.2f}% "
                f"(€{tax:.2f} on €{taxable:.2f}); treated as {RATE_KEYS[nearest]}%"
            )
            return RATE_KEYS[nearest]

    warnings.append(
        f"Unrecognised VAT rate {rate:.2f}% on €{taxable:.2f} with €{tax:.2f} of tax; "
        f"treated as 0% and needs checking by hand"
    )
    return '0'


def _parse_summary(text, warnings):
    """Read net and VAT per rate straight out of the summary table."""
    nets = _empty_buckets()
    vat_amounts = _empty_buckets()

    region = _summary_region(text)
    if region is not None:
        rows = ROW_RE.findall(region)
    else:
        warnings.append('VAT summary header not found; matched rate rows across the whole document')
        rows = ROW_RE.findall(text) or ROW_OCR_RE.findall(text)

    for rate, taxable, tax in rows:
        taxable_value = _amount(taxable)
        tax_value = _amount(tax)
        key = _bucket_for(float(rate), taxable_value, tax_value, warnings)
        nets[key] += taxable_value
        vat_amounts[key] += tax_value

    return (
        {rate: round(value, 2) for rate, value in nets.items()},
        {rate: round(value, 2) for rate, value in vat_amounts.items()},
        len(rows),
    )


def parse_invoice(text, filename):
    """
    Parser for Independent Irish Health Foods invoices.

    The invoice states net and VAT per rate in its summary table, and Independent round
    VAT per line, so the stated Tax column does not equal round(net x rate, 2) on the
    aggregate — on the September 2026 invoice the difference is 3c. The stated figures are
    therefore returned as 'VAT Amounts' so the dispatcher uses them verbatim rather than
    recomputing.
    """
    print(f"[DEBUG] Parsing Independent Irish Health Foods invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        number_match = INVOICE_NUMBER_RE.search(text)
        invoice_number = number_match.group(1) if number_match else 'Not found'
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        date_match = INVOICE_DATE_RE.search(text)
        invoice_date = date_match.group(1) if date_match else 'Not found'
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        nets, vat_amounts, rows = _parse_summary(text, warnings)
        print(f"[DEBUG] {rows} summary rows", file=sys.stderr)
        print(f"[DEBUG] Net by rate: {nets}", file=sys.stderr)
        print(f"[DEBUG] VAT by rate: {vat_amounts}", file=sys.stderr)
        if not rows:
            warnings.append('No VAT summary rows could be read from the invoice')

        net_sum = round(sum(nets.values()), 2)
        vat_sum = round(sum(vat_amounts.values()), 2)

        # The invoice states all three totals, so check the rows against every one of them
        gross_match = GROSS_TOTAL_RE.search(text)
        if gross_match and abs(_amount(gross_match.group(1)) - net_sum) > 0.01:
            warnings.append(
                f"Summary rows total €{net_sum:.2f} net but the invoice states "
                f"€{_amount(gross_match.group(1)):.2f}"
            )

        stated_tax_match = STATED_TAX_RE.search(text)
        if stated_tax_match and abs(_amount(stated_tax_match.group(1)) - vat_sum) > 0.01:
            warnings.append(
                f"Summary rows total €{vat_sum:.2f} VAT but the invoice states "
                f"€{_amount(stated_tax_match.group(1)):.2f}"
            )

        total_match = TOTAL_RE.search(text)
        if total_match:
            total_amount = _amount(total_match.group(1))
            if abs(net_sum + vat_sum - total_amount) > 0.01:
                warnings.append(
                    f"Net €{net_sum:.2f} plus VAT €{vat_sum:.2f} does not reach the invoice "
                    f"total €{total_amount:.2f}"
                )
        else:
            warnings.append('Invoice total not found; using net plus VAT')
            total_amount = round(net_sum + vat_sum, 2)
        print(f"[DEBUG] Total: {total_amount}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Independent',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': total_amount < 0,
            'VAT 0%': f"{nets['0']:.2f}",
            'VAT 9%': f"{nets['9']:.2f}",
            'VAT 13.5%': f"{nets['13.5']:.2f}",
            'VAT 23%': f"{nets['23']:.2f}",
            # VAT as printed, since Independent round it per line
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
