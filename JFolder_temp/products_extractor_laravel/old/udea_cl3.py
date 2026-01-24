import os
import re
import csv
import argparse
import logging
from typing import List, Dict, Optional, Tuple, Pattern, Final
import pdfplumber
import math # For math.isclose or manual tolerance

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
    (\d+)\s+                  # 4) SKU (digits only - typically 1 for this pattern, or case qty)
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
    ({NUMBER})\s+             # 4) Group4 (potentially weight OR piece count for Content)
    ({NUMBER})\s+             # 5) Group5 (potentially piece count for Content OR SKU value)
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
    (\S+)\s+                  # 4) raw_sku_content (SKU is parsed from this)
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

# Enhanced CSV field names (for new Laravel system)
CSV_FIELDNAMES_ENHANCED: Final[List[str]] = [
    "Code", "Ordered", "Qty", "SKU", "Content", "Description",
    "Price", "Sale", "Total", "Calculated_Total", "Validation_Status", "SourcePDF"
]

# Legacy CSV field names (for old system compatibility)
CSV_FIELDNAMES_LEGACY: Final[List[str]] = [
    "Code", "Ordered", "Qty", "SKU", "Content", "Description",
    "Price", "Sale", "Total", "SourcePDF"
]

# --- Logging Setup ---
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

# --- Helper Functions ---

def _clean_number_string(num_str: str) -> str:
    """Convert European-style numbers (e.g., '1.234,56') to standard float format ('1234.56')."""
    if num_str is None:
        return ""
    return num_str.replace(".", "").replace(",", ".")

def _preprocess_line(line: str) -> str:
    """
    Pre-process lines to fix common PDF extraction issues before parsing.
    """
    import re
    
    # Fix merged unit names like "1kilogram" -> "1 kilogram"
    line = re.sub(r'(\d)([a-z])', r'\1 \2', line)
    
    # Fix weight/qty corruption patterns like "1 118,140 1 kilogram" -> "1 1 18,140 1 kilogram"
    # This happens when PDF extraction merges "1 18,140" into "118,140"
    # Pattern: (code) (ordered) (large_number_with_comma) (single_digit)(unit)
    weight_corruption_pattern = re.compile(r'^(\d+\s+\d+)\s+(\d{3},\d+)\s+(\d)(\s*)(kilogram|gram|litre|millilitre|liter|kg|g|l|ml)')
    match = weight_corruption_pattern.match(line)
    if match:
        # Check if this is likely a weight corruption by seeing if the large number starts with "11" or "12"
        large_num = match.group(2)
        if large_num.startswith(('11', '12')):
            # Extract the actual weight by removing the first "1"
            actual_weight = large_num[1:]
            # Reconstruct: code ordered 1 weight digit unit
            fixed_line = f"{match.group(1)} 1 {actual_weight} {match.group(3)}{match.group(4)}{match.group(5)}"
            remainder = line[match.end():]
            line = fixed_line + remainder
            logging.debug(f"Fixed weight/qty corruption: '{large_num}' -> '1 {actual_weight}'")
    
    return line

def _detect_and_fix_price_corruption(parsed_data: Dict[str, str], original_line: str) -> Dict[str, str]:
    """
    Detect and fix cases where price is corrupted into the product description.
    Example: "Soap 2,00" becomes "Soa2,p00" in the extracted text.
    """
    import re
    
    # Check if description ends with a pattern like "word[digit],[digit][digit]"
    desc = parsed_data.get("Description", "")
    corruption_pattern = re.search(r'([a-zA-Z]+)(\d+),(\w+)(\d{2})$', desc)
    
    if corruption_pattern:
        # Extract the corrupted price from the description
        word_part = corruption_pattern.group(1)
        price_part1 = corruption_pattern.group(2)
        price_part2 = corruption_pattern.group(4)
        
        # Reconstruct the price
        reconstructed_price = f"{price_part1}.{price_part2}"
        
        # Fix the description by removing the corrupted part
        fixed_desc = desc[:corruption_pattern.start()] + word_part
        
        # Shift all price-related fields
        # The current "Price" is likely the "Sale" price
        # The current "Sale" is likely something else
        parsed_data_copy = parsed_data.copy()
        parsed_data_copy["Description"] = fixed_desc.strip()
        parsed_data_copy["Sale"] = parsed_data_copy.get("Price", "")
        parsed_data_copy["Price"] = reconstructed_price
        
        logging.debug(f"🔧 Fixed corrupted price in description: '{desc}' -> '{fixed_desc}', extracted price: {reconstructed_price}")
        
        return parsed_data_copy
    
    return parsed_data

def _parse_fallback_sku_content(raw_sku_ct: str) -> Tuple[str, str]:
    """
    Split the combined SKU/Content field from the fallback pattern.
    The first part that looks like a number (int or decimal) is SKU, rest is content.
    If no clear numeric part, whole thing might be SKU or Content based on context (here, assumes SKU).
    """
    sku = raw_sku_ct # Default to whole string as SKU if no split
    content = ""

    # Try to find the first numeric part (could be integer or decimal like "1" or "1,0" or "1.000,00")
    # This regex looks for a number at the beginning of the string, possibly followed by non-digits
    match = re.match(rf"({NUMBER})(.*)", raw_sku_ct)
    if match:
        potential_sku = match.group(1)
        remaining_content = match.group(2).strip()

        # Check if the potential_sku is indeed a number by cleaning and trying to float it
        cleaned_potential_sku = _clean_number_string(potential_sku)
        try:
            float(cleaned_potential_sku)
            sku = cleaned_potential_sku # Assign the cleaned number as SKU
            content = remaining_content.strip(", ") # Remove leading/trailing commas/spaces from content
        except ValueError:
            # If potential_sku wasn't a valid number after cleaning, revert to default
            # This case is less likely if NUMBER regex is robust
            logging.debug(f"Fallback: '{potential_sku}' from '{raw_sku_ct}' not a valid SKU number, treating whole as SKU.")
            sku = _clean_number_string(raw_sku_ct) # Clean the whole thing as SKU
            content = ""
    else: # No numeric prefix found, assume whole string is SKU and clean it
        sku = _clean_number_string(raw_sku_ct)
        content = ""
        logging.debug(f"Fallback: No numeric prefix in '{raw_sku_ct}', treating cleaned whole as SKU: '{sku}'")

    return sku.strip(), content.strip()


def _parse_line(line: str) -> Optional[Dict[str, str]]:
    """
    Attempt to parse a single line using NORMAL_REGEX, QUANTITY_SKU_REGEX, and FALLBACK_REGEX.
    Returns a dict of parsed fields, or None if no pattern matched.
    The SKU field in the returned dict should represent the multiplier for Qty*Price*SKU calculation.
    """
    # 1) NORMAL_REGEX
    m_normal = NORMAL_REGEX.match(line)
    if m_normal:
        code, ordered, qty, sku_val_regex, unit, desc, price, sale, total = m_normal.groups()
        desc_parts = desc.split(None, 1)
        content_str: str
        description_str: str
        if desc_parts:
            content_str = f"{unit} {desc_parts[0]}"
            description_str = desc_parts[1] if len(desc_parts) > 1 else ""
        else:
            content_str = unit
            description_str = ""

        logging.debug(f"Product {code}: Matched NORMAL_REGEX. SKU from regex: '{sku_val_regex}'")
        # For NORMAL_REGEX, the SKU (group 4) is typically '1' or a case quantity.
        # It's already the multiplier.
        return {
            "Code": code, "Ordered": ordered, "Qty": qty, "SKU": _clean_number_string(sku_val_regex),
            "Content": content_str, "Description": description_str,
            "Price": _clean_number_string(price), "Sale": _clean_number_string(sale),
            "Total": _clean_number_string(total),
        }

    # 2) QUANTITY_SKU_REGEX - Enhanced logic for weight-based products
    m_qty_sku = QUANTITY_SKU_REGEX.match(line)
    if m_qty_sku:
        g_code, g_ordered, g_qty, g_group4_val, g_group5_val, g_unit, g_desc, g_price, g_sale, g_total = m_qty_sku.groups()

        cleaned_group4 = _clean_number_string(g_group4_val) # Potential weight or content qty
        cleaned_group5 = _clean_number_string(g_group5_val) # Potential content qty or SKU identifier/multiplier

        # Enhanced logic: For cheese products and similar items, we need to determine
        # which value is the actual SKU multiplier for the calculation
        # Check if both values are decimals (indicating weight-based products)
        has_decimal_group4 = '.' in cleaned_group4
        has_decimal_group5 = '.' in cleaned_group5
        
        # For products like cheese where both are decimals, we need to check the unit
        # If unit is "kilogram" or "gram" and both have decimals, group4 is the actual weight (SKU multiplier)
        if has_decimal_group4 and has_decimal_group5 and g_unit.lower() in ["kilogram", "gram"]:
            # This is a weight-based product where:
            # - Group4 is the actual weight delivered (and the SKU multiplier)
            # - Group5 is just a reference value (maybe price per kg or similar)
            final_csv_sku_value = cleaned_group4
            quantity_for_content_display = cleaned_group5
            logging.debug(f"Product {g_code}: QUANTITY_SKU matched. KILOGRAM WEIGHT_BASED. "
                          f"CSV SKU (multiplier) from G4: '{final_csv_sku_value}', Reference value from G5: '{quantity_for_content_display}'.")
        elif has_decimal_group4 and not has_decimal_group5:
            # Original weight-based style
            final_csv_sku_value = cleaned_group4
            quantity_for_content_display = cleaned_group5
            logging.debug(f"Product {g_code}: QUANTITY_SKU matched. WEIGHT_BASED style. "
                          f"CSV SKU (multiplier) from G4: '{final_csv_sku_value}', Content Qty from G5: '{quantity_for_content_display}'.")
        else:
            # Standard interpretation or case-based:
            # Group5 is the SKU (multiplier, e.g., "1", "6", "12").
            # Group4 is the quantity for display in "Content" field.
            final_csv_sku_value = cleaned_group5
            quantity_for_content_display = cleaned_group4
            logging.debug(f"Product {g_code}: QUANTITY_SKU matched. PIECE/CASE_BASED style. "
                          f"CSV SKU (multiplier) from G5: '{final_csv_sku_value}', Content Qty from G4: '{quantity_for_content_display}'.")

        content_output_display = f"{quantity_for_content_display} {g_unit}"

        return {
            "Code": g_code,
            "Ordered": g_ordered,
            "Qty": g_qty,
            "SKU": final_csv_sku_value, # This SKU is used as the multiplier
            "Content": content_output_display, # This is for display
            "Description": g_desc,
            "Price": _clean_number_string(g_price),
            "Sale": _clean_number_string(g_sale),
            "Total": _clean_number_string(g_total),
        }

    # 3) FALLBACK_REGEX
    m_fallback = FALLBACK_REGEX.match(line)
    if m_fallback:
        code, ordered, qty, raw_sku_ct, desc, price, sale, total = m_fallback.groups()
        # For fallback, _parse_fallback_sku_content tries to extract SKU.
        # This extracted SKU is assumed to be the multiplier.
        sku_fb_multiplier, content_fb_display = _parse_fallback_sku_content(raw_sku_ct)

        logging.debug(f"Product {code}: Matched FALLBACK_REGEX. Raw SKU/Content='{raw_sku_ct}' -> Parsed CSV SKU (multiplier)='{sku_fb_multiplier}', Content='{content_fb_display}'")
        return {
            "Code": code, "Ordered": ordered, "Qty": qty, "SKU": sku_fb_multiplier,
            "Content": content_fb_display, "Description": desc,
            "Price": _clean_number_string(price), "Sale": _clean_number_string(sale),
            "Total": _clean_number_string(total),
        }
    return None

# --- Core Logic ---

def extract_products_from_pdf(pdf_path: str, verbose: bool = False, summary_mode: bool = False) -> List[Dict[str, str]]:
    """
    Extract product entries from the specified PDF.
    Returns a list of dicts for each parsed line.
    """
    products: List[Dict[str, str]] = []
    unmatched_lines_details: List[Tuple[int, int, str, int]] = []
    capture = False
    pdf_filename = os.path.basename(pdf_path)
    calculation_tolerance = 0.015

    try:
        with pdfplumber.open(pdf_path) as pdf:
            if verbose:
                logging.info(f"Processing PDF: {pdf_filename} ({len(pdf.pages)} pages)")
            elif not summary_mode:
                print(f"\n📄 {pdf_filename}:", end=" ", flush=True)
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

                    # Pre-process line to fix PDF extraction issues
                    preprocessed_line = _preprocess_line(line)
                    if preprocessed_line != line:
                        logging.debug(f"P{page_num} L{line_num}: Pre-processed line")
                        
                    parsed = _parse_line(preprocessed_line)
                    if parsed:
                        parsed["SourcePDF"] = pdf_filename
                        
                        # Check for and fix price corruption in description
                        parsed = _detect_and_fix_price_corruption(parsed, line)

                        # --- Always use Qty * Price * SKU == Total validation ---
                        validation_status = "PASS"
                        calculated_total = 0.0

                        try:
                            qty_val_str = parsed.get("Qty")
                            price_val_str = parsed.get("Price")
                            total_val_str = parsed.get("Total")
                            # SKU from parsed data is now always the multiplier
                            sku_multiplier_str = parsed.get("SKU")
                            product_code_for_val = parsed.get("Code", "N/A")

                            if qty_val_str and price_val_str and total_val_str and sku_multiplier_str:
                                qty_f = float(qty_val_str)
                                price_f = float(price_val_str)
                                total_f_pdf = float(total_val_str)
                                sku_f = float(sku_multiplier_str) # SKU is the multiplier

                                calculated_total = qty_f * price_f * sku_f

                                # Handle case where SKU might be 0, which would make expected_total 0
                                # If SKU is 0 and PDF total is also 0, it's valid.
                                # If SKU is 0 and PDF total is non-zero, it's a mismatch.
                                if sku_f == 0 and total_f_pdf != 0:
                                    validation_status = "FAIL"
                                    if verbose:
                                        logging.warning(
                                            f"🚩 P{page_num} L{line_num}: Product Code {product_code_for_val} - SKU is 0 but PDF Total is non-zero ({total_f_pdf:.2f}). "
                                            f"Qty*Price*SKU ({qty_f}*{price_f}*0 = 0.00). "
                                            f"Original line: '{line}'"
                                        )
                                    else:
                                        print(f"\n  🚩 Product {product_code_for_val}: Calculation mismatch (SKU=0)", end="")
                                elif sku_f == 0 and total_f_pdf == 0: # Q*P*0 = 0, matches PDF total 0
                                    logging.debug(f"Product {product_code_for_val}: Qty*Price*SKU(0) = 0, matches PDF Total 0. Validation OK.")
                                    validation_status = "PASS"
                                    # No warning needed here, calculation is correct
                                else: # SKU is non-zero or SKU is zero and total is zero
                                    expected_total_f = qty_f * price_f * sku_f
                                    calc_expression = f"{qty_f}*{price_f}*{sku_f:.3f}"

                                    if abs(expected_total_f - total_f_pdf) > calculation_tolerance:
                                        validation_status = "FAIL"
                                        if verbose:
                                            logging.warning(
                                                f"🚩 P{page_num} L{line_num}: Product Code {product_code_for_val} - Potential data issue. "
                                                f"Qty*Price*SKU ({calc_expression} = {expected_total_f:.2f}) "
                                                f"does not match Total from PDF ({total_f_pdf:.2f}). "
                                                f"Original line: '{line}'"
                                            )
                                        else:
                                            print(f"\n  🚩 Product {product_code_for_val}: {calc_expression}={expected_total_f:.2f} ≠ {total_f_pdf:.2f}", end="")
                                    else:
                                        validation_status = "PASS"
                            else:
                                validation_status = "MISSING_FIELDS"
                                missing_fields = []
                                if not qty_val_str: missing_fields.append("Qty")
                                if not price_val_str: missing_fields.append("Price")
                                if not total_val_str: missing_fields.append("Total")
                                if not sku_multiplier_str: missing_fields.append("SKU (for multiplier)")
                                logging.debug(f"P{page_num} L{line_num}: Product Code {product_code_for_val} - Missing {', '.join(missing_fields)} for validation.")

                        except ValueError as ve:
                            validation_status = "ERROR"
                            logging.error(
                                f"P{page_num} L{line_num}: Product Code {parsed.get('Code', 'N/A')} - Could not convert Qty/Price/SKU/Total to number for validation: {ve}. Line: '{line}'"
                            )
                        except Exception as e_val:
                             validation_status = "ERROR"
                             logging.error(
                                f"P{page_num} L{line_num}: Product Code {parsed.get('Code', 'N/A')} - Error during validation: {e_val}. Line: '{line}'"
                            )
                        # --- End of validation ---

                        # Add validation fields to parsed data
                        parsed["Calculated_Total"] = f"{calculated_total:.2f}"
                        parsed["Validation_Status"] = validation_status

                        products.append(parsed)

                    elif LINE_STARTS_WITH_CODE_REGEX.match(line):
                        tokens = line.split()
                        token_count = len(tokens)
                        product_code_debug = tokens[0] if tokens else "N/A"
                        
                        # Check if this looks like a potential product line
                        # Products typically have: price patterns, units, and certain keywords
                        has_price_pattern = any(re.match(r'\d+[.,]\d{2}', token) for token in tokens)
                        has_unit_keywords = any(token.lower() in ['kilogram', 'gram', 'litre', 'millilitre', 'kg', 'g', 'l', 'ml', 'pc', 'stuks'] for token in tokens)
                        has_percentage = any('%' in token for token in tokens)
                        
                        # Lines with prices, units, and percentages are likely products
                        is_likely_product = has_price_pattern and (has_unit_keywords or has_percentage)

                        log_msg_parts = [
                            f"P{page_num} L{line_num}: Unmatched line (starts with code '{product_code_debug}' but not parsed by any pattern).",
                            f"Tokens: {token_count}."
                        ]
                        
                        if is_likely_product and token_count >= 10:
                            log_msg_parts.append("🔴 LIKELY PRODUCT LINE - needs regex fix!")
                        elif token_count >= 13:
                            log_msg_parts.append("This may be a complex product line needing regex review.")

                        full_log_msg = " ".join(log_msg_parts)
                        if verbose:
                            logging.warning(full_log_msg)
                            logging.warning(f"  └─ Unmatched line content: {line}")
                        unmatched_lines_details.append((page_num, line_num, line, token_count, is_likely_product))
                    else:
                        logging.debug(f"P{page_num} L{line_num}: Ignored non-product-like line (doesn't start with code): {line}")

    except Exception as e:
        logging.error(f"❌ Failed to process {pdf_filename}: {e}", exc_info=True)
        return []

    # Only show detailed unmatched lines in verbose mode or if there are likely products
    likely_products = [item for item in unmatched_lines_details if item[4]]  # item[4] is is_likely_product
    
    if verbose and unmatched_lines_details:
        logging.info(f"🔍 Summary of unmatched lines (starting with code) in {pdf_filename}:")
        for p, ln, text_content, tk_count, is_likely in unmatched_lines_details:
            extra_info = "🔴 LIKELY PRODUCT!" if is_likely else "(POTENTIAL COMPLEX PRODUCT LINE)" if tk_count >= 13 else ""
            logging.info(f"  • P{p} L{ln} (Tokens: {tk_count}): {text_content} {extra_info}")
    elif likely_products and not summary_mode:
        print(f"\n  ⚠️  Found {len(likely_products)} likely product lines that couldn't be parsed:")
        for p, ln, text_content, tk_count, _ in likely_products:
            print(f"    • Page {p}, Line {ln} ({tk_count} tokens): {text_content}")
    
    if verbose:
        logging.info(f"Found {len(products)} products in {pdf_filename}.")
    elif not summary_mode:
        print(f"{len(products)} products", end="", flush=True)
        if len(unmatched_lines_details) > 0:
            non_product_count = len(unmatched_lines_details) - len(likely_products)
            if non_product_count > 0:
                print(f" ({non_product_count} non-product lines ignored)", end="", flush=True)
    return products

def write_legacy_csv(all_products: List[Dict[str, str]], output_base: str, summary_mode: bool = False) -> None:
    """Write legacy format CSV with 10 columns for old system compatibility"""
    # Generate legacy filename from base path
    if output_base.endswith('.csv'):
        legacy_path = output_base.replace('.csv', '_legacy.csv')
    else:
        legacy_path = f"{output_base}_legacy.csv"

    try:
        with open(legacy_path, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=CSV_FIELDNAMES_LEGACY, extrasaction='ignore')
            writer.writeheader()
            writer.writerows(all_products)

        if not summary_mode:
            print(f"📄 Legacy CSV saved: {os.path.basename(legacy_path)}")
    except Exception as e:
        print(f"❌ Failed to write legacy CSV: {e}")

def parse_all_pdfs_to_csv(pdf_folder: str, output_csv_path: str, verbose: bool = False, summary_mode: bool = False) -> None:
    """
    Parse all PDFs in a folder and write extracted product data to both enhanced and legacy CSV formats.
    """
    all_products: List[Dict[str, str]] = []
    if not summary_mode:
        print(f"\n📁 Scanning folder: {os.path.abspath(pdf_folder)}")
        print("-" * 60)

    pdf_files_found = 0
    for fname in sorted(os.listdir(pdf_folder)):
        if fname.lower().endswith(".pdf"):
            pdf_files_found += 1
            path = os.path.join(pdf_folder, fname)
            all_products.extend(extract_products_from_pdf(path, verbose, summary_mode))

    if pdf_files_found == 0:
        print("❌ No PDF files found in the specified folder.")
        return
    if not all_products:
        print("⚠️ No products extracted from any PDF files.")
        return

    if not summary_mode:
        print(f"\n\n📦 Total: {len(all_products)} products from {pdf_files_found} PDFs")
    else:
        print(f"\n✅ Processed {pdf_files_found} PDFs → {len(all_products)} products")

    # Write enhanced CSV (with validation columns)
    try:
        with open(output_csv_path, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=CSV_FIELDNAMES_ENHANCED, extrasaction='ignore')
            writer.writeheader()
            writer.writerows(all_products)
        if not summary_mode:
            print(f"✅ Enhanced CSV saved: {os.path.basename(output_csv_path)}")
            if verbose:
                logging.info(f"Full path: {os.path.abspath(output_csv_path)}")
        else:
            print(f"📄 Enhanced output: {os.path.basename(output_csv_path)}")
    except Exception as e:
        print(f"❌ Failed to write enhanced CSV: {e}")
        return

    # Write legacy CSV (without validation columns)
    write_legacy_csv(all_products, output_csv_path, summary_mode)

def main():
    parser = argparse.ArgumentParser(
        description="Extract product data from PDF invoices/reports into enhanced and legacy CSV files (v3)."
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
        default="udea_cl3_output.csv",
        help="Output CSV file name. Both enhanced and legacy (_legacy.csv) versions will be created."
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_const",
        dest="loglevel",
        const=logging.DEBUG,
        default=logging.INFO,
        help="Increase output verbosity to show debug logs."
    )
    parser.add_argument(
        "-s", "--summary",
        action="store_true",
        help="Summary mode - minimal output, only show final results"
    )

    args = parser.parse_args()
    logging.getLogger().setLevel(args.loglevel)

    input_folder = args.pdf_folder

    if os.path.isabs(args.output_csv):
        output_path = args.output_csv
    else:
        script_dir = os.path.dirname(os.path.abspath(__file__)) if "__file__" in locals() and hasattr(locals()["__file__"], "startswith") and os.path.isabs(locals()["__file__"]) else os.getcwd()
        output_path = os.path.join(script_dir, args.output_csv)

    if args.loglevel == logging.DEBUG:
        logging.info(f"Input folder: {os.path.abspath(input_folder)}")
        logging.info(f"Output CSV will be: {os.path.abspath(output_path)}")

    if not os.path.isdir(input_folder):
        logging.error(f"❌ Input folder not found or is not a directory: {os.path.abspath(input_folder)}")
        return

    parse_all_pdfs_to_csv(input_folder, output_path, args.loglevel == logging.DEBUG, args.summary)

if __name__ == "__main__":
    main()