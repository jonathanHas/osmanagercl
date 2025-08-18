import re
import sys
from utils import extract_text
from pdf2image import convert_from_path
import pytesseract

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Coolnagrower invoice: {filename}", file=sys.stderr)

    # Force OCR again and get per-page text
    pages = convert_from_path(filename, dpi=400)
    results = []

    for i, page in enumerate(pages):
        ocr_text = pytesseract.image_to_string(page, config='--psm 6')

        # Skip the statement page (usually the first one)
        if i == 0 and "Statement For:" in ocr_text:
            print(f"[DEBUG] Skipping statement page: {i + 1}", file=sys.stderr)
            continue

        # === Order Date ===
        date_match = re.search(r'Order Date:\s*(\d{1,2}/\d{1,2}/\d{4})', ocr_text)
        invoice_date = date_match.group(1) if date_match else f"Page {i + 1}"

        # === Total ===
        total_match = re.search(r'TOTAL:\s*([0-9]+(?:[.,][0-9]{2}))', ocr_text)
        total = total_match.group(1).replace(',', '.') if total_match else "0.00"

        parsed_data = {
            'Filename': f"{filename} - Page {i + 1}",
            'Supplier': 'Coolnagrower',
            'Invoice Date': invoice_date,
            'Tax Free': True,
            'Credit Note': False,
            'VAT 0%': total,
            'VAT 9%': "0.00",
            'VAT 13.5%': "0.00",
            'VAT 23%': "0.00",
        }

        print(f"[DEBUG] Invoice found on page {i + 1}: {parsed_data}", file=sys.stderr)
        results.append(parsed_data)

    # If no invoices found, return a dummy entry
    if not results:
        return [{
            'Filename': filename,
            'Supplier': 'Coolnagrower',
            'Invoice Date': 'Not found',
            'Tax Free': False,
            'Credit Note': False,
            'VAT 0%': '0.00',
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': '0.00',
        }]

    return results
