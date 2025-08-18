# parsers/merrymill.py

import re

def parse_invoice(text, filename):
    lines = text.splitlines()
    data = {
        'Filename': filename,
        'Supplier': 'Merry Mill',
        'Invoice Date': '',
        'Tax Free': '',
        'Credit Note': '',
        'VAT 0%': '',
        'VAT 9%': '',
        'VAT 13.5%': '',
        'VAT 23%': '',
    }

    for line in lines:
        if re.search(r'\d{2}/\d{2}/\d{4}', line):  # e.g. 10/04/2025
            parts = line.strip().split()
            if len(parts) >= 1 and re.match(r'\d{2}/\d{2}/\d{4}', parts[0]):
                data['Invoice Date'] = parts[0]

        if 'No VAT' in line or 'VAT Rate' in line:
            vat_0_match = re.search(r'Net\s+(\d+\.\d+)', line)
            if vat_0_match:
                data['VAT 0%'] = vat_0_match.group(1)

        if 'TOTAL €' in line:
            total_match = re.search(r'TOTAL €\s*(\d+\.\d+)', line)
            if total_match:
                data['Tax Free'] = total_match.group(1)

    return data
