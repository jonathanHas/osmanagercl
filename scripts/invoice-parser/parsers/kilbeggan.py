import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Kilbeggan Organic Foods invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = True  # All items 0% VAT

        # === Invoice Date ===
        # Format: "Invoice/Tas Date 15/01/2026" (note typo in "Tas")
        date_match = re.search(r'Invoice/Ta[sx]\s*Date\s+(\d{2}/\d{2}/\d{4})', text, re.IGNORECASE)
        invoice_date = date_match.group(1) if date_match else "Not found"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === Total Amount (VAT 0%) ===
        # Try "Total Net Amount €" first, then "Invoice Total €"
        vat_0 = "0.00"

        total_net_match = re.search(r'Total\s*Net\s*Amount\s*€?\s*([0-9,]+\.[0-9]{2})', text, re.IGNORECASE)
        if total_net_match:
            vat_0 = total_net_match.group(1).replace(',', '')
            print(f"[DEBUG] Found Total Net Amount: {vat_0}", file=sys.stderr)
        else:
            invoice_total_match = re.search(r'Invoice\s*Total\s*€?\s*([0-9,]+\.[0-9]{2})', text, re.IGNORECASE)
            if invoice_total_match:
                vat_0 = invoice_total_match.group(1).replace(',', '')
                print(f"[DEBUG] Found Invoice Total: {vat_0}", file=sys.stderr)

        print(f"[DEBUG] VAT 0%: {vat_0}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Kilbeggan Organic Foods',
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
