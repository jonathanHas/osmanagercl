import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Dunany Flour invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = True  # No VAT breakdown shown, appears to be zero-rated
        vat_9 = vat_135 = vat_23 = "0.00"

        # === Invoice Number ===
        invoice_num_match = re.search(r'INVOICE NO:\s*(\d+)', text)
        invoice_number = invoice_num_match.group(1) if invoice_num_match else "Not found"
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        # === Total ===
        total_match = re.search(r'INVOICE TOTAL\s*€([0-9]+(?:[.,][0-9]{2})?)', text)
        total = total_match.group(1).replace(',', '') if total_match else "Not found"
        print(f"[DEBUG] Total: {total}", file=sys.stderr)

        # === Invoice Date ===
        # Look for DATE: dd/mm/yy format
        date_match = re.search(r'DATE:\s*(\d{1,2}/\d{1,2}/\d{2})', text)
        invoice_date = date_match.group(1) if date_match else "Not found"

        # Convert date to dd/mm/yyyy format
        if invoice_date != "Not found":
            day, month, year = invoice_date.split('/')
            # Pad single digits
            day = day.zfill(2)
            month = month.zfill(2)
            # Convert 2-digit year to 4-digit (assuming 20xx)
            if len(year) == 2:
                year = '20' + year
            invoice_date = f"{day}/{month}/{year}"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === Supplier ===
        supplier = "Dunany Flour"

        # For Dunany Flour, all amounts go to VAT 0% as they appear to be zero-rated
        vat_0 = total if total != "Not found" else "0.00"

        parsed_data = {
            'Filename': filename,
            'Supplier': supplier,
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': vat_0,
            'VAT 9%': vat_9,
            'VAT 13.5%': vat_135,
            'VAT 23%': vat_23
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise