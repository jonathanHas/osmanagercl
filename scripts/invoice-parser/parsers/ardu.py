import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Ardu invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = True  # No VAT shown
        vat_0 = vat_9 = vat_135 = vat_23 = "0.00"

        # === Total ===
        total_match = re.search(r'Total:\s*€?\s*([0-9]+(?:[.,][0-9]{2}))', text)
        total = total_match.group(1).replace(',', '') if total_match else "Not found"
        print(f"[DEBUG] Total: {total}", file=sys.stderr)

        # === Invoice Date ===
        date_match = re.search(r'Invoice Date\s+(\d{2}/\d{2}/\d{2})', text)
        invoice_date = date_match.group(1) if date_match else "Not found"

        # Convert date to dd/mm/yyyy
        if invoice_date != "Not found":
            day, month, year = invoice_date.split('/')
            if len(year) == 2:
                year = '20' + year
            invoice_date = f"{day}/{month}/{year}"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Ardu Bakery',
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': total if tax_free else "0.00",
            'VAT 9%': vat_9,
            'VAT 13.5%': vat_135,
            'VAT 23%': vat_23
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
