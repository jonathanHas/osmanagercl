import re
import sys

def parse_invoice(text, filename):
    """
    Parser for Menton's Organic Farm invoices
    Handles handwritten/informal invoice format
    """
    print(f"[DEBUG] Parsing Menton's Organic Farm invoice: {filename}", file=sys.stderr)

    try:
        # === Date ===
        # Look for dates in format DD/M/YY or DD/MM/YYYY
        date_match = re.search(r'(\d{1,2})/(\d{1,2})/(\d{2,4})', text)
        invoice_date = "Not found"
        if date_match:
            day, month, year = date_match.groups()
            # Convert 2-digit year to 4-digit
            if len(year) == 2:
                year = f"20{year}"
            invoice_date = f"{day.zfill(2)}/{month.zfill(2)}/{year}"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === Total Amount ===
        # Look for calculations like "34.5 ells @ €5.80 = €199" or just amounts like "€199"
        total_amount = 0.0

        # Try to find calculation pattern first (quantity @ price = total)
        calc_match = re.search(r'[\d.]+\s*(?:ells?|units?|kg|each)?\s*[@at]\s*€?\s*([\d.]+)\s*=\s*€?\s*([\d.]+)', text, re.IGNORECASE)
        if calc_match:
            total_amount = float(calc_match.group(2))
            print(f"[DEBUG] Found calculation total: €{total_amount}", file=sys.stderr)
        else:
            # Try to find standalone amounts like "€199" or "199.00"
            amount_match = re.search(r'€?\s*([\d,]+\.?\d{0,2})', text)
            if amount_match:
                amount_str = amount_match.group(1).replace(',', '')
                try:
                    total_amount = float(amount_str)
                    print(f"[DEBUG] Found standalone amount: €{total_amount}", file=sys.stderr)
                except ValueError:
                    print(f"[DEBUG] Could not parse amount: {amount_str}", file=sys.stderr)

        # Menton's invoices typically don't show VAT breakdown
        # Assume standard 23% VAT rate unless otherwise specified
        is_tax_free = False
        vat_totals = {
            "0": 0.0,
            "9": 0.0,
            "13.5": 0.0,
            "23": 0.0
        }

        # If we have a total, calculate backwards assuming 23% VAT
        if total_amount > 0:
            # Check if text mentions "VAT", "tax free", "zero rated", etc.
            if re.search(r'(tax\s*free|zero\s*rated|vat\s*exempt)', text, re.IGNORECASE):
                vat_totals["0"] = total_amount
                is_tax_free = True
            else:
                # Assume 23% VAT included in total
                # Net = Total / 1.23
                net_amount = total_amount / 1.23
                vat_totals["23"] = net_amount

        parsed_data = {
            'Filename': filename,
            'Supplier': "Menton's Organic Farm",
            'Invoice Date': invoice_date,
            'Tax Free': is_tax_free,
            'Credit Note': False,
            'VAT 0%': f"{vat_totals['0']:.2f}",
            'VAT 9%': f"{vat_totals['9']:.2f}",
            'VAT 13.5%': f"{vat_totals['13.5']:.2f}",
            'VAT 23%': f"{vat_totals['23']:.2f}"
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
