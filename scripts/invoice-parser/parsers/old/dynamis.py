import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Dynamis invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = "AVOIR" in text.upper()
        print(f"[DEBUG] Credit Note Detected: {is_credit_note}", file=sys.stderr)

        # Total Parsing
        total_match = re.search(r'NET A PAYER\s*\|?EUR?\s*(-?[0-9\s]+[.,][0-9]+)', text)
        if total_match:
            raw_total = total_match.group(1)
            print(f"[DEBUG] Raw Total Match: {raw_total}", file=sys.stderr)
            total = raw_total.replace(' ', '')
        else:
            print(f"[DEBUG] Total match failed in file: {filename}", file=sys.stderr)
            total = None

        if total and is_credit_note and not total.startswith('-'):
            total = '-' + total

        # Invoice Date
        date_match = re.search(r'FACTURE.*?(\d{2}/\d{2}/\d{2,4})', text)
        if date_match:
            invoice_date = date_match.group(1)
            print(f"[DEBUG] Invoice Date Found: {invoice_date}", file=sys.stderr)
        else:
            print(f"[DEBUG] Invoice Date not found. Trying delivery date...", file=sys.stderr)
            delivery_match = re.search(r'Livraison\s*:\s*(\d{2}/\d{2}/\d{2,4})', text)
            invoice_date = delivery_match.group(1) if delivery_match else "Not found"
            print(f"[DEBUG] Delivery Date Used: {invoice_date}", file=sys.stderr)

        tax_free = "EXONERATION DE TVA" in text.upper()
        print(f"[DEBUG] Tax Free: {tax_free}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Dynamis',
            'Total': total,
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        # Optional: return dummy data or re-raise error
        raise
