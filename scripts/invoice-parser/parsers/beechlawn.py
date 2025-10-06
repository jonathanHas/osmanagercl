import re
import sys

def parse_invoice(text, filename):
    """
    Parser for Beechlawn Organic Farm invoices
    Format: Simple table with zero-rated items (0% VAT)
    """
    # Debug output to stderr only
    print(f"[DEBUG] Parsing Beechlawn invoice: {filename}", file=sys.stderr)

    try:
        # === Invoice Number ===
        invoice_number = "Not found"
        inv_match = re.search(r"INV-(\d+)", text)
        if inv_match:
            invoice_number = f"INV-{inv_match.group(1)}"
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        # === Invoice Date ===
        # Looking for patterns like "30Sep2025" after "InvoiceDate"
        # The date appears on a new line: "InvoiceDate BeechlawnOrganicFarm\n30Sep2025"
        invoice_date = "Not found"

        # Try pattern with newline
        date_match = re.search(r"InvoiceDate.*?\n(\d{1,2}[A-Za-z]{3}\d{4})", text, re.DOTALL)
        if not date_match:
            # Try pattern on same line
            date_match = re.search(r"InvoiceDate\s*(\d{1,2}[A-Za-z]{3}\d{4})", text)

        if date_match:
            date_str = date_match.group(1)
            # Parse dates like "30Sep2025"
            import datetime
            try:
                dt = datetime.datetime.strptime(date_str, "%d%b%Y")
                invoice_date = dt.strftime("%d/%m/%Y")
            except:
                invoice_date = date_str

        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === VAT Totals ===
        # All Beechlawn items appear to be ZeroRated (0% VAT)
        vat_totals = {
            "0": 0.0,
            "9": 0.0,
            "13.5": 0.0,
            "23": 0.0
        }

        is_credit_note = False
        tax_free = False

        # === Total Amount ===
        # Look for "TOTALEUR" followed by amount
        total_match = re.search(r"TOTALEUR\s+(\d+(?:\.\d{2})?)", text)
        total_amount = 0.0
        if total_match:
            total_amount = float(total_match.group(1))
            # All Beechlawn items are zero-rated, so total goes to 0% VAT
            vat_totals["0"] = total_amount

        print(f"[DEBUG] Total Amount: {total_amount}", file=sys.stderr)
        print(f"[DEBUG] VAT 0%: {vat_totals['0']}", file=sys.stderr)

        # === Alternative: Parse line items and sum ===
        # If total not found, try to sum from line items
        if total_amount == 0.0:
            # Look for lines with "ZeroRated" and amounts
            line_pattern = r"(\d+(?:\.\d{2}))\s+(\d+(?:\.\d{2}))\s+ZeroRated\s+(\d+(?:\.\d{2}))"
            line_matches = re.findall(line_pattern, text)

            line_total = 0.0
            for quantity, unit_price, amount in line_matches:
                line_total += float(amount)

            if line_total > 0:
                vat_totals["0"] = line_total
                total_amount = line_total
                print(f"[DEBUG] Calculated total from line items: {line_total}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Beechlawn',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': f"{vat_totals['0']:.2f}",
            'VAT 9%': f"{vat_totals['9']:.2f}",
            'VAT 13.5%': f"{vat_totals['13.5']:.2f}",
            'VAT 23%': f"{vat_totals['23']:.2f}",
            'Total': f"{total_amount:.2f}"
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        raise
