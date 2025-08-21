import re
import sys
from datetime import datetime

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing JetBrains invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False  # No credit notes expected from JetBrains

        # === Invoice Date Parsing ===
        date_match = re.search(r'Issue date:\s*(\d{2}\.\d{2}\.\d{4})', text)
        if date_match:
            raw_date = date_match.group(1)
            print(f"[DEBUG] Raw Invoice Date Found: {raw_date}", file=sys.stderr)
            try:
                dt = datetime.strptime(raw_date, "%d.%m.%Y")
                invoice_date = dt.strftime("%d/%m/%Y")
            except ValueError:
                invoice_date = raw_date
        else:
            invoice_date = "Not found"
            print("[DEBUG] Invoice date not found.", file=sys.stderr)

        # === Net VAT Amount Parsing ===
        net_match = re.search(r'Subtotal:\s*([0-9.,]+)\s*EUR', text)
        if net_match:
            net_amount = net_match.group(1).replace(',', '')
            print(f"[DEBUG] Net VAT Amount Found: {net_amount}", file=sys.stderr)
        else:
            net_amount = "0.00"
            print("[DEBUG] Net VAT amount not found.", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'JetBrains',
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': is_credit_note,
            'VAT 0%': '0.00',
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': net_amount  # Store net amount in 23% VAT column
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
