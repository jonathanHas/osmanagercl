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
        # Format: "20 Feb 2026" or "20/02/26"
        month_names = {
            'jan': '01', 'feb': '02', 'mar': '03', 'apr': '04',
            'may': '05', 'jun': '06', 'jul': '07', 'aug': '08',
            'sep': '09', 'oct': '10', 'nov': '11', 'dec': '12'
        }
        date_match = re.search(r'Invoice Date\s+(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{4})', text, re.IGNORECASE)
        if date_match:
            day = date_match.group(1).zfill(2)
            month = month_names[date_match.group(2).lower()[:3]]
            year = date_match.group(3)
            invoice_date = f"{day}/{month}/{year}"
        else:
            # Fallback: dd/mm/yy format
            date_match2 = re.search(r'Invoice Date\s+(\d{2}/\d{2}/\d{2})', text)
            if date_match2:
                invoice_date = date_match2.group(1)
                day, month, year = invoice_date.split('/')
                if len(year) == 2:
                    year = '20' + year
                invoice_date = f"{day}/{month}/{year}"
            else:
                invoice_date = "Not found"
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
