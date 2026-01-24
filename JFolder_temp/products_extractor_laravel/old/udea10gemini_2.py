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

QUANTITY_SKU_REGEX: Final[Pattern[str]] = re.compile(rf"""
    ^
    (\d+)\s+                  # 1) Code
    (\d+)\s+                  # 2) Ordered
    (\d+)\s+                  # 3) Qty
    ({NUMBER})\s+             # 4) Group4 (potentially weight OR piece count)
    ({NUMBER})\s+             # 5) Group5 (potentially piece count OR SKU)
    (\S+)\s+                  # 6) Unit
    (.+?)\s+                  # 7) Description
    ({NUMBER})\s+             # 8) Price
    ({NUMBER})\s+             # 9) Sale
    {VAT_PROFIT_TOKEN}        #    VAT/profit tokens
    ({NUMBER})                # 10) Total
    $
""", re.VERBOSE)

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
    if num_str is None: # Defensive check
        return ""
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
            comma_index = -1 # Explicitly set, though it would remain -1

    if comma_index > 0:
        potential_sku = raw_sku_ct[:comma_index]
        if potential_sku.isdigit():
            sku = potential_sku
            content = raw_sku_ct[comma_index + 1:]
    elif raw_sku_ct.isdigit(): # If it's all digits, it's likely SKU
        sku = raw_sku_ct
        content = ""
    return sku.strip(), content.strip()

def _parse_line(line: str) -> Optional[Dict[str, str]]:
    """
    Attempt to parse a single line using NORMAL_REGEX, QUANTITY_SKU_REGEX, and FALLBACK_REGEX.
    Returns a dict of parsed fields, or None if no pattern matched.
    """
    # 1) NORMAL_REGEX
    m_normal = NORMAL_REGEX.match(line)
    if m_normal:
        code, ordered, qty, sku_val, unit, desc, price, sale, total = m_normal.groups()
        desc_parts = desc.split(None, 1)
        content_str: str
        description_str: str
        if desc_parts:
            content_str = f"{unit} {desc_parts[0]}"
            description_str = desc_parts[1] if len(desc_parts) > 1 else ""
        else:
            content_str = unit
            description_str = ""

        logging.debug(f"Product {code}: Matched NORMAL_REGEX.")
        return {
            "Code": code, "Ordered": ordered, "Qty": qty, "SKU": sku_val, # SKU from regex group 4
            "Content": content_str, "Description": description_str,
            "Price": _clean_number_string(price), "Sale": _clean_number_string(sale),
            "Total": _clean_number_string(total),
        }

    # 2) QUANTITY_SKU_REGEX
    m_qty_sku = QUANTITY_SKU_REGEX.match(line)
    if m_qty_sku:
        # Extract all groups from the match
        g_code, g_ordered, g_qty, g_group4_val, g_group5_val, g_unit, g_desc, g_price, g_sale, g_total = m_qty_sku.groups()

        # Clean the potentially numeric fields (group 4 and group 5)
        cleaned_group4 = _clean_number_string(g_group4_val)
        cleaned_group5 = _clean_number_string(g_group5_val)

        # Logic to determine field interpretation:
        # If group4 looks like a decimal (e.g., "4.260" from "4,260") AND
        # group5 looks like an integer (e.g., "4" from "4"),
        # then it's likely a weight-based style product (cheese example).
        is_weight_based_style = "." in cleaned_group4 and "." not in cleaned_group5

        final_csv_sku: str
        quantity_for_content_field: str

        if is_weight_based_style:
            # Interpretation for weight-based style (e.g., "code qty WGT PCS unit desc...")
            # Group4 (original "ActualQty") is the weight, which becomes the SKU for the CSV.
            # Group5 (original "SKU") is the piece count, used for the Content field's quantity.
            final_csv_sku = cleaned_group4
            quantity_for_content_field = cleaned_group5
            logging.debug(f"Product {g_code}: QUANTITY_SKU matched. Interpreted as WEIGHT_BASED style. "
                          f"Raw G4='{g_group4_val}', Raw G5='{g_group5_val}'. "
                          f"Output SKU='{final_csv_sku}', Content Qty='{quantity_for_content_field}'")
        else:
            # Standard interpretation (e.g., "code qty PIECES SKU_ID unit desc...")
            # Group4 is the piece count for the Content field.
            # Group5 is the actual product SKU for the CSV.
            final_csv_sku = cleaned_group5
            quantity_for_content_field = cleaned_group4
            logging.debug(f"Product {g_code}: QUANTITY_SKU matched. Interpreted as PIECE_BASED style. "
                          f"Raw G4='{g_group4_val}', Raw G5='{g_group5_val}'. "
                          f"Output SKU='{final_csv_sku}', Content Qty='{quantity_for_content_field}'")

        # Construct the 'Content' field for the CSV
        content_output = f"{quantity_for_content_field} {g_unit}"

        return {
            "Code": g_code,
            "Ordered": g_ordered,
            "Qty": g_qty,
            "SKU": final_csv_sku,
            "Content": content_output,
            "Description": g_desc,
            "Price": _clean_number_string(g_price),
            "Sale": _clean_number_string(g_sale),
            "Total": _clean_number_string(g_total),
        }

    # 3) FALLBACK_REGEX
    m_fallback = FALLBACK_REGEX.match(line)
    if m_fallback:
        code, ordered, qty, raw_sku_ct, desc, price, sale, total = m_fallback.groups()
        sku_fb, content_fb = _parse_fallback_sku_content(raw_sku_ct)
        logging.debug(f"Product {code}: Matched FALLBACK_REGEX. Raw SKU/Content='{raw_sku_ct}' -> Parsed SKU='{sku_fb}', Content='{content_fb}'")
        return {
            "Code": code, "Ordered": ordered, "Qty": qty, "SKU": sku_fb,
            "Content": content_fb, "Description": desc,
            "Price": _clean_number_string(price), "Sale": _clean_number_string(sale),
            "Total": _clean_number_string(total),
        }

    # If line starts with a code but didn't match any primary regex, it will be caught by the calling function.
    # No explicit logging here as the caller handles "unmatched_lines_details".
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
                text = page.extract_text(x_tolerance=1, y_tolerance=2)
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

                    parsed = _parse_line(line) # _parse_line now includes debug logs for matches
                    if parsed:
                        parsed["SourcePDF"] = pdf_filename
                        products.append(parsed)
                        # Successful parse logging is now handled within _parse_line for matched regex
                    elif LINE_STARTS_WITH_CODE_REGEX.match(line):
                        tokens = line.split()
                        token_count = len(tokens)
                        # Extract potential product code for more informative unmatched log
                        product_code_debug = tokens[0] if tokens else "N/A"

                        log_msg_parts = [
                            f"P{page_num} L{line_num}: Unmatched line (starts with code '{product_code_debug}' but not parsed by any pattern).",
                            f"Tokens: {token_count}."
                        ]
                        if token_count >= 13: # Arbitrary threshold for "complex"
                            log_msg_parts.append("This may be a complex product line needing regex review.")

                        full_log_msg = " ".join(log_msg_parts)
                        logging.warning(full_log_msg)
                        logging.warning(f"  └─ Unmatched line content: {line}")
                        unmatched_lines_details.append((page_num, line_num, line, token_count))
                    else:
                        # This case means the line was not empty, capture was true, but it didn't start with a code.
                        logging.debug(f"P{page_num} L{line_num}: Ignored non-product-like line (doesn't start with code): {line}")


    except Exception as e:
        logging.error(f"❌ Failed to process {pdf_filename}: {e}", exc_info=True)
        return []

    if unmatched_lines_details:
        logging.info(f"🔍 Summary of unmatched lines (starting with code) in {pdf_filename}:")
        for p, ln, text_content, tk_count in unmatched_lines_details:
            extra_info = "(POTENTIAL COMPLEX PRODUCT LINE)" if tk_count >=13 else ""
            logging.info(f"  • P{p} L{ln} (Tokens: {tk_count}): {text_content} {extra_info}")

    logging.info(f"Found {len(products)} products in {pdf_filename}.")
    return products

def parse_all_pdfs_to_csv(pdf_folder: str, output_csv_path: str) -> None:
    """
    Parse all PDFs in a folder and write extracted product data to a CSV.
    """
    all_products: List[Dict[str, str]] = []
    logging.info(f"Scanning folder: {os.path.abspath(pdf_folder)}")

    pdf_files_found = 0
    # Sort files for consistent processing order, helpful for debugging
    for fname in sorted(os.listdir(pdf_folder)):
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

    logging.info(f"Writing {len(all_products)} products to {os.path.abspath(output_csv_path)}")
    try:
        with open(output_csv_path, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=CSV_FIELDNAMES, extrasaction='ignore')
            writer.writeheader()
            writer.writerows(all_products)
        logging.info(f"✅ CSV written: {os.path.abspath(output_csv_path)}")
    except Exception as e:
        logging.error(f"❌ Failed to write CSV: {e}", exc_info=True)

def main():
    parser = argparse.ArgumentParser(
        description="Extract product data from PDF invoices/reports into a CSV file."
    )
    parser.add_argument(
        "pdf_folder",
        help="Folder containing the PDF files. Defaults to current directory if not provided.",
        nargs="?",
        default=os.getcwd()
    )
    parser.add_argument(
        "-o", "--output",
        dest="output_csv",
        default="udea_combined_output.csv",
        help="Output CSV file name. If a relative path, it's relative to the script's directory or CWD."
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
    logging.getLogger().setLevel(args.loglevel) # Set the global logging level

    input_folder = args.pdf_folder

    # Determine output path. If output_csv is absolute, use it. Else, join with script/CWD.
    if os.path.isabs(args.output_csv):
        output_path = args.output_csv
    else:
        # Place output in the script's directory if __file__ is defined and absolute, otherwise CWD.
        script_dir = os.path.dirname(os.path.abspath(__file__)) if "__file__" in locals() and hasattr(locals()["__file__"], "startswith") and os.path.isabs(locals()["__file__"]) else os.getcwd()
        output_path = os.path.join(script_dir, args.output_csv)

    logging.info(f"Input folder: {os.path.abspath(input_folder)}")
    logging.info(f"Output CSV will be: {os.path.abspath(output_path)}")

    if not os.path.isdir(input_folder):
        logging.error(f"❌ Input folder not found or is not a directory: {os.path.abspath(input_folder)}")
        return

    parse_all_pdfs_to_csv(input_folder, output_path)

if __name__ == "__main__":
    main()
