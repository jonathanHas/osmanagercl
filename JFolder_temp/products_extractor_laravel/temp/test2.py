import re
import csv
import sys
import pdfplumber

def parse_invoice_to_csv(pdf_path, csv_path):
    rows = []

    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            text = page.extract_text()
            lines = text.split("\n")

            for line in lines:
                # Skip known non-product lines
                if any(skip in line for skip in [
                    "Invoice", "Deliver To", "Order Ref", "Tax Code", "Regular Price", "Offer Price",
                    "Page", "Notes:", "EMAIL:", "Total:", "Account No:", "TEL:", "FAX:"
                ]):
                    continue

                # Match lines ending in Qty, RSP, Price, Tax, Value
                match = re.match(r"^(.*?)\s+(\d+/?\d*)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)$", line)
                if match:
                    description = match.group(1).strip()
                    qty = match.group(2)
                    rsp = match.group(3)
                    price = match.group(4)
                    tax = match.group(5)
                    value = match.group(6)

                    rows.append({
                        "Product": description,  # Combined brand & description
                        "Qty": qty,
                        "RSP": rsp,
                        "Price": price,
                        "Tax": tax,
                        "Value": value
                    })

    if not rows:
        print("No product lines found.")
        return

    # Write to CSV
    with open(csv_path, mode='w', newline='', encoding='utf-8') as file:
        writer = csv.DictWriter(file, fieldnames=rows[0].keys())
        writer.writeheader()
        writer.writerows(rows)

    print(f"Extracted {len(rows)} product lines to {csv_path}")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python3 plumber_extract.py path_to_pdf path_to_output_csv")
    else:
        parse_invoice_to_csv(sys.argv[1], sys.argv[2])
