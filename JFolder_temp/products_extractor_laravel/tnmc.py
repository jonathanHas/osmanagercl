import os
import re
import csv
import argparse
import logging
import shutil
import glob
from typing import List, Dict, Optional, Tuple, Pattern, Final
import pdfplumber

# Target directory for copying processed PDFs
PDF_COPY_TARGET_DIR: Final[str] = "/home/jon/Dropbox/Daily/invoices/Print/TNMC"

# Enhanced CSV field names (for new Laravel system)
CSV_FIELDNAMES_ENHANCED: Final[List[str]] = [
    "Stock_Code", "Description", "Unit", "RRP", "Qty", "Tr_Price",
    "Disc_Percent", "Total", "VAT_Percent", "Calculated_Total",
    "Validation_Status", "SourcePDF"
]

# Legacy CSV field names (for old system compatibility)
CSV_FIELDNAMES_LEGACY: Final[List[str]] = [
    "Stock_Code", "Description", "Unit", "RRP", "Qty", "Tr_Price",
    "Disc_Percent", "Total", "VAT_Percent", "SourcePDF"
]

# --- Logging Setup ---
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')


class TNMCInvoiceParser:
    """Parser for The Natural Medicine Company invoices"""

    def __init__(self, verbose=False, debug=False):
        self.verbose = verbose
        self.debug = debug
        self.stats = {
            'total_lines': 0,
            'parsed_lines': 0,
            'price_validations_passed': 0,
            'price_mismatches': 0,
            'unmatched_lines': 0
        }
        self.parsing_log = []

        # Main regex pattern for product lines
        # Pattern: StockCode Description Each RRP Qty Tr.Price Disc% % Total VAT%
        self.product_pattern = re.compile(
            r'^(\d{5})\s+'           # Stock Code (5 digits)
            r'(.+?)\s+'              # Description (non-greedy)
            r'Each\s+'               # Unit (always "Each")
            r'(\d+\.\d{2})\s+'       # RRP
            r'(\d+)\s+'              # Qty
            r'(\d+\.\d{2})\s+'       # Tr. Price
            r'(\d+\.\d{2})\s+%\s+'   # Disc %
            r'(\d+\.\d{2})\s+'       # Total
            r'(\d+\.\d+)\s+%'        # VAT % (can be 23.0, 13.5, 0.0, etc.)
            r'$'
        )

        # Skip terms to ignore header/footer lines
        self.skip_terms = [
            "Stock Code", "Description", "Unit", "RRP", "Qty", "Tr. Price",
            "Invoice", "Deliver To", "Invoice To", "VAT No.", "Phone:", "Fax:",
            "Email:", "Web:", "Date:", "Cust A/c.:", "Pick List:", "Order Ref:",
            "No of boxes:", "Assistant:", "All amounts are", "SUB-TOTAL", "GRAND TOTAL",
            "Page ", " of ", "Goods remain the property", "Rate", "Goods", "Vat Due",
            "Copy Invoice"
        ]

    def log(self, message: str, level: str = "INFO"):
        """Add message to parsing log"""
        log_entry = f"[{level}] {message}"
        self.parsing_log.append(log_entry)
        if self.verbose or level in ["ERROR", "WARNING"]:
            print(log_entry)

    def should_skip_line(self, line: str) -> bool:
        """Check if line should be skipped (header/footer/etc)"""
        return any(term in line for term in self.skip_terms)

    def parse_line(self, line: str, page_num: int, line_num: int) -> Optional[Dict[str, str]]:
        """Parse a single product line"""
        match = self.product_pattern.match(line)

        if match:
            stock_code = match.group(1)
            description = match.group(2).strip()
            rrp = match.group(3)
            qty = match.group(4)
            tr_price = match.group(5)
            disc_percent = match.group(6)
            total = match.group(7)
            vat_percent = match.group(8)

            self.log(f"✓ Parsed: {stock_code} - {description[:40]}...", "DEBUG") if self.debug else None

            return {
                "Stock_Code": stock_code,
                "Description": description,
                "Unit": "Each",
                "RRP": rrp,
                "Qty": qty,
                "Tr_Price": tr_price,
                "Disc_Percent": disc_percent,
                "Total": total,
                "VAT_Percent": vat_percent
            }

        return None

    def validate_calculation(self, parsed_data: Dict[str, str], tolerance: float = 0.01) -> Tuple[bool, float]:
        """
        Validate if Qty × Tr. Price = Total
        Returns: (is_valid, calculated_total)
        """
        try:
            qty = float(parsed_data["Qty"])
            tr_price = float(parsed_data["Tr_Price"])
            total_pdf = float(parsed_data["Total"])

            calculated_total = qty * tr_price

            # Check if calculated matches PDF total within tolerance
            is_valid = abs(calculated_total - total_pdf) <= tolerance

            return is_valid, calculated_total

        except (ValueError, KeyError) as e:
            self.log(f"Error validating calculation: {e}", "ERROR")
            return False, 0.0

    def parse_invoice(self, pdf_path: str) -> List[Dict[str, str]]:
        """Parse a TNMC invoice and return list of parsed products"""
        products = []
        filename = os.path.basename(pdf_path)

        self.log(f"Processing file: {filename}")

        try:
            with pdfplumber.open(pdf_path) as pdf:
                for page_idx, page in enumerate(pdf.pages):
                    page_num = page_idx + 1
                    text = page.extract_text()

                    if not text:
                        self.log(f"Page {page_num} has no extractable text", "WARNING")
                        continue

                    lines = text.split("\n")

                    for line_idx, raw_line in enumerate(lines):
                        line = raw_line.strip()
                        line_on_page = line_idx + 1

                        if not line:
                            continue

                        self.stats['total_lines'] += 1

                        # Skip header/footer lines
                        if self.should_skip_line(line):
                            self.log(f"Skipping: {line[:50]}...", "DEBUG") if self.debug else None
                            continue

                        # Try to parse the line
                        parsed = self.parse_line(line, page_num, line_on_page)

                        if parsed:
                            parsed["SourcePDF"] = filename

                            # Validate calculation
                            is_valid, calculated_total = self.validate_calculation(parsed)

                            parsed["Calculated_Total"] = f"{calculated_total:.2f}"
                            parsed["Validation_Status"] = "PASS" if is_valid else "FAIL"

                            if not is_valid:
                                self.stats['price_mismatches'] += 1
                                self.log(
                                    f"⚠ Price mismatch: {parsed['Stock_Code']} - "
                                    f"Expected: {calculated_total:.2f}, Got: {parsed['Total']}",
                                    "WARNING"
                                )
                            else:
                                self.stats['price_validations_passed'] += 1

                            products.append(parsed)
                            self.stats['parsed_lines'] += 1

                        elif line and not self.should_skip_line(line):
                            # Line wasn't parsed and isn't a skip term - might be product line
                            # Check if it starts with a 5-digit code
                            if re.match(r'^\d{5}\s+', line):
                                self.stats['unmatched_lines'] += 1
                                self.log(
                                    f"⚠ Unmatched product line at P{page_num} L{line_on_page}: {line[:80]}...",
                                    "WARNING"
                                )

        except Exception as e:
            self.log(f"Error parsing {filename}: {e}", "ERROR")
            logging.error(f"❌ Failed to process {filename}: {e}", exc_info=True)

        return products

    def generate_report(self, all_products: List[Dict[str, str]], output_base: str):
        """Generate parsing report"""
        report_lines = []
        report_lines.append("# TNMC Invoice Parsing Report")
        report_lines.append(f"Generated: {self._get_timestamp()}\n")

        # Summary statistics
        report_lines.append("## Summary Statistics")
        report_lines.append(f"- Total lines processed: {self.stats['total_lines']}")
        report_lines.append(f"- Successfully parsed: {self.stats['parsed_lines']}")
        report_lines.append(f"- Price validations passed: {self.stats['price_validations_passed']}")

        if self.stats['price_mismatches'] > 0:
            report_lines.append(f"- **Price validation warnings: {self.stats['price_mismatches']}**")

        if self.stats['unmatched_lines'] > 0:
            report_lines.append(f"- Unmatched lines (potential products): {self.stats['unmatched_lines']}")

        success_rate = (self.stats['parsed_lines'] / self.stats['total_lines'] * 100) if self.stats['total_lines'] > 0 else 0
        report_lines.append(f"- Success rate: {success_rate:.1f}%\n")

        # Files processed
        files_processed = set(product['SourcePDF'] for product in all_products)
        report_lines.append("## Files Processed")
        for filename in sorted(files_processed):
            file_products = [p for p in all_products if p['SourcePDF'] == filename]
            report_lines.append(f"- {filename}: {len(file_products)} products")

        # Write report
        report_path = f"{output_base}_report.md"
        with open(report_path, 'w', encoding='utf-8') as f:
            f.write('\n'.join(report_lines))

        print(f"📊 Report saved to {report_path}")

        # Save debug log if debug mode
        if self.debug and self.parsing_log:
            log_path = f"{output_base}_log.txt"
            with open(log_path, 'w', encoding='utf-8') as f:
                f.write('\n'.join(self.parsing_log))
            print(f"📋 Debug log saved to {log_path}")

    def _get_timestamp(self):
        """Get current timestamp as string"""
        from datetime import datetime
        return datetime.now().strftime('%Y-%m-%d %H:%M:%S')


def write_legacy_csv(all_products: List[Dict[str, str]], output_base: str) -> None:
    """Write legacy format CSV without validation columns"""
    if output_base.endswith('.csv'):
        legacy_path = output_base.replace('.csv', '_legacy.csv')
    else:
        legacy_path = f"{output_base}_legacy.csv"

    try:
        with open(legacy_path, 'w', newline='', encoding='utf-8') as f:
            writer = csv.DictWriter(f, fieldnames=CSV_FIELDNAMES_LEGACY, extrasaction='ignore')
            writer.writeheader()
            writer.writerows(all_products)

        print(f"📄 Legacy CSV saved: {os.path.basename(legacy_path)}")
    except Exception as e:
        print(f"❌ Failed to write legacy CSV: {e}")


def clear_target_directory_pdfs(target_dir: str) -> int:
    """Delete all PDF files in the target directory. Returns count deleted."""
    deleted_count = 0

    if not os.path.exists(target_dir):
        logging.info(f"Target directory does not exist, creating: {target_dir}")
        os.makedirs(target_dir, exist_ok=True)
        return 0

    pdf_pattern = os.path.join(target_dir, "*.pdf")
    existing_pdfs = glob.glob(pdf_pattern)

    for pdf_file in existing_pdfs:
        try:
            os.remove(pdf_file)
            deleted_count += 1
            logging.debug(f"Deleted: {os.path.basename(pdf_file)}")
        except Exception as e:
            logging.error(f"Failed to delete {pdf_file}: {e}")

    return deleted_count


def copy_pdfs_to_target(pdf_folder: str, target_dir: str) -> Tuple[int, int]:
    """Copy all PDF files from source folder to target directory."""
    success_count = 0
    failure_count = 0

    if not os.path.exists(target_dir):
        os.makedirs(target_dir, exist_ok=True)
        logging.info(f"Created target directory: {target_dir}")

    for fname in os.listdir(pdf_folder):
        if fname.lower().endswith(".pdf"):
            source_path = os.path.join(pdf_folder, fname)
            target_path = os.path.join(target_dir, fname)

            try:
                shutil.copy2(source_path, target_path)
                success_count += 1
                logging.debug(f"Copied: {fname}")
            except Exception as e:
                failure_count += 1
                logging.error(f"Failed to copy {fname}: {e}")

    return success_count, failure_count


def main():
    parser = argparse.ArgumentParser(
        description='The Natural Medicine Company (TNMC) Invoice Parser - Extract product data to CSV'
    )
    parser.add_argument(
        'path',
        nargs='?',
        default='.',
        help='Path to PDF file or directory (default: current directory)'
    )
    parser.add_argument(
        '-o', '--output',
        default='tnmc_output',
        help='Output filename base (default: tnmc_output)'
    )
    parser.add_argument(
        '-v', '--verbose',
        action='store_true',
        help='Enable verbose output'
    )
    parser.add_argument(
        '-d', '--debug',
        action='store_true',
        help='Enable debug mode with detailed logging'
    )

    args = parser.parse_args()

    # Initialize parser
    invoice_parser = TNMCInvoiceParser(verbose=args.verbose, debug=args.debug)

    # Get PDF files
    if os.path.isfile(args.path) and args.path.lower().endswith('.pdf'):
        pdf_files = [args.path]
        pdf_folder = os.path.dirname(args.path) or '.'
    else:
        directory = args.path if os.path.isdir(args.path) else '.'
        pdf_files = [os.path.join(directory, f) for f in os.listdir(directory) if f.lower().endswith('.pdf')]
        pdf_folder = directory

    if not pdf_files:
        print("❌ No PDF files found.")
        return

    print(f"🔍 Found {len(pdf_files)} PDF file(s). Processing...")

    all_products = []

    # Process each PDF
    for pdf_file in pdf_files:
        try:
            products = invoice_parser.parse_invoice(pdf_file)
            all_products.extend(products)

            filename = os.path.basename(pdf_file)
            if args.verbose:
                print(f"✅ {filename}: {len(products)} products parsed")

                # Show price validation status
                valid_prices = len([p for p in products if p.get('Validation_Status') == 'PASS'])
                if valid_prices == len(products):
                    print(f"   ✓ Price validation: All {valid_prices} products passed")
                else:
                    invalid_prices = len(products) - valid_prices
                    print(f"   ⚠ Price validation: {valid_prices} passed, {invalid_prices} failed")
            else:
                print(f"✅ {filename}: {len(products)} products extracted")

        except Exception as e:
            print(f"❌ Error parsing {pdf_file}: {e}")

    if not all_products:
        print("⚠️ No products extracted from any PDF files.")
        return

    # Save enhanced CSV (with validation columns)
    csv_path = f"{args.output}.csv"

    try:
        with open(csv_path, 'w', newline='', encoding='utf-8') as f:
            writer = csv.DictWriter(f, fieldnames=CSV_FIELDNAMES_ENHANCED)
            writer.writeheader()
            writer.writerows(all_products)

        print(f"\n✅ Enhanced CSV saved: {csv_path}")
        print(f"   Total products: {len(all_products)}")
    except Exception as e:
        print(f"❌ Failed to write enhanced CSV: {e}")
        return

    # Write legacy format CSV
    write_legacy_csv(all_products, args.output)

    # Generate report
    invoice_parser.generate_report(all_products, args.output)

    # Copy PDFs to target directory
    print(f"\n📂 Managing PDF files in: {PDF_COPY_TARGET_DIR}")

    # Delete existing PDFs in target directory
    deleted_count = clear_target_directory_pdfs(PDF_COPY_TARGET_DIR)
    if deleted_count > 0:
        print(f"🗑️  Deleted {deleted_count} existing PDF(s)")

    # Copy processed PDFs to target directory
    success_count, failure_count = copy_pdfs_to_target(pdf_folder, PDF_COPY_TARGET_DIR)
    if success_count > 0:
        print(f"✅ Copied {success_count} PDF(s) to target directory")
    if failure_count > 0:
        print(f"❌ Failed to copy {failure_count} PDF(s)")

    # Final summary
    print("\n" + "="*60)
    print("📊 PARSING SUMMARY")
    print("="*60)
    print(f"Total products parsed: {len(all_products)}")

    # Show price validation results
    if invoice_parser.stats['price_validations_passed'] > 0:
        passed = invoice_parser.stats['price_validations_passed']
        total = len(all_products)
        if passed == total:
            print(f"✅ Price validation: All {total} products passed")
        else:
            failed = total - passed
            print(f"⚠️  Price validation: {passed} passed, {failed} failed")
            print(f"   Check 'Validation_Status' column in CSV for details")

    success_rate = (invoice_parser.stats['parsed_lines'] / invoice_parser.stats['total_lines'] * 100) if invoice_parser.stats['total_lines'] > 0 else 0
    print(f"Overall success rate: {success_rate:.1f}%")

    print("\n📦 Output files generated:")
    print(f"  - Enhanced CSV: {args.output}.csv (12 columns)")
    print(f"  - Legacy CSV: {args.output}_legacy.csv (10 columns)")
    print("="*60)


if __name__ == "__main__":
    main()
