import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Kellys (Ce&Os) invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = False  # VAT is present

        # === Invoice Number ===
        invoice_number_match = re.search(r'Invoice No\.[\s]*\n?[\s]*(IN\d+)', text)
        invoice_number = invoice_number_match.group(1) if invoice_number_match else "Not found"
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        # === Invoice Date ===
        date_match = re.search(r'Invoice Date[:\s]+(\d{2}/\d{2}/\d{4})', text)
        invoice_date = date_match.group(1) if date_match else "Not found"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === VAT 23% Parsing
        vat_match = re.search(r'\b23\.00\s+([0-9]+\.[0-9]{2})', text)
        vat_23 = vat_match.group(1) if vat_match else "0.00"
        print(f"[DEBUG] VAT 23%: {vat_23}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Ce&Os Kellys',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': "0.00",
            'VAT 9%': "0.00",
            'VAT 13.5%': "0.00",
            'VAT 23%': vat_23
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
