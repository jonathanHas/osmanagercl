import re
import sys
from datetime import datetime

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Dynamis invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = "AVOIR" in text.upper()
        print(f"[DEBUG] Credit Note Detected: {is_credit_note}", file=sys.stderr)

        # === Extract Net Amount (Full Invoice Amount) ===
        total_match = re.search(r'NET A PAYER\s*\|?EUR?\s*(-?[0-9\s]+[.,][0-9]+)', text)
        if total_match:
            raw_total = total_match.group(1)
            print(f"[DEBUG] Raw Total Match: {raw_total}", file=sys.stderr)
            net_amount = raw_total.replace(' ', '')
        else:
            print(f"[DEBUG] Total match failed in file: {filename}", file=sys.stderr)
            net_amount = '0.00'  # Default fallback

        if is_credit_note and not net_amount.startswith('-'):
            net_amount = '-' + net_amount

        # === Invoice Date Parsing ===
        date_match = re.search(r'FACTURE.*?(\d{2}/\d{2}/\d{2,4})', text)
        raw_date = date_match.group(1) if date_match else None
        if raw_date:
            try:
                dt = datetime.strptime(raw_date, "%d/%m/%y")
                invoice_date = dt.strftime("%d/%m/%Y")
            except ValueError:
                try:
                    dt = datetime.strptime(raw_date, "%d/%m/%Y")
                    invoice_date = dt.strftime("%d/%m/%Y")
                except ValueError:
                    invoice_date = raw_date
            print(f"[DEBUG] Invoice Date Found: {invoice_date}", file=sys.stderr)
        else:
            print(f"[DEBUG] Invoice Date not found. Trying delivery date...", file=sys.stderr)
            delivery_match = re.search(r'Livraison\s*:\s*(\d{2}/\d{2}/\d{2,4})', text)
            raw_date = delivery_match.group(1) if delivery_match else None
            if raw_date:
                try:
                    dt = datetime.strptime(raw_date, "%d/%m/%y")
                    invoice_date = dt.strftime("%d/%m/%Y")
                except ValueError:
                    try:
                        dt = datetime.strptime(raw_date, "%d/%m/%Y")
                        invoice_date = dt.strftime("%d/%m/%Y")
                    except ValueError:
                        invoice_date = raw_date
                print(f"[DEBUG] Delivery Date Used: {invoice_date}", file=sys.stderr)
            else:
                invoice_date = "Not found"

        tax_free = "EXONERATION DE TVA" in text.upper()
        print(f"[DEBUG] Tax Free: {tax_free}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Dynamis',
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': net_amount,
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': '0.00'
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
