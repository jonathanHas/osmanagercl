import re
import sys
from datetime import datetime

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Hetzner invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False  # No credit notes expected from Hetzner

        # === Invoice Number ===
        invoice_number = None
        num_match = re.search(r'Invoice no\.?:\s*(\d+)', text)
        if num_match:
            invoice_number = num_match.group(1)
            print(f"[DEBUG] Invoice Number Found: {invoice_number}", file=sys.stderr)
        else:
            print("[DEBUG] Invoice number not found.", file=sys.stderr)

        # === Invoice Date Parsing (DD/MM/YYYY format on Hetzner invoices) ===
        date_match = re.search(r'Invoice date:\s*(\d{2}/\d{2}/\d{4})', text)
        if date_match:
            invoice_date = date_match.group(1)
            print(f"[DEBUG] Raw Invoice Date Found: {invoice_date}", file=sys.stderr)
        else:
            invoice_date = "Not found"
            print("[DEBUG] Invoice date not found.", file=sys.stderr)

        # === Total Amount Parsing ===
        # Hetzner invoices can span multiple projects/billing periods, each with its own
        # "Subtotal (excl. VAT) € X" line. Sum them all so multi-period invoices are not
        # under-reported (re.search would only grab the first subtotal).
        amount = None
        subtotals = re.findall(r'Subtotal\s*\(excl\.\s*VAT\)\s*€\s*([0-9.,]+)', text)
        if subtotals:
            amount = f"{sum(float(s.replace(',', '')) for s in subtotals):.2f}"
            print(f"[DEBUG] Total Amount Found (sum of {len(subtotals)} subtotal(s)): {amount}", file=sys.stderr)
        else:
            # Fallback: grand-total summary row "Total € 10.58 € 0.00 € 10.58"
            fallback = re.search(r'Total\s+€\s*([0-9.,]+)\s+€\s*[0-9.,]+\s+€\s*[0-9.,]+', text)
            if fallback:
                amount = fallback.group(1).replace(',', '')
                print(f"[DEBUG] Total Amount Found (fallback): {amount}", file=sys.stderr)

        if amount is None:
            amount = "0.00"
            print("[DEBUG] Total amount not found.", file=sys.stderr)

        # Hetzner is a German supplier billing IE with valid VAT no. → reverse charge.
        # Treat as tax free; full amount → VAT 0% (matches DigitalOcean/Linode convention).
        parsed_data = {
            'Filename': filename,
            'Supplier': 'Hetzner',
            'Invoice Date': invoice_date,
            'Tax Free': True,
            'Credit Note': is_credit_note,
            'VAT 0%': amount,
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': '0.00'
        }

        if invoice_number:
            parsed_data['invoice_number'] = invoice_number

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
