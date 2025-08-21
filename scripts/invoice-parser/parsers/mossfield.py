import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Mossfield invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = True  # All Mossfield invoices are zero VAT

        # === Invoice Date ===
        date_match = re.search(r'INVOICE DATE[:\s]*([0-9]{2}/[0-9]{2}/[0-9]{4})', text)
        invoice_date = date_match.group(1) if date_match else "Not found"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === Total Amount ===
        # Match numbers like 1,270.78 or 270.78
        total_matches = re.findall(r'\b(?:[0-9]{1,3}(?:,[0-9]{3})*|[0-9]+)\.[0-9]{2}\b', text)
        if total_matches:
            raw_amount = total_matches[-1]  # Last one is the total
            amount = raw_amount.replace(',', '')  # Strip commas
            print(f"[DEBUG] Total Amount Found: {amount}", file=sys.stderr)
        else:
            amount = "0.00"
            print("[DEBUG] Total amount not found.", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Mossfield',
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
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
