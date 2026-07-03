import re
import sys
from datetime import datetime

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Slieve Bloom Organics invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = True  # Only 0% VAT shown

        # === Invoice Number ===
        # e.g. "INV-0457" (shown under the INVOICE heading and as "Reference: INV-0457")
        invoice_number = None
        num_match = re.search(r'\b(INV-\d+)', text)
        if num_match:
            invoice_number = num_match.group(1)
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        # === Invoice Date ===
        # Supported formats:
        #   Newest: "Date: 26 June 2026" (text month)
        #   Older:  "DATE 26-06-2026" or "Date 26/06/2026" (numeric)
        # Downstream expects dd/mm/yyyy (see format_invoice_date in invoice_parser_laravel.py).
        invoice_date = "Not found"
        text_date = re.search(r'\b[Dd]ate:?\s+(\d{1,2}\s+[A-Za-z]+\s+\d{4})', text)
        if text_date:
            for fmt in ("%d %B %Y", "%d %b %Y"):
                try:
                    invoice_date = datetime.strptime(text_date.group(1), fmt).strftime("%d/%m/%Y")
                    break
                except ValueError:
                    continue
        if invoice_date == "Not found":
            date_match = re.search(r'\b[Dd]ate:?\s+(\d{2}[-/]\d{2}[-/]\d{4})', text)
            if date_match:
                invoice_date = date_match.group(1).replace("-", "/")
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === VAT 0% (net amount) ===
        # New format: "Sub Total €80.80" or "Total Due €80.80"
        # Old format: "VAT @ 0% net_amount vat_amount" or "TOTAL amount"
        vat_0 = "0.00"

        # Amount labels may be followed by an optional colon and/or a euro sign,
        # e.g. "Sub Total €80.80", "Subtotal: €48.00", "TOTAL: €48.00".
        # Try Sub Total first (new format)
        sub_total_match = re.search(r'Sub\s*Total\s*:?\s*€?\s*([0-9]+\.[0-9]{2})', text, re.IGNORECASE)
        if sub_total_match:
            vat_0 = sub_total_match.group(1)
            print(f"[DEBUG] Found Sub Total: {vat_0}", file=sys.stderr)
        else:
            # Try Total Due (new format)
            total_due_match = re.search(r'Total\s*Due\s*:?\s*€?\s*([0-9]+\.[0-9]{2})', text, re.IGNORECASE)
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
                    # Fallback to grand TOTAL line ("TOTAL: €48.00" or old "TOTAL 48.00")
                    total_match = re.search(r'\bTOTAL\s*:?\s*€?\s*([0-9]+\.[0-9]{2})', text)
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

        if invoice_number:
            parsed_data['invoice_number'] = invoice_number

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
