import os
import re
import csv
import argparse
import logging
from typing import List, Dict, Optional, Tuple, Pattern, Final # Added typing
import pdfplumber # Keep pdfplumber

# --- Constants ---
# Regex patterns using re.VERBOSE for readability
# Added non-capturing group (?:...) for VAT/profit tokens as they aren't extracted
NORMAL_REGEX: Final[Pattern[str]] = re.compile(
    r"""^
    (\d+)\s+              # 1) Code (digits)
    (\d+)\s+              # 2) Ordered
    (\d+)\s+              # 3) Qty
    (\d+)\s+              # 4) SKU (digits only)
    (\S+)\s+              # 5) Content
    (.+?)\s+              # 6) Description
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})\s+  # 7) Price (more robust, handles thousands separators)
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})\s+  # 8) Sale (more robust)
    (?:\d+\s+\d+%?\s+)     # Non-capturing: VAT/profit tokens
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})     # 9) Total (more robust)
    $""",
    re.VERBOSE
)

# REGEX #3: Quantity/SKU/Unit Pattern
# Handles lines where quantity, SKU, and unit appear after the initial Qty field
QUANTITY_SKU_REGEX: Final[Pattern[str]] = re.compile(
    r"""^
    (\d+)\s+                          # 1) Code
    (\d+)\s+                          # 2) Ordered
    (\d+)\s+                          # 3) Qty (e.g., 1)
    (\d+,\d{3})\s+                    # 4) ActualQty (e.g., 4,520 or 0,950) - Requires comma + 3 digits
    (\d+)\s+                          # 5) SKU (e.g., 4 or 1)
    (\S+)\s+                          # 6) Unit (e.g., kilogram) - Capture non-space chars
    (.+?)\s+                          # 7) Description
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})\s+  # 8) Price
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})\s+  # 9) Sale
    (?:\d+\s+\d+%?\s+)                 # Non-capturing: VAT/profit tokens
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})     # 10) Total
    $""",
    re.VERBOSE
)

FALLBACK_REGEX: Final[Pattern[str]] = re.compile(
    r"""^
    (\d+)\s+              # 1) Code
    (\d+)\s+              # 2) Ordered
    (\d+)\s+              # 3) Qty
    (\S+)\s+              # 4) raw_sku_content (e.g., "41,200millilitre")
    (.+?)\s+              # 5) Description chunk
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})\s+  # 6) Price (more robust)
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})\s+  # 7) Sale (more robust)
    (?:\d+\s+\d+%?\s+)     # Non-capturing: VAT/profit tokens
    (\d{1,3}(?:[.,]\d{3})*[.,]\d{2})     # 8) Total (more robust)
    $""",
    re.VERBOSE
)

# Detects if line *starts with* digits (likely a product code)
LINE_STARTS_WITH_CODE_REGEX: Final[Pattern[str]] = re.compile(r'^\d+')

# Headers that trigger the start of data capture
SECTION_HEADERS: Final[Tuple[str, ...]] = ("Underdelivery", "Products to deliver")

# Field names for the output CSV (ensures consistent order)
CSV_FIELDNAMES: Final[List[str]] = [
    "Code", "Ordered", "Qty", "SKU", "Content", "Description",
    "Price", "Sale", "Total", "SourcePDF" # Added SourcePDF for traceability
]

# --- Logging Setup ---
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

# --- Helper Functions ---

def _clean_number_string(num_str: str) -> str:
    """Converts European-style numbers (e.g., '1.234,56') to standard float format ('1234.56')."""
    return num_str.replace(".", "").replace(",", ".")

def _parse_fallback_sku_content(raw_sku_ct: str) -> Tuple[str, str]:
    """
    Splits the combined SKU/Content field from the fallback pattern.
    Assumes simple logic: if a comma exists AFTER the first character,
    split there. Otherwise, assume it's all SKU. Adapt as needed.
    Example: "41,200millilitre" -> ("41", "200millilitre")
             "12345"           -> ("12345", "")
             "AB,C"            -> ("AB,C", "") # Doesn't fit the digit-first expected SKU
    """
    sku = raw_sku_ct
    content = ""
    # Find the first comma that is not the first character
    comma_index = -1
    if len(raw_sku_ct) > 1:
        try:
            comma_index = raw_sku_ct.index(',', 1) # Start search from index 1
        except ValueError:
            comma_index = -1 # No comma found after the first char

    if comma_index > 0:
        # Simple split heuristic: digit(s) before comma = SKU, rest = content
        # This might need refinement based on more examples
        potential_sku = raw_sku_ct[:comma_index]
        if potential_sku.isdigit(): # Check if the part before comma is purely digits
             sku = potential_sku
             content = raw_sku_ct[comma_index + 1:] # Take everything after the comma
        # else: keep original raw_sku_ct as sku, content remains ""

    return sku.strip(), content.strip()


def _parse_line(line: str) -> Optional[Dict[str, str]]:
    """
    Attempts to parse a single line using normal, quantity/SKU, and fallback regex patterns.
    ...
    """
    # 1) Try the normal pattern
    m_normal = NORMAL_REGEX.match(line)
    if m_normal:
        logging.debug(f"Normal pattern matched: {line[:50]}...")
        # --- MODIFICATION START ---
        code = m_normal.group(1).strip()
        ordered = m_normal.group(2).strip()
        qty = m_normal.group(3).strip()
        sku = m_normal.group(4).strip()             # e.g., "1" or "150"
        original_content_unit = m_normal.group(5).strip() # e.g., "litre" or "gram"
        original_description = m_normal.group(6).strip() # e.g., "Kefir, Rauw Power..."

        new_content = original_content_unit # Default if description is empty
        new_description = ""                # Default if description is empty or has 1 word

        # Split the original description by the first space to separate the first word/part
        description_parts = original_description.split(None, 1)

        if description_parts:
            # If there was anything in the description
            first_desc_part = description_parts[0]
            # Combine the original unit with the first part of the description
            new_content = f"{original_content_unit} {first_desc_part}"

            if len(description_parts) > 1:
                # If there was a remainder after the split, it's the new description
                new_description = description_parts[1].strip()
            # else: description had only one word, so new_description remains ""
        # else: original_description was empty, defaults are fine

        # --- MODIFICATION END ---

        return {
            "Code": code,
            "Ordered": ordered,
            "Qty": qty,
            "SKU": sku,                     # Keep original SKU (amount)
            "Content": new_content.strip(), # Combined unit + first word/part of desc
            "Description": new_description, # The rest of the original description
            "Price": _clean_number_string(m_normal.group(7)),
            "Sale": _clean_number_string(m_normal.group(8)),
            "Total": _clean_number_string(m_normal.group(9)),
        }

    # 2) Try the new Quantity/SKU pattern
    m_qty_sku = QUANTITY_SKU_REGEX.match(line)
    if m_qty_sku:
        # This block remains the same as it correctly combines amount and unit
        logging.debug(f"Quantity/SKU pattern matched: {line[:50]}...")
        actual_qty_str = _clean_number_string(m_qty_sku.group(4))
        unit = m_qty_sku.group(6).strip()
        content = f"{actual_qty_str} {unit}"
        return {
            "Code": m_qty_sku.group(1).strip(),
            "Ordered": m_qty_sku.group(2).strip(),
            "Qty": m_qty_sku.group(3).strip(),
            "SKU": m_qty_sku.group(5).strip(), # Actual SKU
            "Content": content,                # Combined quantity + unit
            "Description": m_qty_sku.group(7).strip(),
            "Price": _clean_number_string(m_qty_sku.group(8)),
            "Sale": _clean_number_string(m_qty_sku.group(9)),
            "Total": _clean_number_string(m_qty_sku.group(10)),
        }

    # 3) Try the original fallback pattern
    m_fallback = FALLBACK_REGEX.match(line)
    if m_fallback:
        # Fallback logic remains unchanged for now
        raw_sku_content = m_fallback.group(4)
        sku_fb, content_fb = _parse_fallback_sku_content(raw_sku_content)
        code_fb = m_fallback.group(1).strip()
        logging.warning(f"Using ORIGINAL FALLBACK for code={code_fb}, raw='{raw_sku_content}' -> SKU='{sku_fb}', Content='{content_fb}'")
        logging.warning(f" -> Line: {line}")
        return {
            "Code": code_fb,
            "Ordered": m_fallback.group(2).strip(),
            "Qty": m_fallback.group(3).strip(),
            "SKU": sku_fb,
            "Content": content_fb,
            "Description": m_fallback.group(5).strip(),
            "Price": _clean_number_string(m_fallback.group(6)),
            "Sale": _clean_number_string(m_fallback.group(7)),
            "Total": _clean_number_string(m_fallback.group(8)),
        }

    # 4) If neither matched, return None
    return None
# --- Core Logic Functions ---

def extract_products_from_pdf(pdf_path: str) -> List[Dict[str, str]]:
    """
    Extracts product data from designated sections within a single PDF file.

    Args:
        pdf_path: Path to the PDF file.

    Returns:
        A list of dictionaries, where each dictionary represents a parsed product row.
        Returns an empty list if the PDF cannot be opened or no products are found.
    """
    products: List[Dict[str, str]] = []
    capture: bool = False
    pdf_filename = os.path.basename(pdf_path)

    try:
        with pdfplumber.open(pdf_path) as pdf:
            logging.info(f"Processing PDF: {pdf_filename} ({len(pdf.pages)} pages)")
            for page_num, page in enumerate(pdf.pages, start=1):
                text = page.extract_text(x_tolerance=2, y_tolerance=2) # Adjust tolerance if needed
                if not text:
                    logging.warning(f"Page {page_num} in {pdf_filename} has no extractable text.")
                    continue

                lines = text.split("\n")
                logging.debug(f"Page {page_num} has {len(lines)} lines.")

                for line_num, raw_line in enumerate(lines, start=1):
                    line_stripped = raw_line.strip()

                    # Check for section headers to start/stop capture
                    # Note: Capture mode currently doesn't explicitly turn off,
                    # assuming relevant data is contiguous until EOF or next PDF.
                    # Add logic to turn `capture` off if sections have clear endings.
                    if any(header in raw_line for header in SECTION_HEADERS):
                        logging.debug(f"Capture turned ON at page {page_num}, line ~{line_num}")
                        capture = True
                        continue # Don't parse the header line itself

                    # Skip if not in capture mode or line is effectively blank
                    if not capture or not line_stripped:
                        continue

                    # Attempt to parse the line as a product
                    parsed_product = _parse_line(line_stripped)

                    if parsed_product:
                        parsed_product["SourcePDF"] = pdf_filename # Add source file info
                        products.append(parsed_product)
                        logging.debug(f"Successfully parsed line: {line_stripped[:50]}...") # Log snippet
                    elif LINE_STARTS_WITH_CODE_REGEX.match(line_stripped):
                        # Line started with digits but didn't match known patterns
                        logging.warning(f"No pattern matched line starting with code (P{page_num} L{line_num}): {line_stripped}")

    except Exception as e:
        logging.error(f"❌ Failed to process {pdf_filename}: {e}", exc_info=True) # Log traceback
        return [] # Return empty list on error for this PDF

    logging.info(f"Found {len(products)} products in {pdf_filename}.")
    return products


def parse_all_pdfs_to_csv(pdf_folder: str, output_csv_path: str) -> None:
    """
    Parses all PDF files in a specified folder and writes the combined product data to a CSV file.

    Args:
        pdf_folder: The path to the folder containing PDF files.
        output_csv_path: The path where the output CSV file should be saved.
    """
    all_products: List[Dict[str, str]] = []

    logging.info(f"Scanning folder: {pdf_folder}")
    pdf_files_found = 0
    for filename in os.listdir(pdf_folder):
        if filename.lower().endswith(".pdf"):
            pdf_files_found += 1
            pdf_path = os.path.join(pdf_folder, filename)
            pdf_products = extract_products_from_pdf(pdf_path)
            all_products.extend(pdf_products)

    if pdf_files_found == 0:
         logging.warning("No PDF files found in the specified folder.")
         return

    if not all_products:
        logging.warning("⚠️ No products extracted from any PDF files.")
        return

    logging.info(f"Writing {len(all_products)} total products to {output_csv_path}")
    try:
        with open(output_csv_path, "w", newline="", encoding="utf-8") as f:
            # Use the predefined fieldnames for consistent column order
            writer = csv.DictWriter(f, fieldnames=CSV_FIELDNAMES, extrasaction='ignore')
            writer.writeheader()
            writer.writerows(all_products)
        logging.info(f"✅ Successfully created CSV: {output_csv_path}")
    except IOError as e:
        logging.error(f"❌ Failed to write CSV file {output_csv_path}: {e}")
    except Exception as e:
        logging.error(f"❌ An unexpected error occurred during CSV writing: {e}", exc_info=True)


# --- Main Execution ---

def main():
    """Main function to parse arguments and initiate PDF processing."""
    parser = argparse.ArgumentParser(description="Extract product data from PDF invoices/reports into a CSV file.")
    parser.add_argument(
        "pdf_folder",
        help="Path to the folder containing the PDF files.",
        default=os.getcwd(), # Default to current working directory
        nargs='?' # Makes the argument optional, falling back to default
    )
    parser.add_argument(
        "-o", "--output",
        dest="output_csv",
        default="udea_combined_output.csv", # Default output filename
        help="Path for the output CSV file (default: udea_combined_output.csv in the script's directory)."
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_const",
        dest="loglevel",
        const=logging.DEBUG,
        default=logging.INFO,
        help="Increase output verbosity (show DEBUG messages).",
    )

    args = parser.parse_args()

    # Set logging level based on verbosity flag
    logging.getLogger().setLevel(args.loglevel)

    # Ensure the output path is absolute or relative to the script's location if needed
    # If args.output_csv is just a filename, os.path.join will combine it correctly
    output_path = os.path.join(os.path.dirname(__file__) if "__file__" in locals() else os.getcwd() , args.output_csv)
    input_folder = args.pdf_folder

    # Ensure the input folder exists
    if not os.path.isdir(input_folder):
        logging.error(f"❌ Input folder not found: {input_folder}")
        return

    parse_all_pdfs_to_csv(input_folder, output_path)


if __name__ == "__main__":
    main()
