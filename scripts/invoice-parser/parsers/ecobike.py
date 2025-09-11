import re
import sys

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing EcoBike invoice: {filename}", file=sys.stderr)

    try:
        # === Credit Note Detection ===
        is_credit_note = "CREDIT NOTE" in text.upper() or "CREDIT" in text.upper()
        print(f"[DEBUG] Credit Note Detected: {is_credit_note}", file=sys.stderr)

        # === Invoice Date ===
        # Look for date after "Date To Ship To" line: 08/09/2025 The Organic Store
        date_match = re.search(r'Date To Ship To\s*\n\s*(\d{2}/\d{2}/\d{4})', text)
        if not date_match:
            # Alternative pattern for date followed by customer info
            date_match = re.search(r'(\d{2}/\d{2}/\d{4})\s+The Organic Store', text)
        if not date_match:
            # More general pattern for date near "Date" field
            date_match = re.search(r'Date[:\s]*(\d{2}/\d{2}/\d{4})', text)
        
        invoice_date = date_match.group(1) if date_match else "Not found"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === Total Amount ===
        # Look for "Total Gross Amount €432.00"
        total_match = re.search(r'Total Gross Amount\s*€([\d.,]+)', text)
        if not total_match:
            # Alternative pattern for total
            total_match = re.search(r'Total.*?€([\d.,]+)', text)
        
        if total_match:
            total_amount = total_match.group(1).replace(',', '.')
            print(f"[DEBUG] Total Amount: €{total_amount}", file=sys.stderr)
        else:
            total_amount = '0.00'
            print(f"[DEBUG] Total amount not found", file=sys.stderr)

        # Handle credit note negative amount
        if is_credit_note and not total_amount.startswith('-'):
            total_amount = '-' + total_amount

        # === Tax Free Detection ===
        # EcoBike invoices show 0% VAT despite 23% rate (tax-free/exempt)
        vat_amount_match = re.search(r'Total Vat Amount\s*€([\d.,]+)', text)
        vat_amount = float(vat_amount_match.group(1).replace(',', '.')) if vat_amount_match else 0
        tax_free = vat_amount == 0
        print(f"[DEBUG] Tax Free: {tax_free}", file=sys.stderr)

        # === Invoice Number (for debugging) ===
        invoice_num_match = re.search(r'VAT INVOICE #\s*(\d+)', text)
        invoice_num = invoice_num_match.group(1) if invoice_num_match else "Unknown"
        print(f"[DEBUG] Invoice Number: {invoice_num}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'EcoBike',
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': total_amount if tax_free else '0.00',
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': total_amount if not tax_free else '0.00'
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise