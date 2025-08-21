import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Udea invoice: {filename}", file=sys.stderr)

    try:
        # === Total ===
        total_match = re.search(r'Total including vat EUR\s+([\d.,]+)', text, re.IGNORECASE)
        total = total_match.group(1).replace(',', '.').strip() if total_match else None
        print(f"[DEBUG] Total: {total if total else 'Not found'}", file=sys.stderr)

        # === Invoice Date ===
        date_match = re.search(r'Invoice date\s*:\s*(\d{2}\.\d{2}\.\d{4})', text)
        invoice_date = (
            date_match.group(1).replace('.', '/') if date_match else "Not found"
        )
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === VAT Info (all values are within 0% range)
        vat_0 = total if total else "0.00"

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Udea',
            'Invoice Date': invoice_date,
            'Tax Free': True,
            'Credit Note': False,
            'VAT 0%': vat_0,
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': '0.00'
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
