import os
import re
import csv
import argparse
import logging
from typing import List, Dict, Optional, Tuple, Pattern, Final
import pdfplumber

# --- Shared number sub-pattern: ints or decimals with optional thousands separators ---
NUMBER: Final[str] = r"\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?"

# --- Revised VAT/profit token for more flexibility ---
# This pattern allows for an optional first number and space,
# followed by a number (possibly with a %), and a trailing space.
# Examples: " 5 49% " or " 40% "
VAT_PROFIT_TOKEN: Final[str] = r"(?:(?:\d+\s+)?\d+%?\s+)"

# Regex patterns using the shared NUMBER pattern and revised VAT_PROFIT_TOKEN
NORMAL_REGEX: Final[Pattern[str]] = re.compile(rf"""
    ^
    (\d+)\s+                  # 1) Code
    (\d+)\s+                  # 2) Ordered
    (\d+)\s+                  # 3) Qty
    (\d+)\s+                  # 4) SKU (digits only)
    (\S+)\s+                  # 5) Content (unit)
    (.+?)\s+                  # 6) Description
    ({NUMBER})\s+             # 7) Price
    ({NUMBER})\s+             # 8) Sale (allows integer)
    {VAT_PROFIT_TOKEN}        #    VAT/profit tokens (ignored)
    ({NUMBER})                # 9) Total
    $
""", re.VERBOSE)

# Quantity / SKU / Unit Pattern (relaxed actual-qty, SKU now uses NUMBER pattern)
QUANTITY_SKU_REGEX: Final[Pattern[str]] = re.compile(rf"""
    ^
    (\d+)\s+                  # 1) Code
    (\d+)\s+                  # 2) Ordered
    (\d+)\s+                  # 3) Qty
    ({NUMBER})\s+             # 4) ActualQty (e.g., 4,520 or 1)
    ({NUMBER})\s+             # 5) SKU (now uses NUMBER pattern, e.g., 2,70)
    (\S+)\s+                  # 6) Unit
    (.+?)\s+                  # 7) Description
    ({NUMBER})\s+             # 8) Price
    ({NUMBER})\s+             # 9) Sale
    {VAT_PROFIT_TOKEN}        #    VAT/profit tokens
    ({NUMBER})                # 10) Total
    $
""", re.VERBOSE)

# Fallback pattern (also relaxes sale to allow integers, uses revised VAT_PROFIT_TOKEN)
FALLBACK_REGEX: Final[Pattern[str]] = re.compile(rf"""
    ^
    (\d+)\s+                  # 1) Code
    (\d+)\s+                  # 2) Ordered
    (\d+)\s+                  # 3) Qty
    (\S+)\s+                  # 4) raw_sku_content
    (.+?)\s+                  # 5) Description chunk
    ({NUMBER})\s+             # 6) Price
    ({NUMBER})\s+             # 7) Sale
    {VAT_PROFIT_TOKEN}        #    VAT/profit tokens
    (\d{{1,3}}(?:[.,]\d{{3}})*[.,]\d{{2}})  # 8) Total (requires two decimals)
    $
""", re.VERBOSE)

# Detect lines that start with a code
LINE_STARTS_WITH_CODE_REGEX: Final[Pattern[str]] = re.compile(r'^\d+')

# Headers that trigger the start of data capture
SECTION_HEADERS: Final[Tuple[str, ...]] = ("Underdelivery", "Products to deliver")

# Output CSV field names
CSV_FIELDNAMES: Final[List[str]] = [
    "Code", "Ordered", "Qty", "SKU", "Content", "Description",
    "Price", "Sale", "Total", "SourcePDF"
]

# --- Logging Setup ---
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

# --- Helper Functions ---

def _clean_number_string(num_str: str) -> str:
    """Convert European-style numbers (e.g., '1.234,56') to standard float format ('1234.56')."""
    return num_str.replace(".", "").replace(",", ".")

def _parse_fallback_sku_content(raw_sku_ct: str) -> Tuple[str, str]:
    """
    Split the combined SKU/Content field from the fallback pattern.
    Simple heuristic: split at the first comma (if after first character) if the prefix is digits.
    """
    sku = raw_sku_ct
    content = ""
    comma_index = -1
    if len(raw_sku_ct) > 1:
        try:
            comma_index = raw_sku_ct.index(',', 1)
        except ValueError:
            comma_index = -1

    if comma_index > 0:
        potential_sku = raw_sku_ct[:comma_index]
        if potential_sku.isdigit():
            sku = potential_sku
            content = raw_sku_ct[comma_index + 1:]
    elif raw_sku_ct.isdigit(): # If it's all digits, it's likely SKU
        sku = raw_sku_ct
        content = ""
    # Add more heuristics if needed, e.g. split by last space if a part is non-numeric
    return sku.strip(), content.strip()

def _parse_line(line: str) -> Optional[Dict[str, str]]:
    """
    Attempt to parse a single line using NORMAL_REGEX, QUANTITY_SKU_REGEX, and FALLBACK_REGEX.
    Returns a dict of parsed fields, or None if no pattern matched.
    """
    # 1) NORMAL
    m = NORMAL_REGEX.match(line)
    if m:
        code, ordered, qty, sku, unit, desc, price, sale, total = m.groups()
        desc_parts = desc.split(None, 1)
        if desc_parts:
            content = f"{unit} {desc_parts[0]}"
            description = desc_parts[1] if len(desc_parts) > 1 else ""
        else:
            content = unit
            description = ""
        return {
            "Code": code, "Ordered": ordered, "Qty": qty, "SKU": sku,
            "Content": content, "Description": description,
            "Price": _clean_number_string(price), "Sale": _clean_number_string(sale),
            "Total": _clean_number_string(total),
        }

    # 2) QUANTITY_SKU
    m = QUANTITY_SKU_REGEX.match(line)
    if m:
        # Corrected group count for QUANTITY_SKU_REGEX which has 10 groups
        code, ordered, qty, actual_qty, sku, unit, desc, price, sale, total = m.groups()
        content_val = _clean_number_string(actual_qty)
        # Check if unit is purely numeric, if so, it might be part of actual_qty or SKU error
        # For now, assume unit is correct
        content = f"{content_val} {unit}"
        return {
            "Code": code, "Ordered": ordered, "Qty": qty, "SKU": sku, # SKU is now group 5
            "Content": content, "Description": desc,
            "Price": _clean_number_string(price), "Sale": _clean_number_string(sale),
            "Total": _clean_number_string(total),
        }

    # 3) FALLBACK
    m = FALLBACK_REGEX.match(line)
    if m:
        # Corrected group count for FALLBACK_REGEX which has 8 groups
        code, ordered, qty, raw_sku_ct, desc, price, sale, total = m.groups()
        sku_fb, content_fb = _parse_fallback_sku_content(raw_sku_ct)
        # Log only if fallback parsing seems unusual or for debug
        # logging.debug(f"Fallback used for code={code}, raw='{raw_sku_ct}' -> SKU='{sku_fb}', Content='{content_fb}'")
        return {
            "Code": code, "Ordered": ordered, "Qty": qty, "SKU": sku_fb,
            "Content": content_fb, "Description": desc,
            "Price": _clean_number_string(price), "Sale": _clean_number_string(sale),
            "Total": _clean_number_string(total),
        }
    return None

# --- Core Logic ---

def extract_products_from_pdf(pdf_path: str) -> List[Dict[str, str]]:
    """
    Extract product entries from the specified PDF.
    Returns a list of dicts for each parsed line.
    """
    products: List[Dict[str, str]] = []
    unmatched_lines_details: List[Tuple[int, int, str, int]] = [] # page, line_num, text, token_count
    capture = False
    pdf_filename = os.path.basename(pdf_path)

    try:
        with pdfplumber.open(pdf_path) as pdf:
            logging.info(f"Processing PDF: {pdf_filename} ({len(pdf.pages)} pages)")
            for page_num, page in enumerate(pdf.pages, start=1):
                text = page.extract_text(x_tolerance=2, y_tolerance=2)
                if not text:
                    logging.warning(f"Page {page_num} in {pdf_filename} has no extractable text.")
                    continue

                for line_num, raw_line in enumerate(text.split("\n"), start=1):
                    line = raw_line.strip()
                    if any(header in raw_line for header in SECTION_HEADERS):
                        capture = True
                        logging.debug(f"P{page_num} L{line_num}: Capture mode ON (header found: {line})")
                        continue
                    if not capture or not line:
                        continue

                    parsed = _parse_line(line)
                    if parsed:
                        parsed["SourcePDF"] = pdf_filename
                        products.append(parsed)
                        logging.debug(f"P{page_num} L{line_num}: Parsed successfully: {line}")
                    elif LINE_STARTS_WITH_CODE_REGEX.match(line):
                        tokens = line.split()
                        token_count = len(tokens)
                        log_msg_parts = [
                            f"P{page_num} L{line_num}: Unmatched line (starts with code but not parsed by any pattern).",
                            f"Tokens: {token_count}."
                        ]
                        if token_count >= 13:
                            log_msg_parts.append("This may be a complex product line needing regex review.")

                        # Join parts and log the full original line for context
                        full_log_msg = " ".join(log_msg_parts)
                        logging.warning(full_log_msg)
                        logging.warning(f"  └─ Unmatched line content: {line}") # Log the line content separately for clarity
                        unmatched_lines_details.append((page_num, line_num, line, token_count))
                    else:
                        logging.debug(f"P{page_num} L{line_num}: Ignored non-product line: {line}")


    except Exception as e:
        logging.error(f"❌ Failed to process {pdf_filename}: {e}", exc_info=True)
        return []

    if unmatched_lines_details:
        logging.info(f"🔍 Summary of unmatched lines (starting with code) in {pdf_filename}:")
        for p, ln, text, tk_count in unmatched_lines_details:
            extra_info = "(POTENTIAL COMPLEX PRODUCT LINE)" if tk_count >=13 else ""
            logging.info(f"  • P{p} L{ln} (Tokens: {tk_count}): {text} {extra_info}")

    logging.info(f"Found {len(products)} products in {pdf_filename}.")
    return products

def parse_all_pdfs_to_csv(pdf_folder: str, output_csv_path: str) -> None:
    """
    Parse all PDFs in a folder and write extracted product data to a CSV.
    """
    all_products: List[Dict[str, str]] = []
    logging.info(f"Scanning folder: {pdf_folder}")

    pdf_files_found = 0
    for fname in os.listdir(pdf_folder):
        if fname.lower().endswith(".pdf"):
            pdf_files_found += 1
            path = os.path.join(pdf_folder, fname)
            all_products.extend(extract_products_from_pdf(path))

    if pdf_files_found == 0:
        logging.warning("No PDF files found in the specified folder.")
        return
    if not all_products:
        logging.warning("⚠️ No products extracted from any PDF files.")
        return

    logging.info(f"Writing {len(all_products)} products to {output_csv_path}")
    try:
        with open(output_csv_path, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=CSV_FIELDNAMES, extrasaction='ignore')
            writer.writeheader()
            writer.writerows(all_products)
        logging.info(f"✅ CSV written: {output_csv_path}")
    except Exception as e:
        logging.error(f"❌ Failed to write CSV: {e}", exc_info=True)

def main():
    parser = argparse.ArgumentParser(
        description="Extract product data from PDF invoices/reports into a CSV file."
    )
    parser.add_argument(
        "pdf_folder",
        help="Folder containing the PDF files. Defaults to current directory if not provided.",
        nargs="?", # Makes the argument optional
        default=os.getcwd() # Default to current working directory
    )
    parser.add_argument(
        "-o", "--output",
        dest="output_csv",
        default="udea_combined_output.csv",
        help="Output CSV file name (will be created in the script's directory or CWD)."
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_const",
        dest="loglevel",
        const=logging.DEBUG,
        default=logging.INFO,
        help="Increase output verbosity to show debug logs."
    )

    args = parser.parse_args()
    logging.getLogger().setLevel(args.loglevel)

    input_folder = args.pdf_folder

    # Determine output path: place it in the script's directory if __file__ is defined, else CWD.
    script_dir = os.path.dirname(__file__) if "__file__" in locals() else os.getcwd()
    output_path = os.path.join(script_dir, args.output_csv)


    if not os.path.isdir(input_folder):
        logging.error(f"❌ Input folder not found: {input_folder}")
        if input_folder == os.getcwd():
            logging.error("   It seems the script is being run from a directory that doesn't exist or is not accessible.")
        return

    parse_all_pdfs_to_csv(input_folder, output_path)

if __name__ == "__main__":
    main()
