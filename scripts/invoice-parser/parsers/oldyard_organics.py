import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Oldyard Organics invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False
        tax_free = True  # Oldyard Organics invoices are zero VAT
        
        # === Invoice Number ===
        invoice_num_match = re.search(r'INVOICE\s+NO\.\s*(\d+)', text)
        invoice_num = invoice_num_match.group(1) if invoice_num_match else "Not found"
        print(f"[DEBUG] Invoice Number: {invoice_num}", file=sys.stderr)
        
        # === Invoice Date ===
        date_match = re.search(r'(\d{2}-\d{2}-\d{4})', text)
        invoice_date = date_match.group(1) if date_match else "Not found"
        
        # Convert date from DD-MM-YYYY to DD/MM/YYYY
        if invoice_date != "Not found":
            parts = invoice_date.split('-')
            invoice_date = f"{parts[0]}/{parts[1]}/{parts[2]}"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)
        
        # === Total Amount ===
        # Look for the total amount - it appears after "Total" and before VAT text
        total_match = re.search(r'Total\s+€\s*([0-9]+(?:[.,][0-9]{2})?)', text)
        if total_match:
            amount = total_match.group(1).replace(',', '')
        else:
            # Alternative: sum up individual line items
            line_items = re.findall(r'Wk of\s+\d{2}/\d{2}\s*-\s*\d+\s*trays?\s*€\s*([0-9]+(?:[.,][0-9]{2})?)', text)
            if line_items:
                total = sum(float(item.replace(',', '')) for item in line_items)
                amount = f"{total:.2f}"
            else:
                amount = "0.00"
        
        print(f"[DEBUG] Total Amount: {amount}", file=sys.stderr)
        
        parsed_data = {
            'Filename': filename,
            'Supplier': 'Oldyard Organics',
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': amount,
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': '0.00'
        }
        
        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data
        
    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise