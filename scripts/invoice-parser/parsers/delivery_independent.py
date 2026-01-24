"""
Delivery Product Parser for Independent Irish Health Foods

Extracts product line items from delivery PDFs for Laravel delivery import.
Returns JSON with structured product data including case/unit quantities.
"""

import re
import sys
import os
from typing import Dict, List, Optional, Tuple, Any

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


class DeliveryIndependentParser:
    """Parser for Independent Irish Health Foods delivery invoices"""

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

    def log(self, message: str, level: str = "INFO"):
        """Log message to stderr for debugging"""
        if self.verbose or level in ["ERROR", "WARNING"]:
            print(f"[{level}] {message}", file=sys.stderr)

    def parse_quantity(self, qty_str: str) -> Dict[str, float]:
        """
        Parse quantity string like '1/0' or '0/2' into cases and units.
        Format: cases/units where:
        - 1/0 = 1 case, 0 individual units
        - 0/2 = 0 cases, 2 individual units
        """
        result = {'cases': 0, 'units': 0, 'raw': qty_str}

        if '/' in qty_str:
            parts = qty_str.split('/')
            if len(parts) == 2:
                try:
                    result['cases'] = float(parts[0]) if parts[0] else 0
                    result['units'] = float(parts[1]) if parts[1] else 0
                except ValueError:
                    pass
        else:
            # Single number, assume it's units
            try:
                result['units'] = float(qty_str)
            except ValueError:
                pass

        return result

    def extract_case_size(self, product_desc: str) -> Optional[int]:
        """
        Extract case size from product description like '6x500ml' or '8x40g'.
        Returns the multiplier (e.g., 6 from '6x500ml').
        """
        # Look for patterns like 6x, 12x, etc.
        # Handle complex patterns like "10x(2x160g)" - we want the 10, not the 2
        matches = re.findall(r'(\d+)x(?:\d+|[\(\w])', product_desc, re.IGNORECASE)
        if matches:
            # Convert to integers and return the largest (main case size)
            case_sizes = [int(m) for m in matches]
            return max(case_sizes)
        return None

    def calculate_total_units(self, qty_dict: Dict, case_size: Optional[int]) -> float:
        """Calculate total units from cases and units"""
        if case_size:
            return (qty_dict['cases'] * case_size) + qty_dict['units']
        return qty_dict['units']

    def validate_price_calculation(self, delivered_qty: Dict, case_size: Optional[int],
                                   price: float, value: float, tolerance: float = 0.05) -> Tuple[bool, str, float, float]:
        """
        Validate if the calculated price matches the invoice line value.
        Returns: (is_valid, calculation_method, calculated_value, unit_cost)
        """
        total_delivered_units = self.calculate_total_units(delivered_qty, case_size)

        # If no delivery (total units = 0), value should be 0
        if total_delivered_units == 0:
            is_valid = abs(value) < 0.01
            if case_size and case_size > 0:
                unit_cost = price / case_size
            else:
                unit_cost = price
            return is_valid, "No delivery", 0.0, unit_cost

        # Method 1: Direct calculation for full cases only (price per case)
        if delivered_qty['cases'] > 0 and delivered_qty['units'] == 0:
            calculated = delivered_qty['cases'] * price
            if abs(value - calculated) <= (calculated * tolerance) if calculated > 0 else abs(value) < 0.01:
                if case_size and case_size > 0:
                    unit_cost = price / case_size
                else:
                    unit_cost = price
                return True, f"{delivered_qty['cases']} cases x {price:.2f}", calculated, unit_cost

        # Method 2: Unit price calculation (works for mixed cases/units or units only)
        if case_size and case_size > 0:
            # Try: price is per unit
            calculated = total_delivered_units * price
            if abs(value - calculated) <= (calculated * tolerance) if calculated > 0 else abs(value) < 0.01:
                return True, f"{total_delivered_units} units x {price:.2f}", calculated, price

            # Try: price is per case, calculate unit price
            unit_price = price / case_size
            calculated = total_delivered_units * unit_price
            if abs(value - calculated) <= (calculated * tolerance) if calculated > 0 else abs(value) < 0.01:
                return True, f"{total_delivered_units} units x {unit_price:.2f} (case price/{case_size})", calculated, unit_price

        # Method 3: Simple quantity (for items without case size)
        if case_size is None:
            calculated = total_delivered_units * price
            if abs(value - calculated) <= (calculated * tolerance) if calculated > 0 else abs(value) < 0.01:
                return True, f"{total_delivered_units} x {price:.2f}", calculated, price

        # Method 4: Derive unit cost from actual value (fallback)
        if total_delivered_units > 0:
            derived_unit_cost = value / total_delivered_units
            if case_size and case_size > 0:
                expected_unit_cost_from_case_price = price / case_size
                if abs(derived_unit_cost - expected_unit_cost_from_case_price) <= (expected_unit_cost_from_case_price * tolerance):
                    return True, f"{total_delivered_units} units x {derived_unit_cost:.2f} (derived)", value, derived_unit_cost

            if abs(derived_unit_cost - price) <= (price * tolerance):
                return True, f"{total_delivered_units} units x {price:.2f} (direct)", value, price

        # No valid calculation found - derive unit cost for reporting
        unit_cost = value / total_delivered_units if total_delivered_units > 0 else 0
        return False, "No matching calculation", 0.0, unit_cost

    def parse_invoice(self, pdf_path: str) -> Dict[str, Any]:
        """
        Parse a delivery invoice PDF and return structured data.

        Returns:
            Dictionary with structure:
            {
                "success": bool,
                "supplier": "Independent",
                "items": [...],
                "totals": {...},
                "errors": [...],
                "warnings": [...]
            }
        """
        result = {
            "success": False,
            "supplier": "Independent",
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

            # Keywords to skip
            skip_terms = [
                "Invoice", "Deliver To", "Order Ref", "Tax Code", "Regular Price", "Offer Price",
                "Page", "Notes:", "EMAIL:", "Total:", "Account No:", "TEL:", "FAX:",
                "VAT Reg No.", "All goods remain the property",
                "Tax Code Rate Taxable Tax DRS Totals", "Gross Total", "Customer Ref:",
                "Subtotal", "Carriage", "Nett", "Product Brand Description"
            ]

            # Regex patterns (from strict to relaxed)
            # Pattern 1: Full strict pattern (8 fields)
            strict_pattern = re.compile(
                r'^(\S+)\s+'  # Code
                r'(.*?)\s+'   # Product description
                r'(\d+/?[\d]*)\s+'  # Ordered qty
                r'(\d+/?[\d]*)\s+'  # Delivered qty
                r'(\d+\.\d{2})\s+'  # RSP
                r'(\d+\.\d{2})\s+'  # Price
                r'(\d+\.\d{2})\s+'  # Tax
                r'(\d+\.\d{2})$'    # Value
            )

            # Pattern 1.5: Missing RSP pattern (7 fields)
            missing_rsp_pattern = re.compile(
                r'^(\S+)\s+'  # Code
                r'(.*?)\s+'   # Product description
                r'(\d+/?[\d]*)\s+'  # Ordered qty
                r'(\d+/?[\d]*)\s+'  # Delivered qty
                r'(\d+\.\d{2})\s+'  # Price (no RSP)
                r'(\d+\.\d{2})\s+'  # Tax
                r'(\d+\.\d{2})$'    # Value
            )

            items = []
            total_value = 0.0

            lines = text.split("\n")

            for line in lines:
                line = line.strip()
                if not line:
                    continue

                self.stats['total_lines'] += 1

                # Skip known non-product lines
                if any(skip_term in line for skip_term in skip_terms):
                    self.stats['skipped_lines'] += 1
                    continue

                # Try strict pattern first
                match = strict_pattern.match(line)
                rsp_available = True

                if not match:
                    # Try missing RSP pattern
                    match = missing_rsp_pattern.match(line)
                    rsp_available = False

                if match:
                    if rsp_available:
                        code = match.group(1).strip()
                        product = match.group(2).strip()
                        ordered_raw = match.group(3).strip()
                        delivered_raw = match.group(4).strip()
                        rsp = float(match.group(5))
                        price = float(match.group(6))
                        tax = float(match.group(7))
                        value = float(match.group(8))
                    else:
                        code = match.group(1).strip()
                        product = match.group(2).strip()
                        ordered_raw = match.group(3).strip()
                        delivered_raw = match.group(4).strip()
                        rsp = 0.0  # RSP not available
                        price = float(match.group(5))
                        tax = float(match.group(6))
                        value = float(match.group(7))

                    # Parse quantities
                    ordered_qty = self.parse_quantity(ordered_raw)
                    delivered_qty = self.parse_quantity(delivered_raw)

                    # Extract case size
                    case_size = self.extract_case_size(product)

                    # Calculate total units
                    ordered_units = self.calculate_total_units(ordered_qty, case_size)
                    delivered_units = self.calculate_total_units(delivered_qty, case_size)

                    # Validate price calculation and get unit cost
                    is_valid, calc_method, calculated_value, unit_cost = self.validate_price_calculation(
                        delivered_qty, case_size, price, value
                    )

                    if is_valid:
                        self.stats['price_validations_passed'] += 1
                    else:
                        self.stats['price_mismatches'] += 1
                        result["warnings"].append(f"Price mismatch for {code}: calculated={calculated_value:.2f}, actual={value:.2f}")

                    item = {
                        "code": code,
                        "product": product,
                        "case_size": case_size or 1,
                        "ordered_cases": int(ordered_qty['cases']),
                        "ordered_units": int(ordered_qty['units']),
                        "delivered_cases": int(delivered_qty['cases']),
                        "delivered_units": int(delivered_qty['units']),
                        "total_ordered_units": int(ordered_units),
                        "total_delivered_units": int(delivered_units),
                        "unit_cost": round(unit_cost, 4),
                        "case_price": price,
                        "line_total": value,
                        "rsp": rsp,
                        "tax": tax,
                        "price_valid": is_valid
                    }

                    items.append(item)
                    total_value += value
                    self.stats['parsed_lines'] += 1

                    self.log(f"Parsed: {code} - {product[:30]}... [{delivered_units} units @ {unit_cost:.2f}]", "DEBUG")

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
    Main entry point for parsing a delivery PDF.

    Args:
        pdf_path: Path to the PDF file
        verbose: Enable verbose logging
        debug: Enable debug logging

    Returns:
        Dictionary with parsed delivery data
    """
    parser = DeliveryIndependentParser(verbose=verbose, debug=debug)
    return parser.parse_invoice(pdf_path)


# For testing
if __name__ == "__main__":
    import json
    import argparse

    parser = argparse.ArgumentParser(description='Parse Independent Health Foods delivery PDF')
    parser.add_argument('pdf_path', help='Path to PDF file')
    parser.add_argument('-v', '--verbose', action='store_true', help='Enable verbose output')
    parser.add_argument('-d', '--debug', action='store_true', help='Enable debug output')

    args = parser.parse_args()

    result = parse_delivery_pdf(args.pdf_path, verbose=args.verbose, debug=args.debug)
    print(json.dumps(result, indent=2))
