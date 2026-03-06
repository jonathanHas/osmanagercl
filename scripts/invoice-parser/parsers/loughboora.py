import xlrd
import sys
from datetime import datetime, timedelta
import re

def parse_xls(text, filename):
    print(f"[DEBUG] Parsing Lough Boora XLS invoice: {filename}", file=sys.stderr)

    # === Open XLS file and select the first sheet
    workbook = xlrd.open_workbook(filename)
    sheet = workbook.sheet_by_index(0)

    # === Extract subtotal and total from text using regex
    subtotal_match = re.search(r'SubTotal\s+([\d.]+)', text)
    total_match = re.search(r'TOTAL\s+([\d.]+)', text)

    subtotal = subtotal_match.group(1) if subtotal_match else "0.00"
    total = total_match.group(1) if total_match else subtotal

    # === Attempt to extract Excel serial date value and convert to date
    invoice_date = "Not found"
    for row_idx in range(sheet.nrows):
        row = sheet.row_values(row_idx)
        for i, cell in enumerate(row):
            if isinstance(cell, str) and "date" in cell.lower():
                try:
                    date_value = row[i + 1]
                    if isinstance(date_value, (int, float)):
                        excel_base_date = datetime(1899, 12, 30)
                        parsed_date = excel_base_date + timedelta(days=float(date_value))
                        invoice_date = parsed_date.strftime("%d/%m/%Y")
                        print(f"[DEBUG] Parsed Excel date: {invoice_date}", file=sys.stderr)
                        break
                except Exception as e:
                    print(f"[DEBUG] Failed to parse Excel date: {e}", file=sys.stderr)
        if invoice_date != "Not found":
            break

    # === Final structured data
    parsed_data = {
        "Filename": filename,
        "Supplier": "Lough Boora",
        "Invoice Date": invoice_date,
        "Tax Free": True,
        "Credit Note": False,
        "VAT 0%": total,     # All products are VAT exempt
        "VAT 9%": "0.00",
        "VAT 13.5%": "0.00",
        "VAT 23%": "0.00"
    }

    print(f"[DEBUG] Parsed XLS Data: {parsed_data}", file=sys.stderr)
    return parsed_data


def parse_ods(text, filename):
    """Parse Lough Boora ODS invoice from extracted text."""
    print(f"[DEBUG] Parsing Lough Boora ODS invoice: {filename}", file=sys.stderr)

    # === Extract subtotal and total from text using regex
    subtotal_match = re.search(r'SubTotal\s+([\d.]+)', text)
    total_match = re.search(r'TOTAL\s+([\d.]+)', text)

    subtotal = subtotal_match.group(1) if subtotal_match else "0.00"
    total = total_match.group(1) if total_match else subtotal

    # === Extract date from ODS text
    # Pandas outputs dates as "2026-03-02 00:00:00" or "02/03/2026"
    invoice_date = "Not found"
    date_match = re.search(r'Date\s+(\d{4}-\d{2}-\d{2})\s', text)
    if date_match:
        # Convert YYYY-MM-DD to DD/MM/YYYY
        y, m, d = date_match.group(1).split('-')
        invoice_date = f"{d}/{m}/{y}"
    else:
        date_match2 = re.search(r'Date\s+(\d{2}/\d{2}/\d{4})', text)
        if date_match2:
            invoice_date = date_match2.group(1)
        else:
            date_match3 = re.search(r'Date\s+(\d{2}/\d{2}/\d{2})', text)
            if date_match3:
                d, m, y = date_match3.group(1).split('/')
                invoice_date = f"{d}/{m}/20{y}"
    print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

    parsed_data = {
        "Filename": filename,
        "Supplier": "Lough Boora",
        "Invoice Date": invoice_date,
        "Tax Free": True,
        "Credit Note": False,
        "VAT 0%": total,
        "VAT 9%": "0.00",
        "VAT 13.5%": "0.00",
        "VAT 23%": "0.00"
    }

    print(f"[DEBUG] Parsed ODS Data: {parsed_data}", file=sys.stderr)
    return parsed_data
