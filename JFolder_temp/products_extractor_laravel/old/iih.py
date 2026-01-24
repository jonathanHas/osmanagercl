import os
import re
import csv
import pdfplumber
from tabulate import tabulate

def parse_invoice_to_csv(pdf_path, csv_path):
    rows = []

    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            text = page.extract_text()
            lines = text.split("\n")

            for line in lines:
                # Skip non-product lines
                if any(skip in line for skip in [
                    "Invoice", "Deliver To", "Order Ref", "Tax Code", "Regular Price", "Offer Price",
                    "Page", "Notes:", "EMAIL:", "Total:", "Account No:", "TEL:", "FAX:"
                ]):
                    continue

                # Match product lines ending in Qty, RSP, Price, Tax, Value
                match = re.match(r"^(\S+)\s+(.*?)\s+(\d+/?\d*)\s+(\d+/?\d*)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)$",line)

                if match:
                    code = match.group(1).strip()
                    product = match.group(2).strip()
                    ordered_qty = match.group(3).strip()
                    delivered_qty = match.group(4).strip()
                    rsp = match.group(5)
                    price = match.group(6)
                    tax = match.group(7)
                    value = match.group(8)

                    rows.append({
                        "Code": code,
                        "Product": product,
                        "Ordered": ordered_qty,
                        "Qty": delivered_qty,
                        "RSP": rsp,
                        "Price": price,
                        "Tax": tax,
                        "Value": value,
                    })

    if not rows:
        print("No product lines found.")
        return

    # Show preview in terminal
    print("\n📋 CSV Preview:\n")
    print(tabulate(rows, headers="keys", tablefmt="grid"))


    # Write to CSV
    with open(csv_path, mode='w', newline='', encoding='utf-8') as file:
        writer = csv.DictWriter(file, fieldnames=rows[0].keys())
        writer.writeheader()
        writer.writerows(rows)

    print(f"✅ Extracted {len(rows)} products to {csv_path}")


if __name__ == "__main__":
    # Look for first PDF in current folder
    current_dir = os.path.dirname(os.path.abspath(__file__))
    pdf_files = [f for f in os.listdir(current_dir) if f.lower().endswith('.pdf')]

    if not pdf_files:
        print("❌ No PDF files found in this folder.")
    else:
        pdf_file = pdf_files[0]
        pdf_path = os.path.join(current_dir, pdf_file)
        csv_output = os.path.splitext(pdf_path)[0] + ".csv"

        print(f"🔍 Found PDF: {pdf_file}")
        parse_invoice_to_csv(pdf_path, csv_output)
