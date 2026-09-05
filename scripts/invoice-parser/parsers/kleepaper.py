import re
import sys

# Klee Paper are Irish and print UK/US format, so the comma is always a thousands
# separator. Allowing the pattern to span it is the whole point: "1,278.70" read with
# (\d+\.\d{2}) matches the fragment "278.70" out of the middle of the number.
MONEY = r'-?[\d,]+\.\d{2}'
RATE = r'\d{1,2}\.\d{2}'

INVOICE_DATE_RE = re.compile(r'Invoice Date\s+(\d{4}-\d{2}-\d{2})')
INVOICE_REF_RE = re.compile(r'Our Ref\.\s*(\S+)')

# The totals block interleaves three side-by-side tables onto single extracted lines:
#   Package Summary   VAT Summary                  Order Summary
#   Item Value        Code Nett Rate Total         Item Value
#   Items 11.00       S23 1,278.70 23.00 294.12    Nett 1,278.70
#   Weight Kgs 144.90                              VAT 294.12
#                                                  Total 1,572.82
# so everything here matches within the block rather than anchoring to a line.
SUMMARY_START_RE = re.compile(r'Package Summary|VAT Summary')

# <code> <nett> <rate> <vat> — the rate is captured, never assumed, so a row at any rate
# contributes instead of being skipped.
VAT_ROW_RE = re.compile(
    r'\b[A-Z]\d+\s+(?P<nett>' + MONEY + r')\s+(?P<rate>' + RATE + r')\s+(?P<vat>' + MONEY + r')'
)

ORDER_NETT_RE = re.compile(r'\bNett\s+(' + MONEY + r')')
ORDER_VAT_RE = re.compile(r'\bVAT\s+(' + MONEY + r')')
ORDER_TOTAL_RE = re.compile(r'\bTotal\s+(' + MONEY + r')')
INVOICE_TOTAL_RE = re.compile(r'Total value of this invoice:\s*(' + MONEY + r')')

RATE_KEYS = {0.0: '0', 9.0: '9', 13.5: '13.5', 23.0: '23'}
RATE_TOLERANCE = 1.0


def _amount(raw):
    """Convert '1,278.70' into a float."""
    return float(raw.replace(',', ''))


def _empty_buckets():
    return {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def _summary_region(text):
    """The totals block, or the whole document if its header cannot be found."""
    start = SUMMARY_START_RE.search(text)
    return text[start.start():] if start else None


def _bucket_for(rate, nett, vat, warnings):
    """
    Which VAT bucket a summary row belongs to.

    The rate as printed is used when it is one we recognise; otherwise the rate actually
    charged is derived from the figures and snapped onto the nearest known one. Skipping
    the row instead would silently drop its money.
    """
    if rate in RATE_KEYS:
        return RATE_KEYS[rate]

    if nett:
        effective = vat / nett * 100
        nearest = min(RATE_KEYS, key=lambda known: abs(known - effective))
        if abs(nearest - effective) <= RATE_TOLERANCE:
            warnings.append(
                f"Invoice prints a VAT rate of {rate:.2f}% but charges {effective:.2f}% "
                f"(€{vat:.2f} on €{nett:.2f}); treated as {RATE_KEYS[nearest]}%"
            )
            return RATE_KEYS[nearest]

    warnings.append(
        f"Unrecognised VAT rate {rate:.2f}% on €{nett:.2f} with €{vat:.2f} of VAT; "
        f"treated as 0% and needs checking by hand"
    )
    return '0'


def parse_invoice(text, filename):
    """
    Parser for Klee Paper (ecoLand, Dublin) invoices.

    The VAT summary states net and VAT per rate, and Klee Paper round VAT per line, so the
    stated VAT does not equal round(net x rate, 2) on the aggregate — 10 of the 23 invoices
    in the archive differ by a cent or two. The stated figures are therefore returned as
    'VAT Amounts' so the dispatcher uses them verbatim rather than recomputing.
    """
    print(f"[DEBUG] Parsing Klee Paper invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        date_match = INVOICE_DATE_RE.search(text)
        if date_match:
            year, month, day = date_match.group(1).split('-')
            invoice_date = f"{day}/{month}/{year}"
        else:
            invoice_date = 'Not found'
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        ref_match = INVOICE_REF_RE.search(text)
        invoice_number = ref_match.group(1) if ref_match else 'Not found'
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        region = _summary_region(text)
        if region is None:
            warnings.append('Totals block not found; matched across the whole document')
            region = text

        nets = _empty_buckets()
        vat_amounts = _empty_buckets()
        rows = VAT_ROW_RE.findall(region)
        for nett_raw, rate_raw, vat_raw in rows:
            nett, vat = _amount(nett_raw), _amount(vat_raw)
            key = _bucket_for(float(rate_raw), nett, vat, warnings)
            nets[key] += nett
            vat_amounts[key] += vat
        nets = {rate: round(value, 2) for rate, value in nets.items()}
        vat_amounts = {rate: round(value, 2) for rate, value in vat_amounts.items()}

        print(f"[DEBUG] {len(rows)} VAT summary rows", file=sys.stderr)
        print(f"[DEBUG] Net by rate: {nets}", file=sys.stderr)
        print(f"[DEBUG] VAT by rate: {vat_amounts}", file=sys.stderr)
        if not rows:
            warnings.append('No VAT summary rows could be read from the invoice')

        net_sum = round(sum(nets.values()), 2)
        vat_sum = round(sum(vat_amounts.values()), 2)

        # The Order Summary restates net, VAT and total; check the rows against all three
        order_nett = ORDER_NETT_RE.search(region)
        if order_nett and abs(_amount(order_nett.group(1)) - net_sum) > 0.01:
            warnings.append(
                f"VAT summary rows total €{net_sum:.2f} net but the order summary states "
                f"€{_amount(order_nett.group(1)):.2f}"
            )

        order_vat = ORDER_VAT_RE.search(region)
        if order_vat and abs(_amount(order_vat.group(1)) - vat_sum) > 0.01:
            warnings.append(
                f"VAT summary rows total €{vat_sum:.2f} VAT but the order summary states "
                f"€{_amount(order_vat.group(1)):.2f}"
            )

        stated_total = INVOICE_TOTAL_RE.search(text) or ORDER_TOTAL_RE.search(region)
        if stated_total:
            total_amount = _amount(stated_total.group(1))
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
            'Supplier': 'Klee Paper',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': False,
            # Keyed off the total alone: the footer boilerplate mentions "credit claims",
            # so matching the word "credit" in the text would flag every invoice.
            'Credit Note': total_amount < 0,
            'VAT 0%': f"{nets['0']:.2f}",
            'VAT 9%': f"{nets['9']:.2f}",
            'VAT 13.5%': f"{nets['13.5']:.2f}",
            'VAT 23%': f"{nets['23']:.2f}",
            # VAT as printed, since Klee Paper round it per line
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
