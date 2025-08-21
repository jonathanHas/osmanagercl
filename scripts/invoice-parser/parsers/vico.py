import re
import sys
from datetime import datetime

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Vico invoice: {filename}", file=sys.stderr)
    print("[DEBUG] Full Invoice Text:", file=sys.stderr)
    print(text)

    try:
        is_credit_note = False
        tax_free = False  # VAT is present
        vat_0 = vat_9 = vat_135 = "0.00"
        vat_23 = "0.00"

        # === VAT Base Amount Parsing (Ex-VAT amounts, not tax amounts) ===
        vat_tax_amount = 0.0
        net_23_amount = 0.0
        extraction_method = "None"
        
        # Strategy 1: Look for "TOTAL STANDARD23%" (current format) - this is the tax amount
        vat_match = re.search(r'TOTAL STANDARD23%\s+([0-9.]+)', text)
        if vat_match:
            vat_tax_amount = float(vat_match.group(1))
            extraction_method = "TOTAL STANDARD23%"
            print(f"[DEBUG] VAT Tax Amount Found via {extraction_method}: {vat_tax_amount}", file=sys.stderr)
        
        # Strategy 2: Look for "INCLUDES STANDARD23%" (legacy format) - this would be tax amount
        if vat_tax_amount == 0.0:
            vat_match = re.search(r'INCLUDES STANDARD23%\s+([0-9.]+)', text)
            if vat_match:
                vat_tax_amount = float(vat_match.group(1))
                extraction_method = "INCLUDES STANDARD23%"
                print(f"[DEBUG] VAT Tax Amount Found via {extraction_method}: {vat_tax_amount}", file=sys.stderr)
        
        # Strategy 3: Find last occurrence of STANDARD23% or VAT.*23% pattern
        if vat_tax_amount == 0.0:
            # Look for lines that contain VAT info, not product lines
            vat_patterns = [
                r'(?:VAT|STANDARD)\s*23%\s+([0-9.]+)',
                r'([0-9.]+)\s+(?:VAT|STANDARD)\s*23%'
            ]
            for pattern in vat_patterns:
                matches = list(re.finditer(pattern, text))
                if matches:
                    # Take the last match (should be the VAT total, not a product line)
                    last_match = matches[-1]
                    vat_tax_amount = float(last_match.group(1))
                    extraction_method = f"Last VAT 23% match: {pattern}"
                    print(f"[DEBUG] VAT Tax Amount Found via {extraction_method}: {vat_tax_amount}", file=sys.stderr)
                    break
        
        # Strategy 4: Mathematical validation using Subtotal + VAT Tax = Total EUR
        subtotal_match = re.search(r'Subtotal\s+([0-9.]+)', text)
        total_match = re.search(r'TOTAL(?:EUR)?\s+([0-9.]+)', text)
        
        if subtotal_match and total_match:
            subtotal = float(subtotal_match.group(1))
            total_eur = float(total_match.group(1))
            calculated_vat_tax = total_eur - subtotal
            
            print(f"[DEBUG] Subtotal: {subtotal}, Total EUR: {total_eur}, Calculated VAT Tax: {calculated_vat_tax}", file=sys.stderr)
            
            # The subtotal IS the net amount subject to 23% VAT
            net_23_amount = subtotal
            print(f"[DEBUG] Net amount subject to 23% VAT: {net_23_amount}", file=sys.stderr)
            
            # Validate extracted tax amount against calculation
            if vat_tax_amount > 0.0:
                if abs(vat_tax_amount - calculated_vat_tax) <= 0.01:  # Allow small rounding differences
                    print(f"[DEBUG] VAT tax amount validated: {vat_tax_amount} ≈ {calculated_vat_tax}", file=sys.stderr)
                else:
                    print(f"[DEBUG] WARNING: VAT tax mismatch - extracted: {vat_tax_amount}, calculated: {calculated_vat_tax}", file=sys.stderr)
                    # Use calculated value if extraction seems wrong
                    if abs(calculated_vat_tax) > abs(vat_tax_amount):
                        vat_tax_amount = calculated_vat_tax
                        extraction_method = "Mathematical calculation (Total - Subtotal)"
                        print(f"[DEBUG] Using calculated VAT tax: {vat_tax_amount}", file=sys.stderr)
            else:
                # No VAT tax found via patterns, use calculation
                vat_tax_amount = calculated_vat_tax
                extraction_method = "Mathematical calculation (fallback)"
                print(f"[DEBUG] VAT tax calculated as fallback: {vat_tax_amount}", file=sys.stderr)
        
        # The VAT 23% column should contain the NET amount (ex-VAT), not the tax amount
        vat_23 = "{:.2f}".format(abs(net_23_amount))  # This is the subtotal (net amount)
        
        if net_23_amount == 0.0:
            print("[DEBUG] VAT 23% net amount extraction failed - no subtotal found", file=sys.stderr)
        else:
            print(f"[DEBUG] Final VAT 23% (net amount, file=sys.stderr): {vat_23}")
            print(f"[DEBUG] VAT tax amount: {vat_tax_amount:.2f} (for reference, file=sys.stderr)")
            print(f"[DEBUG] Extraction method: {extraction_method}", file=sys.stderr)

        # === Date Parsing (spanning lines, e.g. "InvoiceDate\n7Apr2025")
        invoice_date = "Not found"
        lines = text.splitlines()
        for i, line in enumerate(lines):
            if "InvoiceDate" in line and i + 1 < len(lines):
                possible_date = lines[i + 1].strip()
                try:
                    parsed_date = datetime.strptime(possible_date, "%d%b%Y")
                    invoice_date = parsed_date.strftime("%d/%m/%Y")
                    print(f"[DEBUG] Parsed Invoice Date: {invoice_date}", file=sys.stderr)
                except ValueError as e:
                    print(f"[DEBUG] Failed to parse invoice date '{possible_date}': {e}", file=sys.stderr)
                break  # Only check the first matching instance

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Vico',
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
