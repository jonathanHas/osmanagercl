import re
import sys
from datetime import datetime

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Three invoice: {filename}", file=sys.stderr)

    try:
        is_credit_note = False  # No credit notes expected from Three

        # === BEGIN INVOICE DATE MATCHING SECTION ===
        invoice_date = "Not found"

        lines = text.splitlines()
        for i, line in enumerate(lines):
            if 'bill date' in line.lower():
                print(f"[DEBUG] Bill Date Line Found: {line.strip()}", file=sys.stderr)
                # Find first non-empty line after 'Bill date'
                for j in range(i + 1, len(lines)):
                    next_line = lines[j].strip()
                    if next_line:  # Skip blank lines
                        print(f"[DEBUG] First Non-Empty Line After: {next_line}", file=sys.stderr)
                        date_matches = re.findall(r'\d{2}\s+\w+\s+\d{2,4}', next_line)
                        print(f"[DEBUG] Dates Found: {date_matches}", file=sys.stderr)
                        if date_matches:
                            raw_date = date_matches[-1]  # Take last date
                            print(f"[DEBUG] Raw Invoice Date Selected: {raw_date}", file=sys.stderr)
                            try:
                                dt = datetime.strptime(raw_date, "%d %b %y")
                                invoice_date = dt.strftime("%d/%m/%Y")
                            except ValueError:
                                try:
                                    dt = datetime.strptime(raw_date, "%d %b %Y")
                                    invoice_date = dt.strftime("%d/%m/%Y")
                                except ValueError:
                                    invoice_date = raw_date
                        break  # Stop scanning after processing the next meaningful line
                break  # Stop scanning lines after finding 'Bill date'
        else:
            print("[DEBUG] 'Bill date' line not found.", file=sys.stderr)
        # === END INVOICE DATE MATCHING SECTION ===



        # === VAT Detection ===
        vat_match = re.search(r'VAT at\s*([0-9]+)%\s*on\s*€?([0-9.,]+)', text)
        if vat_match:
            vat_rate = vat_match.group(1)
            net_vat_amount = vat_match.group(2).replace(',', '')
            print(f"[DEBUG] VAT Rate: {vat_rate}%, Net Amount: {net_vat_amount}", file=sys.stderr)
        else:
            vat_rate = None
            net_vat_amount = '0.00'
            print("[DEBUG] VAT info not found.", file=sys.stderr)

        # === VAT Columns (Net Amounts) ===
        vat_columns = {
            'VAT 0%': '0.00',
            'VAT 9%': '0.00',
            'VAT 13.5%': '0.00',
            'VAT 23%': '0.00'
        }

        if vat_rate == '0':
            vat_columns['VAT 0%'] = net_vat_amount
        elif vat_rate == '9':
            vat_columns['VAT 9%'] = net_vat_amount
        elif vat_rate == '13.5':
            vat_columns['VAT 13.5%'] = net_vat_amount
        elif vat_rate == '23':
            vat_columns['VAT 23%'] = net_vat_amount

        # === Final Parsed Data ===
        parsed_data = {
            'Filename': filename,
            'Supplier': 'Three',
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': is_credit_note,
            'VAT 0%': vat_columns['VAT 0%'],
            'VAT 9%': vat_columns['VAT 9%'],
            'VAT 13.5%': vat_columns['VAT 13.5%'],
            'VAT 23%': vat_columns['VAT 23%']
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
