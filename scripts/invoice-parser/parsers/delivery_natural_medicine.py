"""
Delivery Product Parser for The Natural Medicine Company

Extracts product line items from delivery/invoice PDFs for Laravel delivery import.
Returns JSON with structured product data.

Invoice format:
  Stock Code | Description | Unit | RRP | Qty | Tr. Price | Disc % | Total | VAT
  26031      | HH Bach ... | Each | 7.95| 1   | 4.20      | 0.00 % | 4.20  | 23.0 %
"""

import re
import sys
import os
from typing import Dict, List, Optional, Any

# Add parent directory to path for imports
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

try:
    from utils import extract_text
except ImportError:
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


class DeliveryNaturalMedicineParser:
    """Parser for The Natural Medicine Company delivery invoices"""

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

    def _is_header_or_skip_line(self, line: str) -> bool:
        """Check if a line is a header, footer, or other non-product line."""
        skip_terms = [
            "The Natural Medicine Company",
            "Burgage Industrial Estate",
            "VAT No.",
            "Phone:",
            "Email:",
            "Web:",
            "Invoice Copy Invoice",
            "Invoice To:",
            "Deliver To:",
            "Invoice No:",
            "Date:",
            "Cust A/c.:",
            "Order Ref:",
            "Pick List:",
            "No of boxes:",
            "Assistant:",
            "All amounts are in",
            "Stock Code",
            "Description",
            "Organic Store",
            "Main Street",
            "Birr",
            "Co. Offaly",
            "R42 TY29",
            "Page ",
            "SUB-TOTAL",
            "GRAND TOTAL",
            "Goods remain the property",
            "Rate Goods Vat Due",
        ]
        for term in skip_terms:
            if term in line:
                return True

        # Skip VAT summary lines like "0.0% 0.00 0.00" or "23.0% 581.41 133.76"
        if re.match(r'^\d+\.\d+%\s+[\d.]+\s+[\d.]+', line):
            return True

        # Skip standalone "VAT" line with amount
        if re.match(r'^VAT\s+[\d.]+$', line):
            return True

        return False

    def _extract_invoice_totals(self, text: str) -> Dict[str, Optional[float]]:
        """Extract stated totals from the invoice footer."""
        totals: Dict[str, Optional[float]] = {
            'products_stated': None,
            'grand_stated': None,
        }

        # SUB-TOTAL 608.93
        sub_match = re.search(r'SUB-TOTAL\s+([\d.,]+)', text)
        if sub_match:
            try:
                totals['products_stated'] = float(sub_match.group(1).replace(',', ''))
                self.log(f"Extracted SUB-TOTAL: {totals['products_stated']}", "DEBUG")
            except ValueError:
                pass

        # GRAND TOTAL 745.15
        grand_match = re.search(r'GRAND\s+TOTAL\s+([\d.,]+)', text)
        if grand_match:
            try:
                totals['grand_stated'] = float(grand_match.group(1).replace(',', ''))
                self.log(f"Extracted GRAND TOTAL: {totals['grand_stated']}", "DEBUG")
            except ValueError:
                pass

        return totals

    def parse_invoice(self, pdf_path: str) -> Dict[str, Any]:
        """
        Parse a Natural Medicine Company invoice PDF and return structured data.

        Returns:
            Dictionary with structure matching the delivery parser contract:
            {
                "success": bool,
                "supplier": "Natural Medicine",
                "items": [...],
                "totals": {...},
                "errors": [...],
                "warnings": [...]
            }
        """
        result = {
            "success": False,
            "supplier": "Natural Medicine",
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

            # Product line pattern:
            # 26031 HH Bach Vervain (10ml) Each 7.95 1 4.20 0.00 % 4.20 23.0 %
            # Groups: stock_code, description, rrp, qty, trade_price, disc_pct, total, vat_rate
            product_pattern = re.compile(
                r'^(\d{4,6})\s+'           # Stock code (4-6 digits)
                r'(.+?)\s+'               # Description (non-greedy)
                r'Each\s+'                 # Unit (always "Each")
                r'([\d.]+)\s+'             # RRP
                r'(\d+)\s+'               # Quantity
                r'([\d.]+)\s+'             # Trade price
                r'([\d.]+)\s*%\s+'         # Discount %
                r'([\d.]+)\s+'             # Total
                r'([\d.]+)\s*%$'           # VAT rate %
            )

            items = []
            total_value = 0.0
            lines = text.split("\n")

            for i, line in enumerate(lines):
                line = line.strip()
                if not line:
                    continue

                self.stats['total_lines'] += 1

                # Skip header/footer lines
                if self._is_header_or_skip_line(line):
                    self.stats['skipped_lines'] += 1
                    continue

                # Try to match product line
                match = product_pattern.match(line)
                if match:
                    stock_code = match.group(1)
                    description = match.group(2).strip()
                    rrp = float(match.group(3))
                    qty = int(match.group(4))
                    trade_price = float(match.group(5))
                    disc_pct = float(match.group(6))
                    line_total = float(match.group(7))
                    vat_rate = float(match.group(8))

                    # Validate: qty * trade_price should equal total (within tolerance)
                    expected_total = round(qty * trade_price, 2)
                    is_valid = abs(expected_total - line_total) <= 0.02

                    if is_valid:
                        self.stats['price_validations_passed'] += 1
                    else:
                        self.stats['price_mismatches'] += 1
                        result["warnings"].append(
                            f"Price mismatch for {stock_code}: {qty} x {trade_price:.2f} = {expected_total:.2f}, "
                            f"invoice says {line_total:.2f}"
                        )

                    # Calculate tax amount from vat_rate and line_total
                    tax_amount = round(line_total * vat_rate / 100, 2)

                    item = {
                        "code": stock_code,
                        "product": description,
                        "case_size": 1,
                        "ordered_cases": 0,
                        "ordered_units": qty,
                        "delivered_cases": 0,
                        "delivered_units": qty,
                        "total_ordered_units": qty,
                        "total_delivered_units": qty,
                        "unit_cost": trade_price,
                        "case_price": trade_price,
                        "line_total": line_total,
                        "rsp": rrp,
                        "tax": tax_amount,
                        "vat_rate": vat_rate,
                        "price_valid": is_valid,
                    }

                    items.append(item)
                    total_value += line_total
                    self.stats['parsed_lines'] += 1

                    self.log(
                        f"Parsed: {stock_code} - {description[:40]}... "
                        f"[{qty} x {trade_price:.2f} = {line_total:.2f}]",
                        "DEBUG"
                    )
                else:
                    # Check if this is a continuation line for a multi-line description
                    # (e.g., "Skin (50ml)" wrapping from the previous product line)
                    if items and not re.match(r'^\d', line) and len(line) < 80:
                        # Append to previous item's description
                        items[-1]["product"] += " " + line
                        self.log(f"Appended continuation: '{line}' to {items[-1]['code']}", "DEBUG")
                        self.stats['skipped_lines'] += 1
                    else:
                        self.stats['skipped_lines'] += 1
                        self.log(f"Unmatched line {i}: {line[:80]}", "DEBUG")

            # Set results
            result["items"] = items
            result["totals"]["line_count"] = len(items)
            result["totals"]["total_value"] = round(total_value, 2)
            result["totals"]["products_total"] = round(total_value, 2)

            # Extract stated totals and compare
            stated = self._extract_invoice_totals(text)
            tolerance = 0.50

            result["totals"]["products_stated"] = stated['products_stated']
            result["totals"]["grand_stated"] = stated['grand_stated']
            result["totals"]["products_calculated"] = round(total_value, 2)
            result["totals"]["grand_calculated"] = round(total_value, 2)

            products_match = True
            discrepancy = None

            if stated['products_stated'] is not None:
                discrepancy = abs(total_value - stated['products_stated'])
                products_match = discrepancy <= tolerance
                if not products_match:
                    result["warnings"].append(
                        f"Products total mismatch: PDF states {stated['products_stated']:.2f} "
                        f"but parsed items sum to {total_value:.2f} (difference: {discrepancy:.2f})"
                    )
                    self.log(
                        f"Products total mismatch: stated={stated['products_stated']}, calculated={total_value}",
                        "WARNING"
                    )

            result["totals"]["totals_match"] = products_match
            result["totals"]["discrepancy"] = round(discrepancy, 2) if discrepancy is not None else None

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
    Main entry point for parsing a Natural Medicine Company delivery PDF.

    Args:
        pdf_path: Path to the PDF file
        verbose: Enable verbose logging
        debug: Enable debug logging

    Returns:
        Dictionary with parsed delivery data
    """
    parser = DeliveryNaturalMedicineParser(verbose=verbose, debug=debug)
    return parser.parse_invoice(pdf_path)


# For testing
if __name__ == "__main__":
    import json
    import argparse

    parser = argparse.ArgumentParser(description='Parse Natural Medicine Company delivery PDF')
    parser.add_argument('pdf_path', help='Path to PDF file')
    parser.add_argument('-v', '--verbose', action='store_true', help='Enable verbose output')
    parser.add_argument('-d', '--debug', action='store_true', help='Enable debug output')

    args = parser.parse_args()

    result = parse_delivery_pdf(args.pdf_path, verbose=args.verbose, debug=args.debug)
    print(json.dumps(result, indent=2))
