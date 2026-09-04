import re
import sys
import unicodedata
from datetime import datetime

# Dynamis print thousands with a plain space and the decimal separator differs between
# their two layouts — "1 908.62" on the legacy one, "1 844,32" on the current one — so the
# pattern accepts either and _amount() works out which is which.
MONEY = r'-?\d+(?: \d{3})*[.,]\d{2}'
# Horizontal whitespace only: on a multi-page legacy invoice every page repeats the totals
# labels with no figures, and \s+ would run past the newline onto the next label.
HSPACE = r'[^\S\n]'

# --- legacy layout (GROUPE DYNAMIS SAS, French) ---
LEGACY_MARKER = 'NET A PAYER'
LEGACY_TOTAL_RE = re.compile(r'NET A PAYER' + HSPACE + r'*EUR' + HSPACE + r'*(' + MONEY + r')')
LEGACY_HT_RE = re.compile(r'TOTAL HT' + HSPACE + r'+(' + MONEY + r')')
LEGACY_TVA_RE = re.compile(r'TOTAL TVA' + HSPACE + r'+(' + MONEY + r')')
# Credit notes head with "AVOIR N° 692317 DU 19/05/2026" in place of "FACTURE N° ...".
# Matched case-sensitively so the body line "Avoir sur Facture 691185" cannot win instead.
LEGACY_REF_RE = re.compile(r'(?:FACTURE|AVOIR)\s*N°\s*(\S+)')
LEGACY_DATE_RE = re.compile(r'(?:FACTURE|AVOIR)\s*N°.*?DU\s*(\d{2}/\d{2}/\d{2,4})')
LEGACY_DELIVERY_DATE_RE = re.compile(r'Livraison\s*:\s*(\d{2}/\d{2}/\d{2,4})')

# --- current layout (DYNAMIS SAS, English column labels) ---
NEW_INCL_RE = re.compile(r'Total Amount Incl\.' + HSPACE + r'*(' + MONEY + r')')
NEW_EXCL_RE = re.compile(r'Total Amount Excl\.' + HSPACE + r'*(' + MONEY + r')')
NEW_VAT_RE = re.compile(r'Total VAT Amount' + HSPACE + r'*(' + MONEY + r')')
NEW_DUE_RE = re.compile(r'Amount Due' + HSPACE + r'*(' + MONEY + r')')
# The payment slip at the foot repeats the reference, date and amount without the letter
# spacing the header applies, so it is the more reliable source for all three.
FOOT_REF_RE = re.compile(r'Facture\s*:\s*(\S+)')
FOOT_DATE_RE = re.compile(r'Date\s*:\s*(\d{2}/\d{2}/\d{4})')
FOOT_AMOUNT_RE = re.compile(r'Montant\s*:\s*(' + MONEY + r')')
# "INVOICE F C 1 0 0 0 0 0 7 6 dated 19/08/2026" — the header letter-spaces the reference
HEADER_REF_RE = re.compile(r'INVOICE\s+((?:[A-Z0-9]\s+)+)dated')
HEADER_DATE_RE = re.compile(r'dated\s*(\d{2}/\d{2}/\d{4})')


def _amount(raw):
    """
    Convert a Dynamis money string into a float.

    The space is always a thousands separator, but the decimal separator differs between
    the two layouts. Whichever of '.' or ',' appears last is the decimal point — the same
    rule documented for delivery_independent.py in docs/development/known-issues.md.
    """
    value = raw.strip().replace(' ', '').replace(' ', '')
    if ',' in value and '.' in value:
        if value.rfind('.') > value.rfind(','):
            value = value.replace(',', '')          # 1,908.62
        else:
            value = value.replace('.', '').replace(',', '.')   # 1.908,62
    elif ',' in value:
        value = value.replace(',', '.')             # 1844,32
    return float(value)


def _strip_accents(text):
    return ''.join(
        char for char in unicodedata.normalize('NFD', text)
        if not unicodedata.combining(char)
    )


def _is_tax_free(text):
    """
    Dynamis supply Ireland from France under the intra-community exemption, so every
    invoice is VAT exempt. The wording differs between layouts — "EXONERATION DE TVA,
    ARTICLE 262 TER I DU CGI." against "« Exonération TVA, art. 262 ter-I ... »" — so the
    check strips accents and does not assume the wording in between.
    """
    flat = _strip_accents(text).upper()
    return re.search(r'EXONERATION\s+(?:DE\s+)?TVA', flat) is not None


def _normalise_date(raw):
    """Dynamis print both 03/08/26 and 03/08/2026."""
    for fmt in ('%d/%m/%Y', '%d/%m/%y'):
        try:
            return datetime.strptime(raw, fmt).strftime('%d/%m/%Y')
        except ValueError:
            continue
    return raw


def _empty_buckets():
    return {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def _parse_legacy(text, warnings):
    """
    Legacy layout, in use to at least 2026-08-03.

    French throughout, decimals written with a period: "NET A PAYER EUR 1 908.62". On a
    multi-page invoice the totals labels repeat on every page with no figures beside them,
    and only the last page carries the amounts.
    """
    ref_match = LEGACY_REF_RE.search(text)
    invoice_number = ref_match.group(1) if ref_match else 'Not found'

    date_match = LEGACY_DATE_RE.search(text) or LEGACY_DELIVERY_DATE_RE.search(text)
    invoice_date = _normalise_date(date_match.group(1)) if date_match else 'Not found'

    total_match = LEGACY_TOTAL_RE.search(text)
    if total_match:
        total_amount = _amount(total_match.group(1))
    else:
        warnings.append('NET A PAYER not found on the invoice')
        total_amount = 0.0

    # NET A PAYER should be TOTAL HT plus TOTAL TVA
    ht_match = LEGACY_HT_RE.search(text)
    tva_match = LEGACY_TVA_RE.search(text)
    vat_amount = _amount(tva_match.group(1)) if tva_match else 0.0
    if ht_match and total_match:
        expected = round(_amount(ht_match.group(1)) + vat_amount, 2)
        if abs(expected - total_amount) > 0.01:
            warnings.append(
                f"TOTAL HT plus TOTAL TVA is €{expected:.2f} but NET A PAYER is "
                f"€{total_amount:.2f}"
            )

    return invoice_number, invoice_date, total_amount, vat_amount


def _parse_new(text, warnings):
    """
    Current layout, in use from 2026-08-19.

    English column labels, decimals written with a comma: "Total Amount Incl. 1 844,32 €".
    Shipping fees are already inside the total and need no separate handling.
    """
    ref_match = FOOT_REF_RE.search(text)
    if ref_match:
        invoice_number = ref_match.group(1)
    else:
        header_match = HEADER_REF_RE.search(text)
        invoice_number = header_match.group(1).replace(' ', '') if header_match else 'Not found'

    date_match = FOOT_DATE_RE.search(text) or HEADER_DATE_RE.search(text)
    invoice_date = _normalise_date(date_match.group(1)) if date_match else 'Not found'

    vat_match = NEW_VAT_RE.search(text)
    vat_amount = _amount(vat_match.group(1)) if vat_match else 0.0

    incl_match = NEW_INCL_RE.search(text)
    if incl_match:
        total_amount = _amount(incl_match.group(1))
    else:
        due_match = NEW_DUE_RE.search(text) or FOOT_AMOUNT_RE.search(text)
        if due_match:
            warnings.append('Total Amount Incl. not found; used the amount due instead')
            total_amount = _amount(due_match.group(1))
        else:
            warnings.append('Invoice total not found')
            total_amount = 0.0

    # Excl. + VAT must reach Incl., and the amount due and payment slip must agree with it
    excl_match = NEW_EXCL_RE.search(text)
    if excl_match and incl_match:
        expected = round(_amount(excl_match.group(1)) + vat_amount, 2)
        if abs(expected - total_amount) > 0.01:
            warnings.append(
                f"Total excluding VAT plus VAT is €{expected:.2f} but the invoice total is "
                f"€{total_amount:.2f}"
            )
    for label, match in (('amount due', NEW_DUE_RE.search(text)),
                         ('payment slip', FOOT_AMOUNT_RE.search(text))):
        if match and abs(_amount(match.group(1)) - total_amount) > 0.01:
            warnings.append(
                f"The {label} states €{_amount(match.group(1)):.2f} against a total of "
                f"€{total_amount:.2f}"
            )

    return invoice_number, invoice_date, total_amount, vat_amount


def parse_invoice(text, filename):
    """
    Parser for Dynamis (Groupe Dynamis SAS, Rungis) invoices.

    Two layouts are in circulation: the French one up to 2026-08-03 and an English-labelled
    one from 2026-08-19, which also flips the decimal separator from a period to a comma.
    Dynamis supply from France under the intra-community exemption, so every invoice to
    date carries no VAT and the whole amount belongs in the 0% bucket.
    """
    print(f"[DEBUG] Parsing Dynamis invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        is_legacy = LEGACY_MARKER in text.upper()
        print(f"[DEBUG] Layout: {'legacy' if is_legacy else 'current'}", file=sys.stderr)

        if is_legacy:
            invoice_number, invoice_date, total_amount, vat_amount = _parse_legacy(text, warnings)
        else:
            invoice_number, invoice_date, total_amount, vat_amount = _parse_new(text, warnings)

        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)
        print(f"[DEBUG] Total: {total_amount}  VAT: {vat_amount}", file=sys.stderr)

        tax_free = _is_tax_free(text)
        print(f"[DEBUG] Tax Free: {tax_free}", file=sys.stderr)

        # No Dynamis invoice has ever charged VAT. If one does, the layout gives no rate
        # breakdown to work from, so flag it rather than bank the lot as zero rated.
        if abs(vat_amount) > 0.005:
            warnings.append(
                f"Invoice states €{vat_amount:.2f} of VAT; Dynamis invoices are normally "
                f"exempt and the rate needs confirming by hand"
            )

        is_credit_note = total_amount < 0 or 'AVOIR' in text.upper()
        if is_credit_note and total_amount > 0:
            total_amount = -total_amount

        nets = _empty_buckets()
        nets['0'] = round(total_amount - vat_amount, 2)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Dynamis',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
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
