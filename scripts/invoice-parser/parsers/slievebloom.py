import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Slieve Bloom Organics invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = True  # Only 0% VAT shown

        # === Total Amount ===
        total_match = re.search(r'\bTOTAL\s+([0-9]+\.[0-9]{2})', text)
        total = total_match.group(1) if total_match else "Not found"
        print(f"[DEBUG] Total: {total}", file=sys.stderr)

        # === Invoice Date ===
        date_match = re.search(r'\bDATE\s+(\d{2}-\d{2}-\d{4})', text)
        invoice_date = date_match.group(1).replace("-", "/") if date_match else "Not found"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === VAT 0% from summary ===
        vat_0_match = re.search(r'VAT @ 0%\s+[0-9]+\.[0-9]{2}\s+([0-9]+\.[0-9]{2})', text)
        vat_0 = vat_0_match.group(1) if vat_0_match else "0.00"
        print(f"[DEBUG] VAT 0%: {vat_0}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Slieve Bloom Organics',
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': vat_0,
            'VAT 9%': "0.00",
            'VAT 13.5%': "0.00",
            'VAT 23%': "0.00"
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
