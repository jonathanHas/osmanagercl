import re
import sys

# Menton's write their invoices by hand and send photographs of them, so the text this
# parser sees is Tesseract's reading of handwriting and is mostly noise. Every amount
# therefore has to be anchored to something a digit run in that noise cannot imitate.
MONEY = r'-?[\d,]+(?:\.\d{1,2})?'

# "34.5 ells @ €5.80 = €199.00" — the one structure the farm writes consistently. The
# "@ ... =" shape is the anchor here, so the euro signs stay optional.
CALCULATION_RE = re.compile(
    r'[\d.,]+\s*(?:ells?|units?|kg|each)?\s*(?:@|at)\s*€?\s*(' + MONEY + r')'
    r'\s*=\s*€?\s*(' + MONEY + r')',
    re.IGNORECASE
)
# A labelled total, however the OCR mangles the spacing around it
LABELLED_TOTAL_RE = re.compile(r'(?:total|amount\s+due|balance)\s*:?\s*€?\s*(' + MONEY + r')', re.IGNORECASE)

DATE_RE = re.compile(r'(\d{1,2})/(\d{1,2})/(\d{2,4})')


def _amount(raw):
    """Convert '1,234.56' or '232' into a float."""
    return float(raw.replace(',', '').strip())


def parse_invoice(text, filename):
    """
    Parser for Menton's Organic Farm invoices.

    Menton's supply certified organic produce and every one of the 577 invoices in the
    archive is zero-rated, so the whole amount belongs in the 0% bucket. This parser used
    to divide the total by 1.23 and book the result as standard-rated whenever the text did
    not literally say "tax free", inventing input VAT that was never charged and never
    reclaimable.

    It also used to take the first digit run anywhere in the document as the total, which
    on OCR'd handwriting is noise: three archived invoices read as €7.00, €7.00 and €17.00
    against stated totals of €232.00, €232.00 and €58.00. Amounts are now only accepted
    from an anchored position, and a total that cannot be read is reported as a warning so
    the invoice goes to review rather than being created from a number found by accident.
    """
    print(f"[DEBUG] Parsing Menton's Organic Farm invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        # === Date ===
        # Format DD/M/YY or DD/MM/YYYY
        date_match = DATE_RE.search(text)
        invoice_date = "Not found"
        if date_match:
            day, month, year = date_match.groups()
            if len(year) == 2:
                year = f"20{year}"
            invoice_date = f"{day.zfill(2)}/{month.zfill(2)}/{year}"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === Total Amount ===
        total_amount = 0.0

        calc_match = CALCULATION_RE.search(text)
        labelled_match = LABELLED_TOTAL_RE.search(text)

        if calc_match:
            total_amount = _amount(calc_match.group(2))
            print(f"[DEBUG] Found calculation total: €{total_amount}", file=sys.stderr)
        elif labelled_match:
            total_amount = _amount(labelled_match.group(1))
            print(f"[DEBUG] Found labelled total: €{total_amount}", file=sys.stderr)
        else:
            # A bare amount is not accepted, not even one carrying a euro sign: Tesseract
            # reads "€5/2" out of the handwriting on the 2025-04-17 invoice, whose stated
            # total is €58.00. Reporting nothing is safer than reporting €5.00.
            warnings.append(
                "No total could be read from the invoice; Menton's send photographs of "
                "handwritten invoices and this one needs entering by hand"
            )
            print("[DEBUG] No anchored total found.", file=sys.stderr)

        # Menton's are certified organic produce and have never charged VAT. If an invoice
        # ever does, the handwriting gives no rate breakdown to work from, so it is flagged
        # rather than guessed at.
        if re.search(r'\bvat\b|\btax\b', text, re.IGNORECASE) and not re.search(
            r'(tax\s*free|zero\s*rated|vat\s*exempt|no\s*vat)', text, re.IGNORECASE
        ):
            warnings.append(
                "Invoice mentions VAT; Menton's supply zero-rated produce and the rate "
                "needs confirming by hand"
            )

        parsed_data = {
            'Filename': filename,
            'Supplier': "Menton's Organic Farm",
            'Invoice Date': invoice_date,
            'Tax Free': True,
            'Credit Note': total_amount < 0,
            'VAT 0%': f"{total_amount:.2f}",
            'VAT 9%': "0.00",
            'VAT 13.5%': "0.00",
            'VAT 23%': "0.00",
            # Stated rather than computed, so the dispatcher keeps the invoice's own figures
            'VAT Amounts': {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0},
            'Total': f"{total_amount:.2f}",
            'Total_VAT': 0.0,
        }
        if warnings:
            parsed_data['Parse_Warnings'] = warnings

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
