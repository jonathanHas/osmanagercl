"""
Invoice Parser for UDEA B.V.

Extracts structured invoice data from UDEA invoice PDFs.
Returns JSON with header data, line items with classification, and validation.

Line classifications based on Gb.rek (account code):
- 30302 = AGF (Fruit & Vegetables) -> product_for_resale
- 30322 = DKW (Dairy products) -> product_for_resale
- 30342 = Drogmetica -> product_for_resale
- 30362 = Non-food -> product_for_resale
- Barrels section -> deposit_or_returnable_packaging
- Costs section -> freight_or_service
"""

import re
import sys
import os
import json
from typing import Dict, List, Optional, Tuple, Any, Final
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


# --- Shared number sub-pattern: European format numbers ---
# Matches numbers like: 1234, 12,34, 1234,56, 1.234,56, 12.345,67
NUMBER: Final[str] = r"\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?"

# More flexible number pattern for totals (handles 4+ digit numbers without separators)
TOTAL_NUMBER: Final[str] = r"\d+(?:[.,]\d{2,3})*"

# Line classification by Gb.rek (account code)
# Based on Udea distribution groups
GBREK_CLASSIFICATIONS: Final[Dict[str, str]] = {
    # Product categories (all resale)
    '30252': 'product_for_resale',  # Omzet zuivel/ei eu (Dairy/Eggs EU)
    '30262': 'product_for_resale',  # Omzet kaas binnen eu (Cheese EU)
    '30282': 'product_for_resale',  # Omzet vls/vis/mltd/vv eu (Meat/Fish EU)
    '30292': 'product_for_resale',  # Omzet koelvers binnen eu (Refrigerated EU)
    '30302': 'product_for_resale',  # Omzet agf eu (AGF - Fruit & Vegetables)
    '30322': 'product_for_resale',  # Omzet dkw eu (DKW - Dry goods)
    '30342': 'product_for_resale',  # Omzet drogmetica eu (Drogmetica/Cosmetics)
    '30362': 'product_for_resale',  # Omzet non food eu (Non-food)
    '30372': 'product_for_resale',  # Overige omzet eu (Other sales)
    '30382': 'product_for_resale',  # General goods
    '30700': 'product_for_resale',  # Other products
    # Service categories
    '30862': 'freight_or_service',  # Transport doorbel. eu (Transport)
    # Deposit categories
    '34010': 'deposit_or_returnable_packaging',  # Fust inkomend debiteuren (Barrel returns)
    '34120': 'deposit_or_returnable_packaging',  # Fust uitgaand debiteuren (Barrels/crates)
}


class InvoiceUdeaParser:
    """Parser for UDEA B.V. invoices"""

    def __init__(self, verbose: bool = False, debug: bool = False):
        self.verbose = verbose
        self.debug = debug
        self.stats = {
            'total_lines_scanned': 0,
            'product_lines_parsed': 0,
            'partial_lines_parsed': 0,
            'barrel_lines_parsed': 0,
            'cost_lines_parsed': 0,
            'skipped_lines': 0,
        }
        self.problem_lines = []  # Track lines that couldn't be parsed

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

        # If no comma, might be standard notation or integer
        if ',' not in num_str:
            return float(num_str.replace('.', ''))  # Remove thousands dots

        # European format: dots are thousands, comma is decimal
        cleaned = num_str.replace(".", "").replace(",", ".")
        try:
            return float(cleaned)
        except ValueError:
            self.log(f"Could not parse number: {num_str}", "WARNING")
            return 0.0

    def _parse_date(self, date_str: str) -> Optional[str]:
        """Parse date from DD.MM.YYYY format to YYYY-MM-DD"""
        if not date_str:
            return None

        # Try DD.MM.YYYY format
        match = re.match(r'(\d{1,2})\.(\d{1,2})\.(\d{4})', date_str.strip())
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

        Udea format examples:
        - "Invoice number: 1118761"
        - "Invoice number : 1118761"
        - "Invoice date : 17.01.2026"
        - "Total excluding vat 4516,55"
        - "No VAT over 4516,55 0,00" (means VAT is 0.00)
        - "Total including vat EUR 4516,55"

        Returns:
            {
                'invoice_number': '1118761',
                'invoice_date': '2026-01-17',
                'total_excl_vat': 4516.55,
                'vat_amount': 0.00,
                'total_incl_vat': 4516.55,
                'is_zero_vat': True
            }
        """
        header = {
            'invoice_number': None,
            'invoice_date': None,
            'total_excl_vat': 0.0,
            'vat_amount': 0.0,
            'total_incl_vat': 0.0,
            'is_zero_vat': False,
        }

        # Extract invoice number
        inv_patterns = [
            r'Invoice\s*number\s*[:.]?\s*(\d+)',
            r'Factuurnummer\s*[:.]?\s*(\d+)',
            r'Invoice\s*[:.]?\s*(\d{6,})',
            r'Nr\.?\s*[:.]?\s*(\d{6,})',
        ]
        for pattern in inv_patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                header['invoice_number'] = match.group(1)
                self.log(f"Found invoice number: {header['invoice_number']}", "DEBUG")
                break

        # Extract invoice date - look for various patterns
        date_patterns = [
            r'Invoice\s*date\s*[:.]?\s*(\d{1,2}[.\-/]\d{1,2}[.\-/]\d{4})',
            r'Factuurdatum\s*[:.]?\s*(\d{1,2}[.\-/]\d{1,2}[.\-/]\d{4})',
            r'Datum\s*[:.]?\s*(\d{1,2}[.\-/]\d{1,2}[.\-/]\d{4})',
            # Header pattern: Veghel, 17.01.2026
            r'Veghel,?\s*(\d{1,2}\.\d{1,2}\.\d{4})',
        ]
        for pattern in date_patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                header['invoice_date'] = self._parse_date(match.group(1))
                self.log(f"Found invoice date: {header['invoice_date']}", "DEBUG")
                break

        # Extract total excluding VAT - Udea specific patterns
        # Use TOTAL_NUMBER which is more flexible for larger amounts
        # Allow optional minus sign for credit notes
        excl_patterns = [
            rf'Total\s+excluding\s+vat\s+(-?{TOTAL_NUMBER})',
            rf'Totaal\s+exclusief\s+BTW\s+[€]?\s*(-?{TOTAL_NUMBER})',
            rf'Total\s+excl\.?\s+VAT\s+[€]?\s*(-?{TOTAL_NUMBER})',
        ]
        for pattern in excl_patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                header['total_excl_vat'] = self._clean_number_string(match.group(1))
                self.log(f"Found total excl VAT: {header['total_excl_vat']}", "DEBUG")
                break

        # Extract VAT amount - look for "No VAT over X Y" pattern (Y is the VAT amount)
        # Or standard VAT patterns
        vat_patterns = [
            rf'No\s+VAT\s+over\s+-?{TOTAL_NUMBER}\s+({TOTAL_NUMBER})',  # "No VAT over -3873,20 0,00"
            rf'VAT\s+\d+%?\s+[€]?\s*({TOTAL_NUMBER})',
            rf'BTW\s+\d+%?\s+[€]?\s*({TOTAL_NUMBER})',
        ]
        for pattern in vat_patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                header['vat_amount'] = self._clean_number_string(match.group(1))
                self.log(f"Found VAT amount: {header['vat_amount']}", "DEBUG")
                break

        # Check if VAT is zero
        header['is_zero_vat'] = header['vat_amount'] == 0.0

        # Calculate total including VAT
        header['total_incl_vat'] = header['total_excl_vat'] + header['vat_amount']

        # Try to find explicit total including VAT
        incl_patterns = [
            rf'Total\s+including\s+vat\s+(?:EUR\s+)?(-?{TOTAL_NUMBER})',
            rf'Totaal\s+inclusief\s+BTW\s+[€]?\s*(-?{TOTAL_NUMBER})',
            rf'Total\s+incl\.?\s+VAT\s+[€]?\s*(-?{TOTAL_NUMBER})',
            rf'Te\s+betalen\s*[€]?\s*(-?{TOTAL_NUMBER})',
        ]
        for pattern in incl_patterns:
            match = re.search(pattern, text, re.IGNORECASE)
            if match:
                parsed_incl = self._clean_number_string(match.group(1))
                header['total_incl_vat'] = parsed_incl
                # If we found incl but not excl, set excl to incl - vat
                if header['total_excl_vat'] == 0.0:
                    header['total_excl_vat'] = parsed_incl - header['vat_amount']
                self.log(f"Found total incl VAT: {parsed_incl}", "DEBUG")
                break

        # Extract "Total products" for validation
        total_products_match = re.search(rf'Total\s+products\s+({TOTAL_NUMBER})', text, re.IGNORECASE)
        if total_products_match:
            header['total_products_expected'] = self._clean_number_string(total_products_match.group(1))
            self.log(f"Found Total products: {header['total_products_expected']}", "DEBUG")

        return header

    def _extract_product_lines(self, text: str) -> List[Dict[str, Any]]:
        """Extract product line items from invoice.

        Udea invoice line format (space-separated, date merges with article code):
        DD.MM.YYArticle Amount SKU Content Description Quality Gb.rek Country Price Sale VAT Profit% Total

        Example: "15.01.2612047 1 12 125gram Blueberry, . Biologisch 30302 CL 2,07 3,59 1 37% 24,84"
        Where "15.01.26" is the date and "12047" is the article code.

        Returns list of:
            {
                'article_code': '12045',
                'description': 'Product name',
                'quantity': 5,
                'unit_price': 2.50,
                'line_total': 12.50,
                'line_type': 'product_for_resale',
                'gbrek': '30302',
                'content': '500 gram'
            }
        """
        lines = []

        # Split text into lines
        text_lines = text.split('\n')

        # Pre-process function to fix common PDF extraction corruptions
        def preprocess_line(line: str) -> str:
            # The corruption pattern is:
            # 'Bio-Dynamisch 30302' -> 'Bio-Dynamis3c0h302' (ch->3c0h, first 3 merged)
            # So '3c0h' followed by 3-4 digits represents the Gb.rek

            # Fix 'Bio-Dynamis3c0h' + digits -> 'Bio-Dynamisch 30' + digits
            line = re.sub(r'Bio-Dynamis3c0h(\d{3,4})', r'Bio-Dynamisch 30\1', line)

            # Fix 'Niet-biologis3c0h' + digits -> 'Niet-biologisch 30' + digits
            line = re.sub(r'Niet-biologis3c0h(\d{3,4})', r'Niet-biologisch 30\1', line)

            # Fix 'Biologis3c0h' + digits -> 'Biologisch 30' + digits
            line = re.sub(r'[Bb]iologis3c0h(\d{3,4})', r'Biologisch 30\1', line)

            # Fix 'agrarisc3h0' + digits -> 'agrarisch 30' + digits (non-organic marker)
            line = re.sub(r'agrarisc3h0(\d{3})', r'agrarisch 30\1', line)

            # Generic fix: any '3c0h' or '3h0' followed by 3 digits is likely a corrupted Gb.rek
            # '3c0h252' -> ' 30252 ', '3h0362' -> ' 30362 '
            line = re.sub(r'3c0h(\d{3})', r' 30\1 ', line)
            line = re.sub(r'3h0(\d{3})', r' 30\1 ', line)
            line = re.sub(r'3n0i3t', r' 303', line)  # Handle 'cShch3n0i3tz2e2r' type

            # Fix units merged with descriptions
            # "1kilogramOnions" -> "1kilogram Onions"
            # "300millilitreHand-soap" -> "300millilitre Hand-soap"
            line = re.sub(r'(\d+(?:kilogram|gram|litre|millilitre|pc|stuks))([A-Z])', r'\1 \2', line)

            return line

        # Product line pattern for Udea format:
        # Date is DD.MM.YY merged with article code
        # Example: "15.01.2612047 1 12 125gram Blueberry, . Biologisch 30302 CL 2,07 3,59 1 37% 24,84"
        product_pattern = re.compile(
            r'^(\d{2}\.\d{2}\.\d{2})'     # Date DD.MM.YY
            r'(\d{2,7})\s+'               # Article code (merged with date, 2-7 digits)
            r'(\d+)\s+'                   # Amount/Quantity ordered
            r'(\d+)\s+'                   # SKU/pack size
            r'(\S+)\s+'                   # Content (e.g., "125gram", "1pc", "1kilogram")
            r'(.+?)'                      # Description (non-greedy)
            r'\s+(\d{5})\s+'              # Gb.rek code (5 digits) with spaces
            r'([A-Z]{2})\s+'              # Country code (2 letters)
            rf'({NUMBER})\s+'             # Purchase price
            rf'({NUMBER})\s+'             # Sale price
            r'(\d+)\s+'                   # VAT code
            rf'(\d+%?)\s+'                # Profit percentage
            rf'({NUMBER})\s*$'            # Line total
        )

        # Pattern for lines where Gb.rek is merged with quality (e.g., "Biologisch30302")
        merged_gbrek_pattern = re.compile(
            r'^(\d{2}\.\d{2}\.\d{2})'     # Date DD.MM.YY
            r'(\d{2,7})\s+'               # Article code
            r'(\d+)\s+'                   # Amount
            r'(\d+)\s+'                   # SKU
            r'(\S+)\s+'                   # Content
            r'(.+?)'                      # Description including quality
            r'(\d{5})\s+'                 # Gb.rek merged (no leading space)
            r'([A-Z]{2})\s+'              # Country code
            rf'({NUMBER})\s+'             # Purchase price
            rf'({NUMBER})\s+'             # Sale price
            r'(\d+)\s+'                   # VAT code
            rf'(\d+%?)\s+'                # Profit percentage
            rf'({NUMBER})\s*$'            # Line total
        )

        # Pattern with extra fields (some lines have "313" or similar extra number after country)
        extended_pattern = re.compile(
            r'^(\d{2}\.\d{2}\.\d{2})'     # Date DD.MM.YY
            r'(\d{2,7})\s+'               # Article code
            r'(\d+)\s+'                   # Amount
            r'(\d+)\s+'                   # SKU
            r'(\S+)\s+'                   # Content
            r'(.+?)'                      # Description
            r'\s+(\d{5})\s+'              # Gb.rek
            r'([A-Z]{2})\s+'              # Country code
            r'(\d+)\s+'                   # Extra field (like "313")
            rf'({NUMBER})\s+'             # Purchase price
            rf'({NUMBER})\s+'             # Sale price
            r'(\d+)\s+'                   # VAT code
            rf'(\d+%?)\s+'                # Profit percentage
            rf'({NUMBER})\s*$'            # Line total
        )

        # Simplified fallback pattern - more permissive
        simple_pattern = re.compile(
            r'^(\d{2}\.\d{2}\.\d{2})'     # Date DD.MM.YY
            r'(\d+)\s+'                   # Article code (any length after date)
            r'(\d+)\s+'                   # Amount
            r'.+?'                        # Middle content
            r'(\d{5})\s*'                 # Gb.rek (5 digits, may be merged)
            r'[A-Z]{2}\s+'                # Country
            r'.*?'                        # Prices (more permissive)
            rf'({TOTAL_NUMBER})\s*$'      # Line total at end
        )

        # Ultra-flexible fallback - just needs date at start, Gb.rek somewhere, and total at end
        fallback_pattern = re.compile(
            r'^(\d{2}\.\d{2}\.\d{2})'     # Date DD.MM.YY
            r'(\d+)\s+'                   # Article code
            r'(\d+)\s+'                   # Amount
            r'(.+?)'                      # Everything in middle (greedy minimal)
            r'\s(\d{5})\s'                # Gb.rek with spaces around it
            r'.+?'                        # Rest of line
            rf'({TOTAL_NUMBER})\s*$'      # Line total at end
        )

        in_product_section = False
        stop_keywords = ['Barrels delivered', 'Cost code', 'Costs', 'Total Order', 'Totaal']

        for raw_line in text_lines:
            line = raw_line.strip()
            self.stats['total_lines_scanned'] += 1

            # Skip empty lines
            if not line:
                continue

            # Check for stop keywords (end of product section)
            if any(keyword in line for keyword in stop_keywords):
                if 'Total Order' in line:
                    # Extract subtotal from Total Order line if present
                    continue
                if 'Barrels' in line or 'Cost' in line:
                    break

            # Pre-process line to fix common corruptions
            line = preprocess_line(line)

            # Try full pattern first
            match = product_pattern.match(line)
            if match:
                date, article, amount, sku, content, description, gbrek, country, price, sale, vat_code, profit, total = match.groups()

                # Clean up description - remove quality marker and trailing spaces
                description = description.strip()
                description = re.sub(r'\s*(Biologisch|Bio-Dynamisch|Organisch)\s*$', '', description, flags=re.IGNORECASE)
                description = description.rstrip(' ,.')

                line_type = GBREK_CLASSIFICATIONS.get(gbrek, 'product_for_resale')

                line_item = {
                    'article_code': article,
                    'description': f"{content} {description}".strip()[:100],
                    'quantity': int(amount),
                    'unit_price': self._clean_number_string(price),
                    'line_total': self._clean_number_string(total),
                    'line_type': line_type,
                    'gbrek': gbrek,
                    'parse_status': 'full',
                    'content': content,
                    'country': country,
                    'date': date,
                }

                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed product: {article} - {description[:30]}... Total: {line_item['line_total']}", "DEBUG")
                continue

            # Try merged Gb.rek pattern (where Gb.rek is merged with quality like "Bio-Dynamis3c0h302")
            merged_match = merged_gbrek_pattern.match(line)
            if merged_match:
                date, article, amount, sku, content, description, gbrek, country, price, sale, vat_code, profit, total = merged_match.groups()

                # Clean up description - remove quality markers
                description = description.strip()
                description = re.sub(r'\s*(Biologisch|Bio-Dynamisch|Bio-Dynamis3c0h|Niet-biologisch|Organisch)\s*$', '', description, flags=re.IGNORECASE)
                description = description.rstrip(' ,.')

                line_type = GBREK_CLASSIFICATIONS.get(gbrek, 'product_for_resale')

                line_item = {
                    'article_code': article,
                    'description': f"{content} {description}".strip()[:100],
                    'quantity': int(amount),
                    'unit_price': self._clean_number_string(price),
                    'line_total': self._clean_number_string(total),
                    'line_type': line_type,
                    'gbrek': gbrek,
                    'parse_status': 'full',
                    'content': content,
                    'country': country,
                    'date': date,
                }

                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed (merged): {article} - {description[:30]}... Total: {line_item['line_total']}", "DEBUG")
                continue

            # Try extended pattern (for lines with extra field after country like "DE 313")
            extended_match = extended_pattern.match(line)
            if extended_match:
                date, article, amount, sku, content, description, gbrek, country, extra, price, sale, vat_code, profit, total = extended_match.groups()

                description = description.strip()
                description = re.sub(r'\s*(Biologisch|Bio-Dynamisch|Niet-biologisch|Organisch)\s*$', '', description, flags=re.IGNORECASE)
                description = description.rstrip(' ,.')

                line_type = GBREK_CLASSIFICATIONS.get(gbrek, 'product_for_resale')

                line_item = {
                    'article_code': article,
                    'description': f"{content} {description}".strip()[:100],
                    'quantity': int(amount),
                    'unit_price': self._clean_number_string(price),
                    'line_total': self._clean_number_string(total),
                    'line_type': line_type,
                    'gbrek': gbrek,
                    'parse_status': 'full',
                    'content': content,
                    'country': country,
                    'date': date,
                }

                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed (extended): {article} - {description[:30]}... Total: {line_item['line_total']}", "DEBUG")
                continue

            # Try simpler pattern for lines that don't match full pattern
            simple_match = simple_pattern.match(line)
            if simple_match:
                date, article, amount, gbrek, total = simple_match.groups()

                # Extract description from the middle part
                article_end_pos = len(date) + len(article) + len(amount) + 2
                gbrek_pos = line.find(gbrek)
                middle_text = line[article_end_pos:gbrek_pos].strip() if gbrek_pos > article_end_pos else ''

                line_type = GBREK_CLASSIFICATIONS.get(gbrek, 'product_for_resale')

                line_item = {
                    'article_code': article,
                    'description': middle_text[:100],
                    'quantity': int(amount),
                    'unit_price': 0.0,
                    'line_total': self._clean_number_string(total),
                    'line_type': line_type,
                    'gbrek': gbrek,
                    'parse_status': 'full',
                    'date': date,
                }

                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed (simple): {article} - {middle_text[:30]}... Total: {line_item['line_total']}", "DEBUG")
                continue

            # Ultra-flexible fallback pattern
            fallback_match = fallback_pattern.match(line)
            if fallback_match:
                date, article, amount, middle, gbrek, total = fallback_match.groups()

                # Clean up description
                middle_text = middle.strip()
                middle_text = re.sub(r'\d+\s*$', '', middle_text).strip()  # Remove trailing numbers

                line_type = GBREK_CLASSIFICATIONS.get(gbrek, 'product_for_resale')

                line_item = {
                    'article_code': article,
                    'description': middle_text[:100],
                    'quantity': int(amount),
                    'unit_price': 0.0,
                    'line_total': self._clean_number_string(total),
                    'line_type': line_type,
                    'gbrek': gbrek,
                    'parse_status': 'full',
                    'date': date,
                }

                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed (fallback): {article} - {middle_text[:30]}... Total: {line_item['line_total']}", "DEBUG")
                continue

            # Last resort - find Gb.rek anywhere in the line (even embedded in text)
            # Only require date at start and total at end
            # Note: (\d+(?:\.\d+)?) handles decimal quantities like "60.212"
            last_resort_date = re.match(r'^(\d{2}\.\d{2}\.\d{2})(\d+)\s+(\d+(?:\.\d+)?)\s*', line)
            last_resort_total = re.search(rf'(\d+[,\.]\d{{2}})\s*$', line)

            # Look for Gb.rek patterns - try several approaches
            last_resort_gbrek = None

            # First try: standard 5-digit code (30xxx or 34xxx)
            gbrek_match = re.search(r'(3[04]\d{3})', line)
            if gbrek_match:
                last_resort_gbrek = gbrek_match.group(1)
            else:
                # Second try: look for patterns like "30" followed by 3 digits with possible garbage
                # e.g., "3e0252" -> extract 30252, "3s0282" -> 30282
                corrupted_match = re.search(r'3\D?0\D?(\d{3})', line)
                if corrupted_match:
                    last_resort_gbrek = '30' + corrupted_match.group(1)
                else:
                    # Third try: "34" patterns for non-food
                    corrupted_34 = re.search(r'3\D?4\D?(\d{3})', line)
                    if corrupted_34:
                        last_resort_gbrek = '34' + corrupted_34.group(1)
                    else:
                        # Fourth try: look for scrambled patterns
                        # "3n02e52" -> find 0, 2, 5, 2 -> could be 30252
                        # Look for 3 followed by any chars, then 0, then any chars with 3 digits
                        scrambled = re.search(r'3.{0,3}0.{0,2}(\d)(\d)(\d)', line)
                        if scrambled:
                            potential_gbrek = '30' + scrambled.group(1) + scrambled.group(2) + scrambled.group(3)
                            # Validate it looks like a Udea Gb.rek (30xxx or 34xxx range)
                            if potential_gbrek.startswith('30') or potential_gbrek.startswith('34'):
                                last_resort_gbrek = potential_gbrek

            if last_resort_date and last_resort_total and last_resort_gbrek:
                date = last_resort_date.group(1)
                article = last_resort_date.group(2)
                amount = last_resort_date.group(3)
                total = last_resort_total.group(1)
                gbrek = last_resort_gbrek  # Already a string now

                # Extract middle text
                start_pos = last_resort_date.end()
                end_pos = last_resort_total.start()
                middle_text = line[start_pos:end_pos].strip()

                line_type = GBREK_CLASSIFICATIONS.get(gbrek, 'product_for_resale')

                # Handle decimal quantities (e.g., "60.212" for weighted items)
                qty = float(amount)
                qty = int(qty) if qty == int(qty) else round(qty, 3)

                line_item = {
                    'article_code': article,
                    'description': middle_text[:80],
                    'quantity': qty,
                    'unit_price': 0.0,
                    'line_total': self._clean_number_string(total),
                    'line_type': line_type,
                    'gbrek': gbrek,
                    'parse_status': 'full',
                    'date': date,
                }

                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed (last-resort): {article} - Total: {line_item['line_total']}", "DEBUG")
                continue

            # If we get here, the line looks like a product line but couldn't be fully parsed
            # Try partial extraction - extract what we can (article, quantity, total, description)
            # Note: removed trailing \s+ to handle decimal quantities like "60.212"
            if re.match(r'^\d{2}\.\d{2}\.\d{2}\d+\s+\d+', line):
                # Extract article code (digits after date)
                article_match = re.match(r'^(\d{2}\.\d{2}\.\d{2})(\d+)\s+', line)
                # Extract quantity (first number after article, handles decimals like "60.212")
                qty_match = re.search(r'^\d{2}\.\d{2}\.\d{2}\d+\s+(\d+(?:\.\d+)?)', line)
                # Extract line total (last monetary value)
                total_match = re.search(r'(\d+[,\.]\d{2})\s*$', line)

                if article_match and total_match:
                    # We have enough to create a partial line
                    date = article_match.group(1)
                    article = article_match.group(2)
                    quantity = float(qty_match.group(1).replace(',', '.')) if qty_match else 1.0
                    total = total_match.group(1)

                    # Extract description (middle portion)
                    start_pos = article_match.end()
                    end_pos = total_match.start()
                    description = line[start_pos:end_pos].strip()
                    # Clean up description - remove numbers and prices
                    description = re.sub(r'\d+[,\.]\d{2}', '', description)
                    description = re.sub(r'\s+', ' ', description).strip()[:100]

                    line_item = {
                        'article_code': article,
                        'description': description,
                        'quantity': int(quantity) if quantity == int(quantity) else round(quantity, 2),
                        'unit_price': 0.0,  # Unknown
                        'line_total': self._clean_number_string(total),
                        'line_type': 'unknown',  # Cannot classify without Gb.rek
                        'gbrek': None,  # Not available
                        'parse_status': 'partial',
                        'raw_line_text': raw_line.strip()[:150],
                        'date': date,
                    }

                    lines.append(line_item)
                    self.stats['partial_lines_parsed'] += 1
                    self.log(f"Parsed (partial): {article} - Total: {line_item['line_total']}", "DEBUG")
                    continue

                # Only add to problem_lines if we can't extract article + total
                self.problem_lines.append({
                    'original': raw_line.strip()[:150],
                    'reason': "cannot extract article_code or line_total",
                })
                self.stats['skipped_lines'] += 1

        return lines

    def _extract_product_returns(self, text: str) -> List[Dict[str, Any]]:
        """Extract returned product lines from the 'Products Returned' section.

        Return lines have negative amounts and totals:
        16.10.254126155 -1 1 140gram Roompaté, St. Hendrick 5936 30282 NL 1,95 1 -1,95

        Returns list of line items with negative line_total values.
        """
        lines = []

        returns_start = text.find('Products Returned')
        if returns_start == -1:
            return lines

        returns_text = text[returns_start:]

        # Pattern for return lines - like product lines but with negative amount and negative total
        # Date+OrderCode  -Amount  SKU  Content  Description  ArticleCode  Gb.rek  Country  Price  VAT  -Total
        return_pattern = re.compile(
            r'^(\d{2}\.\d{2}\.\d{2})'     # Date DD.MM.YY
            r'(\d{2,7})\s+'               # Order code (merged with date)
            r'(-\d+)\s+'                  # Negative amount
            r'(\d+)\s+'                   # SKU
            r'(\S+)\s+'                   # Content (e.g., "140gram")
            r'(.+?)'                      # Description (non-greedy)
            r'\s*?(\d{3,7})\s+'           # Article code (may be merged with description)
            r'(\d{5})\s+'                 # Gb.rek code
            r'([A-Z]{2})\s+'              # Country code
            rf'({NUMBER})\s+'             # Unit price
            r'(\d+)\s+'                   # VAT code
            r'(-' + NUMBER + r')\s*$'     # Negative total
        )

        for raw_line in returns_text.split('\n'):
            line = raw_line.strip()

            if not line:
                continue

            # Stop at end of returns section
            if 'Total products returned' in line or 'Barrels delivered' in line or 'Cost code' in line:
                break

            # Skip headers
            if line.startswith('Products Returned') or line.startswith('Date '):
                continue

            match = return_pattern.match(line)
            if match:
                date, order, amount, sku, content, description, article, gbrek, country, price, vat_code, total = match.groups()

                description = description.strip().rstrip(' ,.')
                line_type = GBREK_CLASSIFICATIONS.get(gbrek, 'product_for_resale')

                line_item = {
                    'article_code': article,
                    'description': f"{content} {description}".strip()[:100],
                    'quantity': int(amount),  # Negative
                    'unit_price': self._clean_number_string(price),
                    'line_total': self._clean_number_string(total),  # Negative
                    'line_type': line_type,
                    'gbrek': gbrek,
                    'parse_status': 'full',
                    'content': content,
                    'country': country,
                    'date': date,
                    'is_return': True,
                }

                lines.append(line_item)
                self.stats['product_lines_parsed'] += 1
                self.log(f"Parsed return: {article} - {description[:30]}... Total: {line_item['line_total']}", "DEBUG")

        return lines

    def _extract_barrels(self, text: str) -> Dict[str, Any]:
        """Extract barrel deposits from the invoice.

        Returns:
            {
                'items': [
                    {'code': '69', 'quantity': 1, 'description': 'Crate deposit',
                     'price': 1.50, 'total': 1.50, 'line_type': 'deposit_or_returnable_packaging'},
                ],
                'total': 44.28
            }
        """
        result = {'items': [], 'total': 0.0}

        # Find barrels section
        barrels_start = text.find('Barrels delivered')
        if barrels_start == -1:
            return result

        # Find end of barrels section
        barrels_text = text[barrels_start:]
        costs_start = barrels_text.find('Cost code')
        if costs_start == -1:
            costs_start = barrels_text.find('Costs')

        if costs_start != -1:
            barrels_section = barrels_text[:costs_start]
        else:
            barrels_section = barrels_text[:500]  # Limit search

        # Parse barrel lines: BrlCode Amount Description Price VAT Total
        barrel_pattern = re.compile(
            rf'^(\d+)\s+(\d+)\s+(.+?)\s+({NUMBER})\s+\d+\s+({NUMBER})\s*$',
            re.MULTILINE
        )

        for match in barrel_pattern.finditer(barrels_section):
            code, qty, desc, price, total = match.groups()
            item = {
                'code': code,
                'quantity': int(qty),
                'description': desc.strip()[:50],
                'price': self._clean_number_string(price),
                'total': self._clean_number_string(total),
                'line_type': 'deposit_or_returnable_packaging',
            }
            result['items'].append(item)
            self.stats['barrel_lines_parsed'] += 1
            self.log(f"Parsed barrel: {code} - {desc[:30]}... Total: {item['total']}", "DEBUG")

        # Extract total barrels
        total_match = re.search(rf'Total barrels delivered\s+({NUMBER})', barrels_section)
        if total_match:
            result['total'] = self._clean_number_string(total_match.group(1))
        elif result['items']:
            result['total'] = sum(item['total'] for item in result['items'])

        return result

    def _extract_barrel_returns(self, text: str) -> Dict[str, Any]:
        """Extract barrel returns from a credit note.

        Credit note format (section "Barrels returned"):
        Date Brl -Amount TicketNr Description Price VAT -Total
        21.10.25 7 -1 90202431 Europallet 15,00 1 -15,00

        Returns same structure as _extract_barrels but with negative totals.
        """
        result = {'items': [], 'total': 0.0}

        # Find barrel returns section
        returns_start = text.find('Barrels returned')
        if returns_start == -1:
            return result

        # Find end of section
        returns_text = text[returns_start:]
        for end_marker in ['Total barrels returned', 'Distribution by group', 'Total excluding']:
            end_pos = returns_text.find(end_marker)
            if end_pos != -1:
                returns_section = returns_text[:end_pos + 200]  # Include total line
                break
        else:
            returns_section = returns_text[:2000]

        # Parse barrel return lines:
        # DD.MM.YY BrlCode -Amount TicketNr Description Price VAT -Total
        barrel_return_pattern = re.compile(
            r'^(\d{2}\.\d{2}\.\d{2})\s+'      # Date DD.MM.YY
            r'(\d+)\s+'                         # Barrel code
            r'(-\d+)\s+'                        # Negative amount
            r'(\d+)\s+'                         # Ticket number
            r'(.+?)\s+'                         # Description
            rf'({NUMBER})\s+'                   # Price
            r'\d+\s+'                           # VAT code
            rf'(-{NUMBER})\s*$',                # Negative total
            re.MULTILINE
        )

        for match in barrel_return_pattern.finditer(returns_section):
            date, code, qty, ticket, desc, price, total = match.groups()
            item = {
                'code': code,
                'quantity': int(qty),
                'description': desc.strip()[:50],
                'price': self._clean_number_string(price),
                'total': self._clean_number_string(total),
                'line_type': 'deposit_or_returnable_packaging',
                'ticket': ticket,
                'date': date,
                'is_return': True,
            }
            result['items'].append(item)
            self.stats['barrel_lines_parsed'] += 1
            self.log(f"Parsed barrel return: {code} - {desc.strip()[:30]}... Total: {item['total']}", "DEBUG")

        # Extract total barrel returns
        total_match = re.search(rf'Total barrels returned\s+(-{TOTAL_NUMBER})', returns_section)
        if total_match:
            result['total'] = self._clean_number_string(total_match.group(1))
        elif result['items']:
            result['total'] = sum(item['total'] for item in result['items'])

        return result

    def _extract_costs(self, text: str) -> Dict[str, Any]:
        """Extract costs/freight from the invoice.

        Udea format example:
        "15.01.264294418 1 Freight 2 293,55 1 293,55"
        or
        "Total Costs 293,55"

        Returns:
            {
                'items': [
                    {'code': '1', 'description': 'Freight', 'total': 293.55,
                     'line_type': 'freight_or_service'},
                ],
                'total': 293.55
            }
        """
        result = {'items': [], 'total': 0.0}

        # Find costs section - look for "Costs" header after product lines
        costs_markers = ['Total Costs', 'Costs\n', '\nCosts']
        costs_start = -1
        for marker in costs_markers:
            pos = text.find(marker)
            if pos != -1:
                costs_start = pos
                break

        # Also try to find cost lines by pattern (date + order number + cost description)
        # Pattern: "15.01.264294418 1 Freight 2 293,55 1 293,55"
        cost_line_pattern = re.compile(
            rf'^(\d{{2}}\.\d{{2}}\.\d{{2}})(\d+)\s+(\d+)\s+(Freight|Vracht|Transport)\s+\d+\s+({TOTAL_NUMBER})\s+\d+\s+({TOTAL_NUMBER})\s*$',
            re.MULTILINE | re.IGNORECASE
        )

        for match in cost_line_pattern.finditer(text):
            date, order_num, qty, desc, price, total = match.groups()
            item = {
                'code': order_num,
                'description': desc.strip(),
                'quantity': int(qty),
                'unit_price': self._clean_number_string(price),
                'total': self._clean_number_string(total),
                'line_type': 'freight_or_service',
                'date': date,
            }
            result['items'].append(item)
            self.stats['cost_lines_parsed'] += 1
            self.log(f"Parsed cost: {desc} - Total: {item['total']}", "DEBUG")

        # Extract Total Costs line if present
        total_costs_match = re.search(rf'Total\s+Costs\s+({TOTAL_NUMBER})', text, re.IGNORECASE)
        if total_costs_match:
            result['total'] = self._clean_number_string(total_costs_match.group(1))
            self.log(f"Found Total Costs: {result['total']}", "DEBUG")

            # If we didn't find any cost lines, create one from the total
            if not result['items'] and result['total'] > 0:
                result['items'].append({
                    'code': '1',
                    'description': 'Freight/Transport',
                    'total': result['total'],
                    'line_type': 'freight_or_service',
                })
        elif result['items']:
            # Calculate total from items
            result['total'] = sum(item['total'] for item in result['items'])

        return result

    def _validate_totals(self, header: Dict, products: List, barrels: Dict, costs: Dict) -> Dict[str, Any]:
        """Validate that line totals reconcile with invoice total.

        Returns:
            {
                'is_valid': True/False,
                'products_total': 4082.96,
                'barrels_total': 140.04,
                'costs_total': 293.55,
                'calculated_total': 4516.55,
                'invoice_total': 4516.55,
                'difference': 0.00,
                'tolerance_ok': True
            }
        """
        products_total = sum(p['line_total'] for p in products)
        barrels_total = barrels['total']
        costs_total = costs['total']

        calculated_total = products_total + barrels_total + costs_total
        invoice_total = header['total_excl_vat']

        difference = abs(calculated_total - invoice_total)
        tolerance = 0.50  # Allow 50 cents tolerance for rounding

        validation = {
            'is_valid': difference <= tolerance,
            'products_total': round(products_total, 2),
            'barrels_total': round(barrels_total, 2),
            'costs_total': round(costs_total, 2),
            'calculated_total': round(calculated_total, 2),
            'invoice_total': round(invoice_total, 2),
            'difference': round(difference, 2),
            'tolerance_ok': difference <= tolerance,
        }

        if not validation['is_valid']:
            self.log(f"Total validation failed: calculated={calculated_total:.2f}, invoice={invoice_total:.2f}, diff={difference:.2f}", "WARNING")

        # Validate products total against "Total products" if available
        if 'total_products_expected' in header:
            expected = header['total_products_expected']
            validation['products_expected'] = round(expected, 2)
            products_diff = abs(products_total - expected)
            validation['products_difference'] = round(products_diff, 2)
            if products_diff > tolerance:
                validation['products_mismatch'] = True
                self.log(f"Products mismatch: parsed={products_total:.2f}, expected={expected:.2f}, diff={products_diff:.2f}", "WARNING")
            else:
                validation['products_mismatch'] = False

        return validation

    def parse_invoice(self, pdf_path: str) -> Dict[str, Any]:
        """
        Parse a UDEA invoice PDF and return structured data.

        Returns:
            {
                "success": bool,
                "supplier": "Udea",
                "header": {
                    "invoice_number": "1118761",
                    "invoice_date": "2026-01-17",
                    "total_excl_vat": 4516.55,
                    "vat_amount": 0.00,
                    "is_zero_vat": True
                },
                "lines": [
                    {
                        "article_code": "32350",
                        "description": "...",
                        "quantity": 1,
                        "unit_price": 7.00,
                        "line_total": 17.50,
                        "line_type": "product_for_resale",
                        "gbrek": "30322"
                    },
                    ...
                ],
                "barrels": {...},
                "costs": {...},
                "validation": {...},
                "errors": [],
                "warnings": []
            }
        """
        result = {
            "success": False,
            "supplier": "Udea",
            "header": {},
            "lines": [],
            "barrels": {"items": [], "total": 0.0},
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
                # Include raw text in debug mode
                result["metadata"]["raw_text_preview"] = text[:2000]

            # Extract header information
            header = self._extract_header(text)
            result["header"] = header

            if not header['invoice_number']:
                result["warnings"].append("Could not extract invoice number")

            if not header['invoice_date']:
                result["warnings"].append("Could not extract invoice date")

            # Extract product lines
            products = self._extract_product_lines(text)

            # Extract product returns (negative amounts)
            returns = self._extract_product_returns(text)
            if returns:
                products.extend(returns)
                self.log(f"Added {len(returns)} return lines (negative totals)", "INFO")

            result["lines"] = products

            if not products:
                result["warnings"].append("No product lines could be extracted")

            # Detect credit note (negative total)
            is_credit_note = header.get('total_excl_vat', 0) < 0
            if is_credit_note:
                result["is_credit_note"] = True
                self.log("Credit note detected (negative total)", "INFO")

            # Extract barrels (deposits)
            barrels = self._extract_barrels(text)

            # Extract barrel returns (credit notes for returned crates)
            barrel_returns = self._extract_barrel_returns(text)
            if barrel_returns['items']:
                barrels['items'].extend(barrel_returns['items'])
                barrels['total'] += barrel_returns['total']
                self.log(f"Added {len(barrel_returns['items'])} barrel return lines", "INFO")

            result["barrels"] = barrels

            # Extract costs (freight/service)
            costs = self._extract_costs(text)
            result["costs"] = costs

            # Validate totals
            validation = self._validate_totals(header, products, barrels, costs)
            result["validation"] = validation

            if not validation['is_valid']:
                result["warnings"].append(
                    f"Total mismatch: calculated {validation['calculated_total']:.2f} vs invoice {validation['invoice_total']:.2f} (diff: {validation['difference']:.2f})"
                )

            # Set success based on critical extractions
            result["success"] = bool(header['invoice_number'] and (products or barrels['items'] or costs['items']))

            # Add stats
            result["metadata"]["stats"] = self.stats

            # Add problem lines if any
            if self.problem_lines:
                result["problem_lines"] = self.problem_lines

            self.log(f"Parsing complete: {len(products)} products, {len(barrels['items'])} barrels, {len(costs['items'])} costs", "INFO")
            if self.problem_lines:
                self.log(f"Problem lines: {len(self.problem_lines)}", "WARNING")

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
    Main entry point for parsing a UDEA invoice PDF.

    Args:
        pdf_path: Path to the PDF file
        verbose: Enable verbose logging
        debug: Enable debug logging

    Returns:
        Dictionary with parsed invoice data
    """
    parser = InvoiceUdeaParser(verbose=verbose, debug=debug)
    return parser.parse_invoice(pdf_path)


# CLI interface
if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description='Parse UDEA invoice PDF')
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
