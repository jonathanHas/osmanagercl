import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Klee Paper invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False  # Assuming credit notes are not handled yet
        tax_free = False

        # === Invoice Date ===
        date_match = re.search(r'Invoice Date\s+(\d{4}-\d{2}-\d{2})', text)
        invoice_date = "Not found"
        if date_match:
            parts = date_match.group(1).split("-")
            invoice_date = f"{parts[2]}/{parts[1]}/{parts[0]}"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === VAT Summary ===
        vat_0 = vat_9 = vat_135 = vat_23 = "0.00"

        vat_summary_match = re.search(r'VAT Summary.*?(\d+\.\d{2})\s+23\.00\s+(\d+\.\d{2})', text, re.DOTALL)
        if vat_summary_match:
            vat_23 = vat_summary_match.group(1)
            print(f"[DEBUG] VAT 23% Detected: {vat_23}", file=sys.stderr)
        else:
            print("[DEBUG] No VAT 23% found.", file=sys.stderr)

        # === Total Net Detection ===
        net_total_match = re.search(r'Nett\s+(\d+\.\d{2})', text)
        if net_total_match:
            vat_23 = net_total_match.group(1)
            print(f"[DEBUG] Updated VAT 23% from net: {vat_23}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Klee Paper',
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': vat_0,
            'VAT 9%': vat_9,
            'VAT 13.5%': vat_135,
            'VAT 23%': vat_23,
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
