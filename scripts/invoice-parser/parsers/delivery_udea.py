"""
Delivery Product Parser for UDEA B.V.

Extracts product line items from UDEA delivery PDFs for Laravel delivery import.
Returns JSON with structured product data.

Port of the udea_cl4.py logic adapted for the delivery import system.
"""

import re
import sys
import os
from typing import Dict, List, Optional, Tuple, Any, Pattern, Final

# Add parent directory to path for imports
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

try:
    from utils import extract_text
except ImportError:
    # Fallback if utils not available
    import pdfplumber
    def extract_text(pdf_path, force_ocr=False):
        try:
            with pdfplumber.open(pdf_path) as pdf:
                all_text = "\n".join(page.extract_text() or "" for page in pdf.pages)
                if all_text.strip():
                    return all_text, "pdfplumber"
        except Exception as e:
            print(f"pdfplumber failed: {e}", file=sys.stderr)
        return "", "extraction_failed"


# --- Shared number sub-pattern: ints or decimals with optional thousands separators ---
NUMBER: Final[str] = r"\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?"

# --- VAT/profit token for flexibility ---
VAT_PROFIT_TOKEN: Final[str] = r"(?:(?:\d+\s+)?\d+%?\s+)"

# Regex patterns using the shared NUMBER pattern
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


class DeliveryUdeaParser:
    """Parser for UDEA B.V. delivery invoices"""

    def __init__(self, verbose: bool = False, debug: bool = False):
        self.verbose = verbose
        self.debug = debug
        self.stats = {
            'total_lines': 0,
            'parsed_lines': 0,
            'skipped_lines': 0,
            'price_validations_passed': 0,
            'price_mismatches': 0
        }
        self.calculation_tolerance = 0.015

    def log(self, message: str, level: str = "INFO"):
        """Log message to stderr for debugging"""
        if self.verbose or level in ["ERROR", "WARNING"]:
            print(f"[{level}] {message}", file=sys.stderr)

    def _clean_number_string(self, num_str: str) -> str:
        """Convert European-style numbers (e.g., '1.234,56') to standard float format ('1234.56')."""
        if num_str is None:
            return ""
        return num_str.replace(".", "").replace(",", ".")

    def _preprocess_line(self, line: str) -> str:
        """Pre-process lines to fix common PDF extraction issues."""
        # Fix merged unit names like "1kilogram" -> "1 kilogram"
        line = re.sub(r'(\d)([a-z])', r'\1 \2', line)

        # Fix weight/qty corruption patterns
        weight_corruption_pattern = re.compile(
            r'^(\d+\s+\d+)\s+(\d{3},\d+)\s+(\d)(\s*)(kilogram|gram|litre|millilitre|liter|kg|g|l|ml)'
        )
        match = weight_corruption_pattern.match(line)
        if match:
            large_num = match.group(2)
            if large_num.startswith(('11', '12')):
                actual_weight = large_num[1:]
                fixed_line = f"{match.group(1)} 1 {actual_weight} {match.group(3)}{match.group(4)}{match.group(5)}"
                remainder = line[match.end():]
                line = fixed_line + remainder

        return line

    def _parse_fallback_sku_content(self, raw_sku_ct: str) -> Tuple[str, str]:
        """Split the combined SKU/Content field from the fallback pattern."""
        sku = raw_sku_ct
        content = ""

        match = re.match(rf"({NUMBER})(.*)", raw_sku_ct)
        if match:
            potential_sku = match.group(1)
            remaining_content = match.group(2).strip()

            cleaned_potential_sku = self._clean_number_string(potential_sku)
            try:
                float(cleaned_potential_sku)
                sku = cleaned_potential_sku
                content = remaining_content.strip(", ")
            except ValueError:
                sku = self._clean_number_string(raw_sku_ct)
                content = ""
        else:
            sku = self._clean_number_string(raw_sku_ct)
            content = ""

        return sku.strip(), content.strip()

    def _parse_line(self, line: str) -> Optional[Dict[str, str]]:
        """
        Attempt to parse a single line using NORMAL_REGEX, QUANTITY_SKU_REGEX, and FALLBACK_REGEX.
        Returns a dict of parsed fields, or None if no pattern matched.
        """
        # 1) NORMAL_REGEX
        m_normal = NORMAL_REGEX.match(line)
        if m_normal:
            code, ordered, qty, sku_val_regex, unit, desc, price, sale, total = m_normal.groups()
            desc_parts = desc.split(None, 1)
            if desc_parts:
                content_str = f"{unit} {desc_parts[0]}"
                description_str = desc_parts[1] if len(desc_parts) > 1 else ""
            else:
                content_str = unit
                description_str = ""

            return {
                "Code": code,
                "Ordered": ordered,
                "Qty": qty,
                "SKU": self._clean_number_string(sku_val_regex),
                "Content": content_str,
                "Description": description_str,
                "Price": self._clean_number_string(price),
                "Sale": self._clean_number_string(sale),
                "Total": self._clean_number_string(total),
            }

        # 2) QUANTITY_SKU_REGEX - Enhanced logic for weight-based products
        m_qty_sku = QUANTITY_SKU_REGEX.match(line)
        if m_qty_sku:
            g_code, g_ordered, g_qty, g_group4_val, g_group5_val, g_unit, g_desc, g_price, g_sale, g_total = m_qty_sku.groups()

            cleaned_group4 = self._clean_number_string(g_group4_val)
            cleaned_group5 = self._clean_number_string(g_group5_val)

            has_decimal_group4 = '.' in cleaned_group4
            has_decimal_group5 = '.' in cleaned_group5

            # For weight-based products
            if has_decimal_group4 and has_decimal_group5 and g_unit.lower() in ["kilogram", "gram"]:
                final_csv_sku_value = cleaned_group4
                quantity_for_content_display = cleaned_group5
            elif has_decimal_group4 and not has_decimal_group5:
                final_csv_sku_value = cleaned_group4
                quantity_for_content_display = cleaned_group5
            else:
                final_csv_sku_value = cleaned_group5
                quantity_for_content_display = cleaned_group4

            content_output_display = f"{quantity_for_content_display} {g_unit}"

            return {
                "Code": g_code,
                "Ordered": g_ordered,
                "Qty": g_qty,
                "SKU": final_csv_sku_value,
                "Content": content_output_display,
                "Description": g_desc,
                "Price": self._clean_number_string(g_price),
                "Sale": self._clean_number_string(g_sale),
                "Total": self._clean_number_string(g_total),
            }

        # 3) FALLBACK_REGEX
        m_fallback = FALLBACK_REGEX.match(line)
        if m_fallback:
            code, ordered, qty, raw_sku_ct, desc, price, sale, total = m_fallback.groups()
            sku_fb_multiplier, content_fb_display = self._parse_fallback_sku_content(raw_sku_ct)

            return {
                "Code": code,
                "Ordered": ordered,
                "Qty": qty,
                "SKU": sku_fb_multiplier,
                "Content": content_fb_display,
                "Description": desc,
                "Price": self._clean_number_string(price),
                "Sale": self._clean_number_string(sale),
                "Total": self._clean_number_string(total),
            }

        return None

    def _validate_price(self, qty: float, price: float, sku: float, total: float) -> Tuple[bool, float]:
        """
        Validate price calculation: Qty × Price × SKU = Total
        Returns (is_valid, calculated_total)
        """
        if sku == 0:
            return total == 0, 0.0

        calculated_total = qty * price * sku

        if abs(calculated_total - total) <= self.calculation_tolerance:
            return True, calculated_total

        return False, calculated_total

    def _calculate_unit_cost(self, qty: float, sku: float, total: float) -> float:
        """
        Calculate unit cost from total.
        For UDEA: total_units = qty * sku, unit_cost = total / total_units
        """
        total_units = qty * sku
        if total_units > 0:
            return total / total_units
        return 0.0

    def parse_invoice(self, pdf_path: str) -> Dict[str, Any]:
        """
        Parse a delivery invoice PDF and return structured data.

        Returns:
            Dictionary with structure matching Independent parser:
            {
                "success": bool,
                "supplier": "Udea",
                "items": [...],
                "totals": {...},
                "errors": [...],
                "warnings": [...]
            }
        """
        result = {
            "success": False,
            "supplier": "Udea",
            "items": [],
            "totals": {
                "line_count": 0,
                "total_value": 0.0
            },
            "errors": [],
            "warnings": [],
            "metadata": {
                "filename": os.path.basename(pdf_path),
                "parsing_method": "unknown"
            }
        }

        try:
            # Extract text from PDF
            text, extraction_method = extract_text(pdf_path)
            result["metadata"]["parsing_method"] = extraction_method

            if not text or not text.strip():
                result["errors"].append({
                    "code": "NO_TEXT_EXTRACTED",
                    "message": f"No text could be extracted from {pdf_path}"
                })
                return result

            items = []
            total_value = 0.0
            capture = False
            unmatched_product_lines = []

            lines = text.split("\n")

            for line_num, raw_line in enumerate(lines, start=1):
                line = raw_line.strip()

                # Check for section headers
                if any(header in raw_line for header in SECTION_HEADERS):
                    capture = True
                    self.log(f"L{line_num}: Capture mode ON (header found)", "DEBUG")
                    continue

                if not capture or not line:
                    continue

                self.stats['total_lines'] += 1

                # Pre-process line
                preprocessed_line = self._preprocess_line(line)

                # Parse line
                parsed = self._parse_line(preprocessed_line)

                if parsed:
                    try:
                        code = parsed["Code"]
                        ordered = int(parsed["Ordered"])
                        qty = int(parsed["Qty"])
                        sku = float(parsed["SKU"]) if parsed["SKU"] else 1.0
                        price = float(parsed["Price"]) if parsed["Price"] else 0.0
                        total = float(parsed["Total"]) if parsed["Total"] else 0.0
                        description = parsed.get("Description", "")
                        content = parsed.get("Content", "")

                        # Build product name
                        product_name = f"{content} {description}".strip()
                        if not product_name:
                            product_name = f"Product {code}"

                        # Validate price calculation
                        is_valid, calculated_total = self._validate_price(qty, price, sku, total)

                        if is_valid:
                            self.stats['price_validations_passed'] += 1
                        else:
                            self.stats['price_mismatches'] += 1
                            result["warnings"].append(
                                f"Price mismatch for {code}: {qty}×{price}×{sku}={calculated_total:.2f}, actual={total:.2f}"
                            )

                        # Calculate unit cost
                        unit_cost = self._calculate_unit_cost(qty, sku, total)

                        # Calculate total units
                        # For UDEA: case_size is the SKU multiplier, total_units = qty * sku
                        case_size = int(sku) if sku >= 1 else 1
                        total_delivered_units = int(qty * sku) if sku >= 1 else qty
                        total_ordered_units = int(ordered * sku) if sku >= 1 else ordered

                        item = {
                            "code": code,
                            "product": product_name,
                            "case_size": case_size,
                            "total_ordered_units": total_ordered_units,
                            "total_delivered_units": total_delivered_units,
                            "unit_cost": round(unit_cost, 4),
                            "line_total": total,
                            "price_valid": is_valid
                        }

                        items.append(item)
                        total_value += total
                        self.stats['parsed_lines'] += 1

                        self.log(f"Parsed: {code} - {product_name[:30]}... [{total_delivered_units} units @ {unit_cost:.2f}]", "DEBUG")

                    except (ValueError, KeyError) as e:
                        self.log(f"L{line_num}: Error processing parsed data: {e}", "WARNING")
                        self.stats['skipped_lines'] += 1

                elif LINE_STARTS_WITH_CODE_REGEX.match(line):
                    # Line starts with a code but couldn't be parsed
                    tokens = line.split()
                    has_price_pattern = any(re.match(r'\d+[.,]\d{2}', token) for token in tokens)
                    has_unit_keywords = any(
                        token.lower() in ['kilogram', 'gram', 'litre', 'millilitre', 'kg', 'g', 'l', 'ml', 'pc', 'stuks']
                        for token in tokens
                    )
                    has_percentage = any('%' in token for token in tokens)
                    is_likely_product = has_price_pattern and (has_unit_keywords or has_percentage)

                    if is_likely_product:
                        unmatched_product_lines.append({
                            "line_num": line_num,
                            "content": line[:100]
                        })
                        self.log(f"L{line_num}: Unmatched likely product line: {line[:60]}...", "WARNING")

                    self.stats['skipped_lines'] += 1

            # Add warnings for unmatched lines
            if unmatched_product_lines:
                result["warnings"].append(
                    f"Found {len(unmatched_product_lines)} likely product lines that couldn't be parsed"
                )

            # Set results
            result["items"] = items
            result["totals"]["line_count"] = len(items)
            result["totals"]["total_value"] = round(total_value, 2)
            result["success"] = len(items) > 0
            result["metadata"]["stats"] = self.stats

            if not items:
                result["errors"].append({
                    "code": "NO_PRODUCTS_FOUND",
                    "message": "No product lines could be parsed from the invoice"
                })

        except Exception as e:
            import traceback
            result["errors"].append({
                "code": "PARSE_ERROR",
                "message": str(e),
                "traceback": traceback.format_exc()
            })

        return result


def parse_delivery_pdf(pdf_path: str, verbose: bool = False, debug: bool = False) -> Dict[str, Any]:
    """
    Main entry point for parsing a UDEA delivery PDF.

    Args:
        pdf_path: Path to the PDF file
        verbose: Enable verbose logging
        debug: Enable debug logging

    Returns:
        Dictionary with parsed delivery data
    """
    parser = DeliveryUdeaParser(verbose=verbose, debug=debug)
    return parser.parse_invoice(pdf_path)


# For testing
if __name__ == "__main__":
    import json
    import argparse

    parser = argparse.ArgumentParser(description='Parse UDEA delivery PDF')
    parser.add_argument('pdf_path', help='Path to PDF file')
    parser.add_argument('-v', '--verbose', action='store_true', help='Enable verbose output')
    parser.add_argument('-d', '--debug', action='store_true', help='Enable debug output')

    args = parser.parse_args()

    result = parse_delivery_pdf(args.pdf_path, verbose=args.verbose, debug=args.debug)
    print(json.dumps(result, indent=2))
