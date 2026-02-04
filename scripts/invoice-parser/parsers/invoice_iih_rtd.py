"""
Invoice Parser for Independent Irish Health Foods (IIH)

Extracts structured invoice data from IIH invoice PDFs for RTD purposes.
Returns JSON with header data, VAT summary, and DRS totals.

Unlike Udea/Dynamis, IIH invoices already have VAT categorization in the invoice,
so we extract the VAT summary directly and subtract DRS from 0% goods.

Key data extracted:
- Invoice number, date, totals from header
- VAT summary table (0%, 13.5%, 23%)
- DRS Totals section (deposit return scheme amounts)
"""

import re
import sys
import os
import json
from typing import Dict, Any, Optional
from datetime import datetime

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


class InvoiceIIHParser:
    """Parser for Independent Irish Health Foods invoices"""

    def __init__(self, verbose: bool = False, debug: bool = False):
        self.verbose = verbose
        self.debug = debug

    def log(self, message: str, level: str = "INFO"):
        """Log message to stderr for debugging"""
        if self.verbose or level in ["ERROR", "WARNING"] or self.debug:
            print(f"[{level}] {message}", file=sys.stderr)

    def _clean_number(self, num_str: str) -> float:
        """Convert number string to float.

        Handles:
        - Standard notation: '894.51' -> 894.51
        - Comma thousands: '1,525.16' -> 1525.16
        - European format: '1.234,56' -> 1234.56
        """
        if num_str is None or str(num_str).strip() == '':
            return 0.0

        num_str = str(num_str).strip()

        # Remove any currency symbols and spaces
        num_str = re.sub(r'[€$£\s]', '', num_str)

        if not num_str:
            return 0.0

        # Check for European format (comma as decimal separator)
        # European: 1.234,56 (dot=thousands, comma=decimal)
        # US/UK: 1,234.56 (comma=thousands, dot=decimal)

        if ',' in num_str and '.' in num_str:
            # Both present - determine format by position
            last_comma = num_str.rfind(',')
            last_dot = num_str.rfind('.')

            if last_comma > last_dot:
                # European format: 1.234,56
                num_str = num_str.replace('.', '').replace(',', '.')
            else:
                # US format: 1,234.56
                num_str = num_str.replace(',', '')
        elif ',' in num_str:
            # Only comma - check if it's decimal (has 2 digits after)
            parts = num_str.split(',')
            if len(parts) == 2 and len(parts[1]) == 2:
                # Likely decimal: 894,51
                num_str = num_str.replace(',', '.')
            else:
                # Thousands separator: 1,234
                num_str = num_str.replace(',', '')

        try:
            return float(num_str)
        except ValueError:
            self.log(f"Could not parse number: {num_str}", "WARNING")
            return 0.0

    def _parse_date(self, date_str: str) -> Optional[str]:
        """Parse date from DD/MM/YYYY format to YYYY-MM-DD"""
        if not date_str:
            return None

        # Try DD/MM/YYYY format (IIH format)
        match = re.match(r'(\d{1,2})/(\d{1,2})/(\d{4})', date_str.strip())
        if match:
            day, month, year = match.groups()
            return f"{year}-{month.zfill(2)}-{day.zfill(2)}"

        # Try DD-MM-YYYY format
        match = re.match(r'(\d{1,2})-(\d{1,2})-(\d{4})', date_str.strip())
        if match:
            day, month, year = match.groups()
            return f"{year}-{month.zfill(2)}-{day.zfill(2)}"

        return date_str  # Return as-is if can't parse

    def _extract_header(self, text: str) -> Dict[str, Any]:
        """Extract invoice header information.

        IIH format:
        - "Invoice No: IN457971"
        - "Invoice Date: 22/01/2026"
        - "Gross Total: 2,696.70"
        - "Tax: 243.14"
        - "Total: 2,939.84"
        """
        header = {
            'invoice_number': None,
            'invoice_date': None,
            'gross_total': 0.0,
            'tax_amount': 0.0,
            'net_total': 0.0,
        }

        # Extract invoice number - "Invoice No: IN457971"
        inv_match = re.search(r'Invoice\s*No[.:]?\s*(\w+)', text, re.IGNORECASE)
        if inv_match:
            header['invoice_number'] = inv_match.group(1)
            self.log(f"Found invoice number: {header['invoice_number']}", "DEBUG")

        # Extract invoice date - "Invoice Date: 22/01/2026"
        date_match = re.search(r'Invoice\s*Date[.:]?\s*(\d{1,2}/\d{1,2}/\d{4})', text, re.IGNORECASE)
        if date_match:
            header['invoice_date'] = self._parse_date(date_match.group(1))
            self.log(f"Found invoice date: {header['invoice_date']}", "DEBUG")

        # Extract totals from footer
        # Gross Total: 2,696.70
        gross_match = re.search(r'Gross\s*Total[.:]?\s*([\d,\.]+)', text, re.IGNORECASE)
        if gross_match:
            header['gross_total'] = self._clean_number(gross_match.group(1))
            self.log(f"Found gross total: {header['gross_total']}", "DEBUG")

        # Tax: 243.14
        tax_match = re.search(r'(?:^|\s)Tax[.:]?\s*([\d,\.]+)', text, re.MULTILINE | re.IGNORECASE)
        if tax_match:
            header['tax_amount'] = self._clean_number(tax_match.group(1))
            self.log(f"Found tax amount: {header['tax_amount']}", "DEBUG")

        # Total: 2,939.84
        total_match = re.search(r'(?:^|\s)Total[.:]?\s*([\d,\.]+)\s*$', text, re.MULTILINE | re.IGNORECASE)
        if total_match:
            header['net_total'] = self._clean_number(total_match.group(1))
            self.log(f"Found net total: {header['net_total']}", "DEBUG")

        return header

    def _extract_vat_summary(self, text: str) -> Dict[str, float]:
        """Extract VAT summary from tax code table.

        IIH format (merged with DRS columns):
        Tax Code Rate Taxable Tax DRS Totals Gross Total: 2,696.70
        1 23.00 894.51 205.75 DRS 15c 29 4.35 Tax: 243.14
        0 0.00 1,525.16 0.00 DRS 25c 0 0.00
        Total: 2,939.84
        2 13.50 277.03 37.39
        """
        vat_summary = {
            '0': 0.0,
            '9': 0.0,
            '13.5': 0.0,
            '23': 0.0,
        }

        # First, find the VAT summary section - starts with "Tax Code Rate Taxable Tax"
        # This section is at the bottom of the invoice
        vat_section_match = re.search(
            r'Tax\s+Code\s+Rate\s+Taxable\s+Tax(.*?)(?:VAT\s+Reg|All\s+goods\s+remain|$)',
            text,
            re.DOTALL | re.IGNORECASE
        )

        if not vat_section_match:
            self.log("No VAT summary section found", "WARNING")
            return vat_summary

        vat_section = vat_section_match.group(1)
        self.log(f"VAT section found: {vat_section[:200]}...", "DEBUG")

        # Pattern for VAT summary rows within the section
        # Matches lines like: "1 23.00 894.51 205.75" or "0 0.00 1,525.16 0.00"
        # The rate must be 0.00, 9.00, 13.50, or 23.00
        vat_pattern = re.compile(
            r'(\d)\s+'                            # Tax code (single digit 0, 1, 2)
            r'(0\.00|9\.00|13\.50|23\.00)\s+'     # Rate (exact matches only)
            r'([\d,]+\.?\d*)\s+'                  # Taxable amount
            r'([\d,]+\.?\d*)',                    # Tax amount
            re.MULTILINE
        )

        for match in vat_pattern.finditer(vat_section):
            tax_code, rate, taxable, tax = match.groups()
            rate_float = float(rate)
            taxable_amount = self._clean_number(taxable)

            self.log(f"VAT row: code={tax_code}, rate={rate_float}, taxable={taxable_amount}", "DEBUG")

            # Map rate to our bucket keys
            if rate_float == 0.0:
                vat_summary['0'] += taxable_amount
            elif abs(rate_float - 9.0) < 0.1:
                vat_summary['9'] += taxable_amount
            elif abs(rate_float - 13.5) < 0.1 or abs(rate_float - 13.50) < 0.1:
                vat_summary['13.5'] += taxable_amount
            elif abs(rate_float - 23.0) < 0.1:
                vat_summary['23'] += taxable_amount

        self.log(f"VAT summary: {vat_summary}", "DEBUG")
        return vat_summary

    def _extract_drs_totals(self, text: str) -> Dict[str, Any]:
        """Extract DRS (Deposit Return Scheme) totals.

        IIH format (DRS is embedded in the VAT summary lines):
        Tax Code Rate Taxable Tax DRS Totals Gross Total: 2,696.70
        1 23.00 894.51 205.75 DRS 15c 29 4.35 Tax: 243.14
        0 0.00 1,525.16 0.00 DRS 25c 0 0.00
        """
        drs = {
            'items': [],
            'total': 0.0,
        }

        # Pattern for DRS rows anywhere in the text: "DRS 15c 29 4.35"
        # The format is: DRS <type> <quantity> <amount>
        drs_pattern = re.compile(
            r'DRS\s+(\d+c)\s+'        # DRS type (15c, 25c)
            r'(\d+)\s+'               # Quantity
            r'([\d,\.]+)',            # Amount
            re.IGNORECASE
        )

        for match in drs_pattern.finditer(text):
            drs_type, qty, amount = match.groups()
            amount_float = self._clean_number(amount)

            # Only add if we haven't already (avoid duplicates)
            existing_types = [item['type'] for item in drs['items']]
            if drs_type not in existing_types:
                drs['items'].append({
                    'type': drs_type,
                    'quantity': int(qty),
                    'amount': amount_float,
                })
                drs['total'] += amount_float
                self.log(f"DRS item: {drs_type} x {qty} = {amount_float}", "DEBUG")

        self.log(f"Total DRS: {drs['total']}", "DEBUG")
        return drs

    def parse_invoice(self, pdf_path: str) -> Dict[str, Any]:
        """
        Parse an IIH invoice PDF and return structured data for RTD.

        Returns:
            {
                "success": bool,
                "supplier": "Independent",
                "header": {
                    "invoice_number": "IN457971",
                    "invoice_date": "2026-01-22",
                    "gross_total": 2696.70,
                    "tax_amount": 243.14,
                    "net_total": 2939.84
                },
                "vat_summary": {
                    "0": 1525.16,
                    "9": 0.0,
                    "13.5": 277.03,
                    "23": 894.51
                },
                "drs": {
                    "total": 4.35,
                    "items": [...]
                },
                "lines": [...],  # Placeholder for compatibility
                "validation": {...},
                "errors": [],
                "warnings": []
            }
        """
        result = {
            "success": False,
            "supplier": "Independent",
            "header": {},
            "vat_summary": {},
            "drs": {"total": 0.0, "items": []},
            "lines": [],  # Empty for IIH - we use VAT summary instead
            "barrels": {"total": 0.0, "items": []},  # Not used for IIH
            "costs": {"total": 0.0, "items": []},  # Not used for IIH
            "validation": {},
            "errors": [],
            "warnings": [],
            "metadata": {
                "filename": os.path.basename(pdf_path),
                "parsing_method": "unknown",
            }
        }

        try:
            # Extract text from PDF
            text, extraction_method = extract_text(pdf_path)
            result["metadata"]["parsing_method"] = extraction_method
            result["metadata"]["text_length"] = len(text) if text else 0

            if not text or not text.strip():
                result["errors"].append({
                    "code": "NO_TEXT_EXTRACTED",
                    "message": f"No text could be extracted from {pdf_path}"
                })
                return result

            if self.debug:
                result["metadata"]["raw_text_preview"] = text[:3000]

            # Extract header information
            header = self._extract_header(text)
            result["header"] = header

            if not header['invoice_number']:
                result["warnings"].append("Could not extract invoice number")

            if not header['invoice_date']:
                result["warnings"].append("Could not extract invoice date")

            # Extract VAT summary
            vat_summary = self._extract_vat_summary(text)
            result["vat_summary"] = vat_summary

            # Extract DRS totals
            drs = self._extract_drs_totals(text)
            result["drs"] = drs

            # Create synthetic lines for RTD compatibility
            # Each VAT rate becomes a "line" for the RTD system
            for rate, amount in vat_summary.items():
                if amount > 0:
                    result["lines"].append({
                        "article_code": f"IIH_VAT_{rate}",
                        "description": f"Goods at {rate}% VAT",
                        "line_total": amount,
                        "line_type": "product_for_resale",
                        "vat_rate": float(rate),
                        "parse_status": "full",
                    })

            # Validation
            vat_total = sum(vat_summary.values())
            header_gross = header.get('gross_total', 0)
            difference = abs(vat_total - header_gross)

            result["validation"] = {
                "vat_summary_total": round(vat_total, 2),
                "header_gross_total": round(header_gross, 2),
                "difference": round(difference, 2),
                "drs_total": round(drs['total'], 2),
                "vat_0_after_drs": round(vat_summary['0'] - drs['total'], 2),
                "reconciled": difference < 1.00,  # Allow €1 tolerance
            }

            # Check for issues
            if difference > 1.00:
                result["warnings"].append(
                    f"VAT summary total ({vat_total:.2f}) doesn't match gross total ({header_gross:.2f})"
                )

            # Set success based on critical extractions
            result["success"] = bool(
                header['invoice_number'] and
                (vat_summary['0'] > 0 or vat_summary['13.5'] > 0 or vat_summary['23'] > 0)
            )

            self.log(f"Parsing complete: VAT 0%={vat_summary['0']:.2f}, 13.5%={vat_summary['13.5']:.2f}, 23%={vat_summary['23']:.2f}, DRS={drs['total']:.2f}", "INFO")

        except Exception as e:
            import traceback
            result["errors"].append({
                "code": "PARSE_ERROR",
                "message": str(e),
                "traceback": traceback.format_exc()
            })

        return result


def parse_invoice_pdf(pdf_path: str, verbose: bool = False, debug: bool = False) -> Dict[str, Any]:
    """
    Main entry point for parsing an IIH invoice PDF.

    Args:
        pdf_path: Path to the PDF file
        verbose: Enable verbose logging
        debug: Enable debug logging

    Returns:
        Dictionary with parsed invoice data
    """
    parser = InvoiceIIHParser(verbose=verbose, debug=debug)
    return parser.parse_invoice(pdf_path)


# CLI interface
if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description='Parse IIH invoice PDF for RTD')
    parser.add_argument('pdf_path', help='Path to PDF file')
    parser.add_argument('-v', '--verbose', action='store_true', help='Enable verbose output')
    parser.add_argument('-d', '--debug', action='store_true', help='Enable debug output')
    parser.add_argument('-o', '--output', help='Output file (default: stdout)')

    args = parser.parse_args()

    result = parse_invoice_pdf(args.pdf_path, verbose=args.verbose, debug=args.debug)

    output = json.dumps(result, indent=2, ensure_ascii=False)

    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(output)
        print(f"Output written to {args.output}", file=sys.stderr)
    else:
        print(output)
