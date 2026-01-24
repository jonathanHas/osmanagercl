import os
import re
import csv
import pdfplumber
from tabulate import tabulate

def parse_invoice(pdf_path):
    rows = []
    potential_missed_lines_details = []

    # Keywords that help identify lines that are definitely not individual product entries.
    # Lines containing these will be skipped before attempting the main product regex.
    initial_skip_terms = [
        "Invoice", "Deliver To", "Order Ref", "Tax Code", "Regular Price", "Offer Price",
        "Page", "Notes:", "EMAIL:", "Total:", "Account No:", "TEL:", "FAX:",
        # Additions based on common invoice patterns and your sample
        "VAT Reg No.", "All goods remain the property",
        "Tax Code Rate Taxable Tax DRS Totals", "Gross Total", "Customer Ref:",
        "Subtotal", "Carriage", "Nett" # Common invoice terms
    ]

    # Specific patterns that are NOT product lines.
    # Used to avoid flagging them as "potential misses" if they fail the main regex.
    non_product_patterns_for_miss_check = [
        "Product Brand Description Ordered Qty RSP Price Tax Value", # Exact main header
        # Lines that might appear standalone and are not product items themselves
        # (The initial_skip_terms already covers "Regular Price", "Offer Price" broadly)
    ]

    with pdfplumber.open(pdf_path) as pdf:
        for page_idx, page in enumerate(pdf.pages):
            page_num = page_idx + 1
            text = page.extract_text()
            if not text: # Handle cases where a page might not extract text
                continue

            lines = text.split("\n")

            for line_idx, raw_line in enumerate(lines):
                line_on_page = line_idx + 1 # 1-based line number for reporting
                line = raw_line.strip()

                if not line: # Skip empty or whitespace-only lines
                    continue

                # 1. Initial broad filter for lines that are definitely not product items
                if any(skip_term in line for skip_term in initial_skip_terms):
                    continue

                # 2. Attempt to parse as a full product line using the strict regex
                #    Regex expects: Code, Product, Ordered, Qty, RSP, Price, Tax, Value
                #                 (S)  (?.?) (d/?d*) (d/?d*) (d.d) (d.d) (d.d) (d.d)
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
                else:
                    # 3. Line did not match the strict product regex.
                    #    Now, check if it's a "potential missed product line".

                    # 3a. Ensure it's not a known non-product structural line (like the header itself)
                    is_known_non_product_pattern = False
                    for pattern in non_product_patterns_for_miss_check:
                        if pattern in line:
                            is_known_non_product_pattern = True
                            break
                    if is_known_non_product_pattern:
                        continue

                    # 3b. Apply criteria for a "potential product line"
                    words = line.split()
                    if not words:
                        continue

                    first_word = words[0]
                    # Criterion i: First word looks like a product code (e.g., "12345A", "CODE123B")
                    # This regex checks for digits followed by a letter, case-insensitive.
                    if not re.match(r"^\d+[A-Za-z]$", first_word):
                        continue

                    # Criterion ii: Contains at least 2 decimal numbers (likely prices/values)
                    # This helps filter out lines that might start with a code but lack monetary info.
                    decimal_numbers_found = len(re.findall(r"\d+\.\d+", line))
                    if decimal_numbers_found < 2: # Requires at least two values like "10.99", "23.00"
                        continue

                    # Criterion iii: Has a reasonable number of space-separated parts.
                    # Your main regex implies about 8 significant parts. A near miss might have 5+.
                    if len(words) < 5:
                        continue

                    # If all checks pass, it's a potential missed line
                    potential_missed_lines_details.append({
                        "filename": os.path.basename(pdf_path),
                        "page": page_num,
                        "line_on_page": line_on_page,
                        "text": line
                    })
    return rows, potential_missed_lines_details

if __name__ == "__main__":
    current_dir = os.path.dirname(os.path.abspath(__file__))
    pdf_files = [f for f in os.listdir(current_dir) if f.lower().endswith('.pdf')]
    all_rows = []
    all_potential_missed_lines = [] # To store all potential misses

    if not pdf_files:
        print("❌ No PDF files found in this folder.")
    else:
        print(f"🔍 Found {len(pdf_files)} PDF file(s). Parsing...")

        for pdf_file in pdf_files:
            pdf_path = os.path.join(current_dir, pdf_file)
            try:
                parsed_rows, potential_missed = parse_invoice(pdf_path)
                all_rows.extend(parsed_rows)
                all_potential_missed_lines.extend(potential_missed)
                print(f"✅ {pdf_file}: {len(parsed_rows)} lines extracted.")
            except Exception as e:
                print(f"⚠️ Error parsing {pdf_file}: {e}")


        if all_potential_missed_lines:
            print("\n" + "="*40)
            print("⚠️ POTENTIAL MISSED PRODUCT LINES (Review Required):")
            print("="*40)
            for item in all_potential_missed_lines:
                print(f"  📄 File: {item['filename']}")
                print(f"     Page: {item['page']}, Line on page: {item['line_on_page']}")
                print(f"     Text: \"{item['text']}\"\n")
            print("="*40 + "\n")

        if all_rows:
            csv_output_filename = "combined_iih_invoices.csv"
            csv_output_path = os.path.join(current_dir, csv_output_filename)

            # Ensure all dictionaries in all_rows have the same keys for DictWriter
            # This uses the keys from the first row as the standard.
            # If all_rows can be empty, this needs protection.
            field_names = all_rows[0].keys() if all_rows else []

            if field_names:
                with open(csv_output_path, mode='w', newline='', encoding='utf-8') as file:
                    writer = csv.DictWriter(file, fieldnames=field_names)
                    writer.writeheader()
                    writer.writerows(all_rows)

                print(f"\n📋 Combined CSV Preview (first 10 rows of {len(all_rows)} total):\n")
                # tabulate can also handle a list of dicts directly
                print(tabulate(all_rows[:10], headers="keys", tablefmt="grid"))
                print(f"\n✅ All successfully parsed data written to {csv_output_path}")
            else:
                 print("ℹ️ No data successfully parsed into rows to write to CSV.")

        if not all_rows and not all_potential_missed_lines:
            print("⚠️ No product lines found or suspected in any PDF.")
        elif not all_rows and all_potential_missed_lines:
            print("ℹ️ No product lines were successfully parsed, but potential misses were identified (see above).")
