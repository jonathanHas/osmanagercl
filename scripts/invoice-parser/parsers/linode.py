import re
import sys
from datetime import datetime

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Linode invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False  # No credit notes expected from Linode

        # === Invoice Date Parsing ===
        date_match = re.search(r'Invoice Date:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})', text)
        if date_match:
            raw_date = date_match.group(1)
            print(f"[DEBUG] Raw Invoice Date Found: {raw_date}", file=sys.stderr)
            try:
                dt = datetime.strptime(raw_date, "%Y-%m-%d")
                invoice_date = dt.strftime("%d/%m/%Y")
            except ValueError:
                invoice_date = raw_date
        else:
            invoice_date = "Not found"
            print("[DEBUG] Invoice date not found.", file=sys.stderr)

        # === Total Amount Parsing ===
        total_match = re.search(r'Total\s*\(USD\)\s*\$([0-9.,]+)', text)
        if total_match:
            amount = total_match.group(1).replace(',', '')
            print(f"[DEBUG] Total Amount Found: {amount}", file=sys.stderr)
        else:
            amount = "0.00"
            print("[DEBUG] Total amount not found.", file=sys.stderr)

        # Zero VAT → VAT 0% column
        parsed_data = {
            'Filename': filename,
            'Supplier': 'Linode Akamai',
            'Invoice Date': invoice_date,
            'Tax Free': True,
            'Credit Note': is_credit_note,
            'VAT 0%': amount,
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': '0.00'
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
