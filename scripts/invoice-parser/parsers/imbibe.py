import re
import sys
from datetime import datetime

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Imbibe invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False  # Assume no credit notes

        # === Invoice Date Parsing ===
        date_match = re.search(r'Date\s*(\d{2}/\d{2}/\d{4})', text)
        if date_match:
            invoice_date = date_match.group(1)
            print(f"[DEBUG] Invoice Date Found: {invoice_date}", file=sys.stderr)
        else:
            invoice_date = "Not found"
            print("[DEBUG] Invoice date not found.", file=sys.stderr)

        # === Total Amount Parsing ===
        total_match = re.search(r'^TOTAL\s*€?([0-9.,]+)', text, re.MULTILINE | re.IGNORECASE)
        if not total_match:
            total_match = re.search(r'AMOUNT:\s*€?([0-9.,]+)', text, re.IGNORECASE)
        if not total_match:
            total_match = re.search(r'^\s*€([0-9.,]+)\s*$', text, re.MULTILINE)

        if total_match:
            amount = total_match.group(1).replace(',', '')
            print(f"[DEBUG] Total Amount Found: {amount}", file=sys.stderr)
        else:
            amount = "0.00"
            print("[DEBUG] Total amount not found.", file=sys.stderr)

        # Treat as zero VAT supplier
        parsed_data = {
            'Filename': filename,
            'Supplier': 'Imbibe',
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
