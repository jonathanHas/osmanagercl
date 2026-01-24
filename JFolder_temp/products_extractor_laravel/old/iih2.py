import os
import re
import csv
import pdfplumber
from tabulate import tabulate

def parse_invoice(pdf_path):
    rows = []

    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            text = page.extract_text()
            lines = text.split("\n")

            for line in lines:
                if any(skip in line for skip in [
                    "Invoice", "Deliver To", "Order Ref", "Tax Code", "Regular Price", "Offer Price",
                    "Page", "Notes:", "EMAIL:", "Total:", "Account No:", "TEL:", "FAX:"
                ]):
                    continue

                match = re.match(r"^(\S+)\s+(.*?)\s+(\d+/?\d*)\s+(\d+/?\d*)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)$", line)

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
                        "Filename": os.path.basename(pdf_path),
                        "Code": code,
                        "Product": product,
                        "Ordered": ordered_qty,
                        "Qty": delivered_qty,
                        "RSP": rsp,
                        "Price": price,
                        "Tax": tax,
                        "Value": value,
                    })
    return rows

if __name__ == "__main__":
    current_dir = os.path.dirname(os.path.abspath(__file__))
    pdf_files = [f for f in os.listdir(current_dir) if f.lower().endswith('.pdf')]
    all_rows = []

    if not pdf_files:
        print("❌ No PDF files found in this folder.")
    else:
        print(f"🔍 Found {len(pdf_files)} PDF file(s). Parsing...")

        for pdf_file in pdf_files:
            pdf_path = os.path.join(current_dir, pdf_file)
            parsed_rows = parse_invoice(pdf_path)
            all_rows.extend(parsed_rows)
            print(f"✅ {pdf_file}: {len(parsed_rows)} lines extracted.")

        if all_rows:
            csv_output = os.path.join(current_dir, "combined_iih_invoices.csv")
            with open(csv_output, mode='w', newline='', encoding='utf-8') as file:
                writer = csv.DictWriter(file, fieldnames=all_rows[0].keys())
                writer.writeheader()
                writer.writerows(all_rows)

            print(f"\n📋 Combined CSV Preview:\n")
            print(tabulate(all_rows[:10], headers="keys", tablefmt="grid"))  # show first 10 rows
            print(f"\n✅ All data written to {csv_output}")
        else:
            print("⚠️ No product lines found in any PDF.")
