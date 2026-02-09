import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Independent Irish Health Foods invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = False  # We'll set this false since VAT is present

        # === VAT Lines ===
        vat_0 = vat_9 = vat_135 = vat_23 = 0.0

        # Try OCR-style line match first
        matches = re.findall(r'(?m)^(0\.00|9\.00|13\.50|23\.00)\s+([0-9.,]+)', text)
        print(f"[DEBUG] OCR-style VAT Rate Matches: {matches}", file=sys.stderr)

        if not matches:
            # Try pdfplumber-style lines as fallback
            matches = re.findall(r'(?m)^\s*\d+\s+(0\.00|9\.00|13\.50|23\.00)\s+([0-9.,]+)\s+[0-9.,]+', text)
            print(f"[DEBUG] Plumber-style VAT Rate Matches: {matches}", file=sys.stderr)

        for rate, amount in matches:
            amount_val = float(amount.replace(',', ''))
            if rate == '0.00':
                vat_0 += amount_val
            elif rate == '9.00':
                vat_9 += amount_val
            elif rate == '13.50':
                vat_135 += amount_val
            elif rate == '23.00':
                vat_23 += amount_val

        # === Invoice Date Parsing
        date_match = re.search(r'Invoice Date[:\s]*([0-9]{2}/[0-9]{2}/[0-9]{4})', text)
        invoice_date = date_match.group(1) if date_match else "Not found"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Independent',
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': is_credit_note,
            'VAT 0%': f"{vat_0:.2f}",
            'VAT 9%': f"{vat_9:.2f}",
            'VAT 13.5%': f"{vat_135:.2f}",
            'VAT 23%': f"{vat_23:.2f}"
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
