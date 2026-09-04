import re
import sys

# Amounts are printed with the euro sign and the minus ahead of it: "€9.16", "-€35.24"
EURO = r'-?€\s*-?[\d,]+\.\d{2}'
QTY = r'-?[\d,]+(?:\.\d+)?'

INVOICE_NUMBER_RE = re.compile(r'Invoice\s+No:?\s*(\S+)')
INVOICE_DATE_RE = re.compile(r'Invoice\s+Date:?\s*(\d{1,2}/\d{1,2}/\d{4})')

# The totals block is interleaved onto the payment-details lines, so these match the label
# wherever it falls rather than anchoring to the start of a line:
#   "Payment Methods                      Subtotal €360.80"
#   "IBAN IE97 AIBK ...                   Total    €360.80"
SUBTOTAL_RE = re.compile(r'Subtotal\s*(' + EURO + r')')
# \b stops "Total" matching inside "Subtotal"; the header's "Line Total" carries no amount
TOTAL_RE = re.compile(r'\bTotal\s*(' + EURO + r')')
# The Tax row is normally printed with no amount at all — Meadow & Moss charge no VAT
TAX_RE = re.compile(r'\bTax\b\s*(' + EURO + r')')

# The item table runs between these two; the Subtotal shares a line with the first
# payment-details row, which is where the table ends.
ITEM_HEADER_RE = re.compile(r'^Item\b.*\bLine\s+Total\b')

# <item> <delivery date> <qty> <unit price> <line total>
# Anchoring on the trailing pair of euro amounts means the delivery date can be absorbed
# into the description, so its formatting ("6 August 2026" vs "02 July 2026") is irrelevant.
LINE_ITEM_RE = re.compile(
    r'^(?P<desc>.*?\S)\s+'
    r'(?P<qty>' + QTY + r')\s+'
    r'(?P<price>' + EURO + r')\s+'
    r'(?P<total>' + EURO + r')$'
)


def _amount(raw):
    """Convert '€54.96' or '-€35.24' into a float."""
    return float(raw.replace('€', '').replace(',', '').replace(' ', ''))


def _empty_buckets():
    return {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def _collect_line_items(text):
    """Sum the line totals between the item header and the Subtotal row."""
    total = 0.0
    rows = 0
    in_table = False

    for line in text.split('\n'):
        line = line.strip()
        if ITEM_HEADER_RE.match(line):
            in_table = True
            continue
        if 'Subtotal' in line:
            break
        if not in_table or not line:
            continue
        match = LINE_ITEM_RE.match(line)
        if not match:
            continue
        rows += 1
        total += _amount(match.group('total'))

    return round(total, 2), rows


def parse_invoice(text, filename):
    """
    Parser for Meadow & Moss invoices (cut flower bouquets, delivered weekly).

    One layout across the archive since 2026-07-23. The supplier is not VAT registered —
    no VAT number appears anywhere on the invoice and the Tax row is printed empty, with
    Subtotal equal to Total — so the invoice is tax free and the whole amount belongs in
    the 0% bucket. Returned bouquets appear as negative lines within an ordinary invoice,
    which is not the same thing as a credit note.
    """
    print(f"[DEBUG] Parsing Meadow & Moss invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        number_match = INVOICE_NUMBER_RE.search(text)
        invoice_number = number_match.group(1) if number_match else 'Not found'
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        date_match = INVOICE_DATE_RE.search(text)
        if date_match:
            day, month, year = date_match.group(1).split('/')
            invoice_date = f"{int(day):02d}/{int(month):02d}/{year}"
        else:
            invoice_date = 'Not found'
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        line_sum, rows = _collect_line_items(text)
        print(f"[DEBUG] {rows} line items summing to {line_sum}", file=sys.stderr)
        if not rows:
            warnings.append('No line items could be read from the invoice')

        subtotal_match = SUBTOTAL_RE.search(text)
        subtotal = _amount(subtotal_match.group(1)) if subtotal_match else None
        if subtotal is not None and abs(subtotal - line_sum) > 0.01:
            warnings.append(
                f"Line items sum to €{line_sum:.2f} but the invoice subtotal is €{subtotal:.2f}"
            )

        total_match = TOTAL_RE.search(text)
        if total_match:
            total_amount = _amount(total_match.group(1))
        elif subtotal is not None:
            warnings.append('Invoice total not found; using the subtotal')
            total_amount = subtotal
        else:
            warnings.append('Invoice total not found; using the sum of the line items')
            total_amount = line_sum
        print(f"[DEBUG] Total: {total_amount}", file=sys.stderr)

        # Meadow & Moss charge no VAT, so the Tax row is printed empty and Subtotal equals
        # Total. If that ever stops being true we cannot tell which rate applies, so flag
        # the invoice for review rather than guess.
        tax_match = TAX_RE.search(text)
        if tax_match and abs(_amount(tax_match.group(1))) > 0.005:
            warnings.append(
                f"Invoice states €{_amount(tax_match.group(1)):.2f} of tax; the VAT rate "
                f"needs confirming by hand"
            )
        if subtotal is not None and abs(subtotal - total_amount) > 0.01:
            warnings.append(
                f"Subtotal €{subtotal:.2f} differs from total €{total_amount:.2f}; "
                f"this invoice appears to carry VAT and needs checking by hand"
            )

        nets = _empty_buckets()
        nets['0'] = total_amount

        # Individual returned bouquets show as negative lines ("Credit Lrg Bouq -1"), which
        # does not make the document a credit note — only a negative invoice total does.
        is_credit_note = total_amount < 0 or 'CREDIT NOTE' in text.upper()

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Meadow & Moss',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': True,
            'Credit Note': is_credit_note,
            'VAT 0%': f"{nets['0']:.2f}",
            'VAT 9%': f"{nets['9']:.2f}",
            'VAT 13.5%': f"{nets['13.5']:.2f}",
            'VAT 23%': f"{nets['23']:.2f}",
            # Stated rather than computed, so the dispatcher keeps the invoice's own total
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
