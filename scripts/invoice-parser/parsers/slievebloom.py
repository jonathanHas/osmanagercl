import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Slieve Bloom Organics invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = True  # Only 0% VAT shown

        # === Invoice Date ===
        # Support both old (DATE dd-mm-yyyy) and new (Date dd/mm/yyyy) formats
        date_match = re.search(r'\b[Dd]ate\s+(\d{2}[-/]\d{2}[-/]\d{4})', text)
        invoice_date = date_match.group(1).replace("-", "/") if date_match else "Not found"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === VAT 0% (net amount) ===
        # New format: "Sub Total €80.80" or "Total Due €80.80"
        # Old format: "VAT @ 0% net_amount vat_amount" or "TOTAL amount"
        vat_0 = "0.00"

        # Try Sub Total first (new format)
        sub_total_match = re.search(r'Sub\s*Total\s*€?([0-9]+\.[0-9]{2})', text, re.IGNORECASE)
        if sub_total_match:
            vat_0 = sub_total_match.group(1)
            print(f"[DEBUG] Found Sub Total: {vat_0}", file=sys.stderr)
        else:
            # Try Total Due (new format)
            total_due_match = re.search(r'Total\s*Due\s*€?([0-9]+\.[0-9]{2})', text, re.IGNORECASE)
            if total_due_match:
                vat_0 = total_due_match.group(1)
                print(f"[DEBUG] Found Total Due: {vat_0}", file=sys.stderr)
            else:
                # Try old VAT @ 0% format
                vat_0_match = re.search(r'VAT @ 0%\s+[0-9]+\.[0-9]{2}\s+([0-9]+\.[0-9]{2})', text)
                if vat_0_match:
                    vat_0 = vat_0_match.group(1)
                    print(f"[DEBUG] Found VAT @ 0%: {vat_0}", file=sys.stderr)
                else:
                    # Fallback to old TOTAL format
                    total_match = re.search(r'\bTOTAL\s+([0-9]+\.[0-9]{2})', text)
                    if total_match:
                        vat_0 = total_match.group(1)
                        print(f"[DEBUG] Found TOTAL: {vat_0}", file=sys.stderr)

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
