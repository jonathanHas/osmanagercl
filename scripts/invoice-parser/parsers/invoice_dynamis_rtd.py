"""
RTD Invoice Parser for GROUPE DYNAMIS SAS

Extracts structured invoice data from Dynamis PDF invoices for RTD (Revenue Tax Determination).
Handles two invoice types:
- F&V (RUNGIS): Fresh produce without EAN codes
- Grocery (MAG): Packaged goods with EAN barcodes

Returns JSON with header data, line items with EAN/article codes, and validation.
"""

import re
import sys
import os
import json
from typing import Dict, List, Optional, Any, Final
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


# European number pattern
NUMBER: Final[str] = r"\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?"


class InvoiceDynamisRtdParser:
    """Parser for GROUPE DYNAMIS SAS invoices (RTD line extraction)"""

    def __init__(self, verbose: bool = False, debug: bool = False):
        self.verbose = verbose
        self.debug = debug
        self.stats = {
            'total_lines_scanned': 0,
            'product_lines_parsed': 0,
            'ean_lines_parsed': 0,
            'transport_lines_parsed': 0,
            'skipped_lines': 0,
        }
        self.problem_lines = []

    def log(self, message: str, level: str = "INFO"):
        """Log message to stderr for debugging"""
        if self.verbose or level in ["ERROR", "WARNING"] or self.debug:
            print(f"[{level}] {message}", file=sys.stderr)

    def _clean_number_string(self, num_str: str) -> float:
        """Convert European-style numbers to float.

        Handles:
        - European decimals: '1,20' -> 1.20
        - European thousands: '1.234,56' -> 1234.56
        - Standard notation: '4.56' -> 4.56
        """
        if num_str is None or num_str.strip() == '':
            return 0.0

        num_str = num_str.strip()

        # Remove any currency symbols or spaces
        num_str = re.sub(r'[€EUR\s]', '', num_str)

        # If no comma, might be standard notation or integer
        if ',' not in num_str:
            # Check if it's European thousands (1.234)
            if '.' in num_str and len(num_str.split('.')[-1]) == 3:
                return float(num_str.replace('.', ''))
            return float(num_str) if num_str else 0.0

        # European format: dots are thousands, comma is decimal
        cleaned = num_str.replace(".", "").replace(",", ".")
        try:
            return float(cleaned)
        except ValueError:
            self.log(f"Could not parse number: {num_str}", "WARNING")
            return 0.0

    def _parse_date(self, date_str: str) -> Optional[str]:
        """Parse date from DD/MM/YYYY or DD/MM/YY format to YYYY-MM-DD"""
        if not date_str:
            return None

        date_str = date_str.strip()

        # Try DD/MM/YYYY format
        match = re.match(r'(\d{1,2})/(\d{1,2})/(\d{4})', date_str)
        if match:
            day, month, year = match.groups()
            return f"{year}-{month.zfill(2)}-{day.zfill(2)}"

        # Try DD/MM/YY format
        match = re.match(r'(\d{1,2})/(\d{1,2})/(\d{2})', date_str)
        if match:
            day, month, year = match.groups()
            # Assume 20xx for 2-digit years
            full_year = f"20{year}"
            return f"{full_year}-{month.zfill(2)}-{day.zfill(2)}"

        return date_str

    def _extract_origin_country(self, description: str) -> Optional[str]:
        """Extract 2-letter country code from description.

        Examples:
        - "AVOCADO HASS BIO - ES - cat.2" -> "ES"
        - "CONFITURE DE CERISE BIO 260g - FR - COTEAUX N" -> "FR"
        """
        # Look for pattern: " - XX - " where XX is 2-letter country
        match = re.search(r'\s-\s([A-Z]{2})\s-\s', description)
        if match:
            return match.group(1)

        # Also try end of string: " - XX"
        match = re.search(r'\s-\s([A-Z]{2})$', description)
        if match:
            return match.group(1)

        return None

    def _generate_article_code(self, description: str) -> str:
        """Generate article code from description for F&V items without EAN.

        Format: DYN-{PRODUCT}-{COUNTRY}
        Example: "AVOCADO HASS BIO - ES - cat.2" -> "DYN-AVOCADO-ES"
        """
        # Extract first word (product name)
        words = description.split()
        product = words[0] if words else "UNKNOWN"

        # Clean product name (remove special chars)
        product = re.sub(r'[^A-Z0-9]', '', product.upper())

        # Get country code
        country = self._extract_origin_country(description) or "XX"

        return f"DYN-{product}-{country}"

    def _extract_header(self, text: str) -> Dict[str, Any]:
        """Extract invoice header information.

        Dynamis format examples:
        - "FACTURE N° 666109 DU 17/11/2025"
        - "B.L. N° : 1008196"
        - "Livraison : 17/11/25"
        - "Ent : RUNGIS" or "Ent : MAG"
        - "NET A PAYER EUR 996.95"
        """
        header = {
            'invoice_number': None,
            'invoice_date': None,
            'delivery_note': None,
            'delivery_date': None,
            'invoice_type': None,  # RUNGIS (F&V) or MAG (Grocery)
            'total_excl_vat': 0.0,
            'vat_amount': 0.0,
            'total_incl_vat': 0.0,
            'is_zero_vat': True,
        }

        # Extract invoice number and date: "FACTURE N° 666109 DU 17/11/2025"
        inv_match = re.search(r'FACTURE\s+N°\s*(\d+)\s+DU\s+(\d{2}/\d{2}/\d{4})', text)
        if inv_match:
            header['invoice_number'] = inv_match.group(1)
            header['invoice_date'] = self._parse_date(inv_match.group(2))
            self.log(f"Found invoice: {header['invoice_number']} date: {header['invoice_date']}", "DEBUG")

        # Extract delivery note: "B.L. N° : 1008196"
        bl_match = re.search(r'B\.L\.\s*N°\s*:\s*(\d+)', text)
        if bl_match:
            header['delivery_note'] = bl_match.group(1)
            self.log(f"Found delivery note: {header['delivery_note']}", "DEBUG")

        # Extract delivery date: "Livraison : 17/11/25"
        livr_match = re.search(r'Livraison\s*:\s*(\d{2}/\d{2}/\d{2,4})', text)
        if livr_match:
            header['delivery_date'] = self._parse_date(livr_match.group(1))
            self.log(f"Found delivery date: {header['delivery_date']}", "DEBUG")

        # Extract invoice type: "Ent : RUNGIS" or "Ent : MAG"
        ent_match = re.search(r'Ent\s*:\s*(\w+)', text)
        if ent_match:
            header['invoice_type'] = ent_match.group(1).upper()
            self.log(f"Found invoice type: {header['invoice_type']}", "DEBUG")

        # Extract total: "NET A PAYER EUR 996.95" or "NET A PAYER EUR996.95"
        total_patterns = [
            r'NET\s+A\s+PAYER\s*(?:EUR)?\s*(\d+[.,]\d{2})',
            r'NET\s+A\s+PAYER.*?(\d+[.,]\d{2})\s*$',
        ]
        for pattern in total_patterns:
            total_match = re.search(pattern, text, re.MULTILINE)
            if total_match:
                header['total_excl_vat'] = self._clean_number_string(total_match.group(1))
                header['total_incl_vat'] = header['total_excl_vat']  # No VAT for EU exports
                self.log(f"Found total: {header['total_excl_vat']}", "DEBUG")
                break

        # Extract VAT amount: "TOTAL TVA" followed by amount (usually 0.00)
        vat_match = re.search(r'TOTAL\s+TVA\s*(\d+[.,]\d{2})', text)
        if vat_match:
            header['vat_amount'] = self._clean_number_string(vat_match.group(1))
            header['is_zero_vat'] = header['vat_amount'] == 0.0

        # Extract goods total: "HT MARCHANDISE" for validation
        ht_match = re.search(r'HT\s+MARCHANDISE\s*(\d+[.,]\d{2})', text)
        if ht_match:
            header['goods_total_expected'] = self._clean_number_string(ht_match.group(1))
            self.log(f"Found goods total: {header['goods_total_expected']}", "DEBUG")

        return header

    def _extract_product_lines_fv(self, text: str) -> List[Dict[str, Any]]:
        """Extract product lines from F&V (RUNGIS) invoice.

        F&V format (no EAN):
        AVOCADO HASS BIO - ES - cat.2 cal.22 171g/190g 5 22.23 C 111.15
        BEANS GREEN 4kg BIO - IT - cat.2 cal.SC SANS CALIB 1 4.00 4.00 5.72 K 22.88
        MISCELLANEOUS TRANSPORT 1 135.00 135.00
        """
        lines = []
        text_lines = text.split('\n')

        # Pattern for F&V lines - matches various formats
        # Description | Colis | (Pds Brut) | (Pds Net) | P.U. | U | H.T.
        fv_patterns = [
            # Pattern with weight columns: DESC COLIS PDS_BRUT PDS_NET PU U HT
            re.compile(
                r'^([A-Z][A-Z0-9 \'.,/-]+(?:BIO|DEMETER|CONV)?[A-Z0-9 \'.,/-]*?)\s+'
                r'(\d+)\s+'                           # Colis
                r'(\d+[.,]\d{2})\s+'                  # Pds Brut
                r'(\d+[.,]\d{2})\s+'                  # Pds Net
                r'(\d+[.,]\d{2})\s*'                  # P.U.
                r'([CKP])\s+'                         # Unit
                r'(\d+[.,]\d{2})\s*$'                 # H.T.
            ),
            # Pattern without weight: DESC COLIS PU U HT (for case items)
            re.compile(
                r'^([A-Z][A-Z0-9 \'.,/-]+(?:BIO|DEMETER|CONV)?[A-Z0-9 \'.,/-]*?)\s+'
                r'(\d+)\s+'                           # Colis
                r'(\d+[.,]\d{2})\s*'                  # P.U.
                r'([CKP])\s+'                         # Unit
                r'(\d+[.,]\d{2})\s*$'                 # H.T.
            ),
            # Transport line: MISCELLANEOUS TRANSPORT 1 135.00 135.00
            re.compile(
                r'^(MISCELLANEOUS\s+TRANSPORT|TRANSPORT|FREIGHT)\s+'
                r'(\d+)\s+'                           # Colis
                r'(\d+[.,]\d{2})\s+'                  # Price (no unit type)
                r'(\d+[.,]\d{2})\s*$'                 # Total
            ),
        ]

        for raw_line in text_lines:
            line = raw_line.strip()
            self.stats['total_lines_scanned'] += 1

            if not line or len(line) < 10:
                continue

            # Skip header/footer lines
            if any(skip in line for skip in [
                'Désignation Article', 'Colis', 'Pds Brut', 'H.T.',
                'HT MARCHANDISE', 'TOTAL', 'NET A PAYER', 'IBAN',
                'EXONERATION', 'FACTURE', 'B.L.', 'Livraison',
                'Règlement', 'Echéance', 'Client', 'Ent', 'Incoterm',
                'HANNON', 'TRANSPORT PRIX', 'Adresse', 'MOSSFIELD',
                'GROUPE DYNAMIS', 'SIRET', 'TVA', 'EORI', 'REX',
                'BASE TAXABLE', 'MONTANT', 'Emb', 'BIO :', 'CONV :',
                'N° CEE', 'IRLANDE', 'Main Street', 'R42TY29'
            ]):
                continue

            # Try transport pattern first
            transport_match = fv_patterns[2].match(line)
            if transport_match:
                groups = transport_match.groups()
                line_item = {
                    'article_code': 'DYN-TRANSPORT',
                    'description': groups[0].strip(),
                    'quantity': int(groups[1]),
                    'unit_price': self._clean_number_string(groups[2]),
                    'unit_type': None,
                    'line_total': self._clean_number_string(groups[3]),
                    'line_type': 'freight_or_service',
                    'origin_country': None,
                    'ean': None,
                    'parse_status': 'full',
                }
                lines.append(line_item)
                self.stats['transport_lines_parsed'] += 1
                self.log(f"Parsed transport: {line_item['line_total']}", "DEBUG")
                continue

            # Try pattern with weights
            match = fv_patterns[0].match(line)
            if match:
                groups = match.groups()
                description = groups[0].strip()
                line_item = {
                    'article_code': self._generate_article_code(description),
                    'description': description[:100],
                    'quantity': int(groups[1]),  # Colis
                    'pds_brut': self._clean_number_string(groups[2]),
                    'pds_net': self._clean_number_string(groups[3]),
                    'unit_price': self._clean_number_string(groups[4]),
                    'unit_type': groups[5],
                    'line_total': self._clean_number_string(groups[6]),
                    'line_type': 'product_for_resale',
                    'origin_country': self._extract_origin_country(description),
                    'ean': None,
                    'parse_status': 'full',
                }
                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed F&V (weight): {line_item['article_code']} - {line_item['line_total']}", "DEBUG")
                continue

            # Try pattern without weights (case items)
            match = fv_patterns[1].match(line)
            if match:
                groups = match.groups()
                description = groups[0].strip()
                line_item = {
                    'article_code': self._generate_article_code(description),
                    'description': description[:100],
                    'quantity': int(groups[1]),  # Colis
                    'unit_price': self._clean_number_string(groups[2]),
                    'unit_type': groups[3],
                    'line_total': self._clean_number_string(groups[4]),
                    'line_type': 'product_for_resale',
                    'origin_country': self._extract_origin_country(description),
                    'ean': None,
                    'parse_status': 'full',
                }
                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed F&V (case): {line_item['article_code']} - {line_item['line_total']}", "DEBUG")
                continue

            # Fallback: try to extract just description and total from end
            # For lines we can't fully parse but look like products
            if re.match(r'^[A-Z]', line) and re.search(r'(\d+[.,]\d{2})\s*$', line):
                total_match = re.search(r'(\d+[.,]\d{2})\s*$', line)
                if total_match:
                    # Get description (everything before numbers at end)
                    desc_part = re.sub(r'\s+\d+.*$', '', line).strip()
                    if len(desc_part) > 5:
                        line_item = {
                            'article_code': self._generate_article_code(desc_part),
                            'description': desc_part[:100],
                            'quantity': 1,
                            'unit_price': 0.0,
                            'unit_type': None,
                            'line_total': self._clean_number_string(total_match.group(1)),
                            'line_type': 'product_for_resale',
                            'origin_country': self._extract_origin_country(desc_part),
                            'ean': None,
                            'parse_status': 'partial',
                            'raw_line': line[:150],
                        }
                        lines.append(line_item)
                        self.stats['product_lines_parsed'] += 1
                        self.log(f"Parsed F&V (fallback): {line_item['article_code']} - {line_item['line_total']}", "DEBUG")

        return lines

    def _extract_product_lines_grocery(self, text: str) -> List[Dict[str, Any]]:
        """Extract product lines from Grocery (MAG) invoice.

        Grocery format (with EAN on separate line):
        CONFITURE DE CERISE BIO 260g - FR - COTEAUX N
        EAN : 3301595002651 1 6.00 6 2.98 P 17.88
        """
        lines = []
        text_lines = text.split('\n')

        pending_description = None

        for i, raw_line in enumerate(text_lines):
            line = raw_line.strip()
            self.stats['total_lines_scanned'] += 1

            if not line:
                continue

            # Skip header/footer lines
            if any(skip in line for skip in [
                'Désignation Article', 'Colis', 'Pds Brut', 'H.T.',
                'HT MARCHANDISE', 'TOTAL', 'NET A PAYER', 'IBAN',
                'EXONERATION', 'FACTURE', 'B.L.', 'Livraison',
                'Règlement', 'Echéance', 'Client', 'Ent', 'Incoterm',
                'HANNON', 'TRANSPORT PRIX', 'Adresse', 'MOSSFIELD',
                'GROUPE DYNAMIS', 'SIRET', 'TVA', 'EORI', 'REX',
                'BASE TAXABLE', 'MONTANT', 'Emb', 'BIO :', 'CONV :',
                'N° CEE', 'IRLANDE', 'Main Street', 'R42TY29'
            ]):
                pending_description = None
                continue

            # Check for EAN line
            # Format: EAN : 3301595002651 1 6.00 6 2.98 P 17.88
            ean_match = re.match(
                r'EAN\s*:\s*(\d{13})\s+'     # EAN code
                r'(\d+)\s+'                   # Colis
                r'(\d+[.,]\d{2})\s+'          # Pds Brut/qty
                r'(\d+)\s+'                   # Pieces
                r'(\d+[.,]\d{2})\s*'          # P.U.
                r'([CKP])\s+'                 # Unit
                r'(\d+[.,]\d{2})\s*$',        # H.T.
                line
            )

            if ean_match and pending_description:
                groups = ean_match.groups()
                ean = groups[0]
                line_item = {
                    'article_code': ean,  # Use EAN as article code for lookup
                    'ean': ean,
                    'description': pending_description[:100],
                    'quantity': int(groups[3]),  # Pieces column
                    'colis': int(groups[1]),
                    'unit_price': self._clean_number_string(groups[4]),
                    'unit_type': groups[5],
                    'line_total': self._clean_number_string(groups[6]),
                    'line_type': 'product_for_resale',
                    'origin_country': self._extract_origin_country(pending_description),
                    'parse_status': 'full',
                }
                lines.append(line_item)
                self.stats['ean_lines_parsed'] += 1
                self.log(f"Parsed grocery EAN: {ean} - {line_item['description'][:30]}... Total: {line_item['line_total']}", "DEBUG")
                pending_description = None
                continue

            # Check if this is a product description line (starts with uppercase, no EAN: prefix)
            # Note: Check for 'EAN :' or 'EAN:' specifically, not just 'EAN' (which matches 'BEANS')
            if re.match(r'^[A-Z][A-Z0-9 \'.,/-]+', line) and not re.match(r'^EAN\s*:', line):
                # This might be a product description, save it for next EAN line
                pending_description = line.strip()
                continue

            pending_description = None

        return lines

    def _validate_totals(self, header: Dict, lines: List) -> Dict[str, Any]:
        """Validate that line totals reconcile with invoice total."""
        products_total = sum(
            l['line_total'] for l in lines
            if l['line_type'] == 'product_for_resale'
        )
        transport_total = sum(
            l['line_total'] for l in lines
            if l['line_type'] == 'freight_or_service'
        )

        calculated_total = products_total + transport_total
        invoice_total = header.get('total_excl_vat', 0.0)

        difference = abs(calculated_total - invoice_total)
        tolerance = 1.00  # Allow €1 tolerance for rounding

        validation = {
            'is_valid': difference <= tolerance,
            'products_total': round(products_total, 2),
            'transport_total': round(transport_total, 2),
            'calculated_total': round(calculated_total, 2),
            'invoice_total': round(invoice_total, 2),
            'difference': round(difference, 2),
            'tolerance_ok': difference <= tolerance,
        }

        # Check against expected goods total if available
        if 'goods_total_expected' in header:
            expected = header['goods_total_expected']
            validation['goods_expected'] = round(expected, 2)
            goods_diff = abs(products_total - expected)
            validation['goods_difference'] = round(goods_diff, 2)
            if goods_diff > tolerance:
                validation['goods_mismatch'] = True
                self.log(f"Goods mismatch: parsed={products_total:.2f}, expected={expected:.2f}", "WARNING")
            else:
                validation['goods_mismatch'] = False

        if not validation['is_valid']:
            self.log(f"Total validation failed: calculated={calculated_total:.2f}, invoice={invoice_total:.2f}", "WARNING")

        return validation

    def parse_invoice(self, pdf_path: str) -> Dict[str, Any]:
        """
        Parse a Dynamis invoice PDF and return structured data for RTD.

        Returns:
            {
                "success": bool,
                "supplier": "Dynamis",
                "header": {...},
                "lines": [...],
                "costs": {...},
                "validation": {...},
                "errors": [],
                "warnings": []
            }
        """
        result = {
            "success": False,
            "supplier": "Dynamis",
            "header": {},
            "lines": [],
            "costs": {"items": [], "total": 0.0},
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

            # Determine invoice type and extract lines accordingly
            invoice_type = header.get('invoice_type', '').upper()

            if invoice_type == 'MAG':
                # Grocery invoice with EAN codes
                lines = self._extract_product_lines_grocery(text)
                self.log(f"Processing as Grocery (MAG) invoice", "INFO")
            else:
                # F&V invoice (RUNGIS or unknown) - no EAN codes
                lines = self._extract_product_lines_fv(text)
                self.log(f"Processing as F&V (RUNGIS) invoice", "INFO")

            result["lines"] = lines

            if not lines:
                result["warnings"].append("No product lines could be extracted")

            # Separate transport/costs from product lines
            transport_lines = [l for l in lines if l['line_type'] == 'freight_or_service']
            if transport_lines:
                result["costs"] = {
                    "items": transport_lines,
                    "total": sum(l['line_total'] for l in transport_lines)
                }

            # Validate totals
            validation = self._validate_totals(header, lines)
            result["validation"] = validation

            if not validation['is_valid']:
                result["warnings"].append(
                    f"Total mismatch: calculated {validation['calculated_total']:.2f} vs invoice {validation['invoice_total']:.2f}"
                )

            # Set success
            result["success"] = bool(header['invoice_number'] and lines)

            # Add stats
            result["metadata"]["stats"] = self.stats

            if self.problem_lines:
                result["problem_lines"] = self.problem_lines

            self.log(f"Parsing complete: {len(lines)} lines ({self.stats['ean_lines_parsed']} with EAN)", "INFO")

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
    Main entry point for parsing a Dynamis invoice PDF.

    Args:
        pdf_path: Path to the PDF file
        verbose: Enable verbose logging
        debug: Enable debug logging

    Returns:
        Dictionary with parsed invoice data
    """
    parser = InvoiceDynamisRtdParser(verbose=verbose, debug=debug)
    return parser.parse_invoice(pdf_path)


# CLI interface
if __name__ == "__main__":
    import argparse

    arg_parser = argparse.ArgumentParser(description='Parse Dynamis invoice PDF for RTD')
    arg_parser.add_argument('pdf_path', help='Path to PDF file')
    arg_parser.add_argument('-v', '--verbose', action='store_true', help='Enable verbose output')
    arg_parser.add_argument('-d', '--debug', action='store_true', help='Enable debug output')
    arg_parser.add_argument('-o', '--output', help='Output file (default: stdout)')

    args = arg_parser.parse_args()

    result = parse_invoice_pdf(args.pdf_path, verbose=args.verbose, debug=args.debug)

    output = json.dumps(result, indent=2, ensure_ascii=False)

    if args.output:
        with open(args.output, 'w', encoding='utf-8') as f:
            f.write(output)
        print(f"Output written to {args.output}", file=sys.stderr)
    else:
        print(output)
