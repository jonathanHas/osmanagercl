import os
import re
import csv
import pdfplumber
from tabulate import tabulate
import argparse
from datetime import datetime
from typing import List, Dict, Tuple, Optional
import shutil
import glob

# Target directory for copying processed PDFs
PDF_COPY_TARGET_DIR = "/home/jon/Dropbox/Daily/invoices/Print/independent"
# Target directory for moving successfully parsed PDFs
PDF_MOVE_TARGET_DIR = "/home/jon/Documents/Invoices_in_limbo"

class IIHInvoiceParser:
    def __init__(self, verbose=False, debug=False):
        self.verbose = verbose
        self.debug = debug
        self.stats = {
            'total_lines': 0,
            'parsed_lines': 0,
            'potential_misses': 0,
            'high_confidence_misses': 0,
            'medium_confidence_misses': 0,
            'low_confidence_misses': 0
        }
        self.all_unparsed_lines = []
        self.parsing_log = []

    def log(self, message: str, level: str = "INFO"):
        """Add message to parsing log"""
        timestamp = datetime.now().strftime("%H:%M:%S")
        log_entry = f"[{timestamp}] [{level}] {message}"
        self.parsing_log.append(log_entry)
        if self.verbose or level in ["ERROR", "WARNING"]:
            print(log_entry)

    def extract_product_code(self, line: str) -> Optional[str]:
        """Extract product code from a line (first alphanumeric token)"""
        # Try to match product code at start of line
        match = re.match(r'^([A-Z0-9]{3,}[A-Z]?)\s', line)
        if match:
            return match.group(1)
        return None

    def parse_quantity(self, qty_str: str) -> Dict[str, float]:
        """Parse quantity string like '1/0' or '0/2' into cases and units"""
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
        """Extract case size from product description like '6x500ml' or '8x40g'"""
        # Look for patterns like 6x, 12x, etc.
        # Priority: Find the first/main pattern, usually at the beginning
        # Handle complex patterns like "10x(2x160g)" - we want the 10, not the 2

        # First try to find main case size pattern (typically early in description)
        matches = re.findall(r'(\d+)x(?:\d+|[\(\w])', product_desc, re.IGNORECASE)
        if matches:
            # Convert to integers and return the first (main) one
            case_sizes = [int(m) for m in matches]
            # For complex cases like "10x(2x160g)", prefer larger numbers as they're likely case sizes
            return max(case_sizes)  # Take the largest as it's more likely to be the case size
        return None

    def calculate_total_units(self, qty_dict: Dict, case_size: Optional[int]) -> float:
        """Calculate total units from cases and units"""
        if case_size:
            return (qty_dict['cases'] * case_size) + qty_dict['units']
        return qty_dict['units']  # If no case size, return just units

    def validate_price_calculation(self, delivered_qty: Dict, case_size: Optional[int],
                                   price: float, value: float, tolerance: float = 0.05) -> Tuple[bool, str, float, float]:
        """
        Validate if the calculated price matches the value.
        Returns: (is_valid, calculation_method, calculated_value, unit_cost)
        """
        # Calculate total delivered units first
        total_delivered_units = self.calculate_total_units(delivered_qty, case_size)

        # If no delivery (total units = 0), value should be 0
        if total_delivered_units == 0:
            is_valid = abs(value) < 0.01
            # Still calculate unit cost based on price and case size for non-delivered items
            if case_size and case_size > 0:
                unit_cost = price / case_size
            else:
                unit_cost = price  # If no case size, price is the unit cost
            return is_valid, "No delivery", 0.0, unit_cost

        # Method 1: Direct calculation for full cases only (price per case)
        if delivered_qty['cases'] > 0 and delivered_qty['units'] == 0:
            # Full cases - price might be per case
            calculated = delivered_qty['cases'] * price
            if abs(value - calculated) <= (calculated * tolerance) if calculated > 0 else abs(value) < 0.01:
                # For full cases, if case_size exists, calculate unit cost
                if case_size and case_size > 0:
                    unit_cost = price / case_size
                else:
                    unit_cost = price  # If no case size, price is unit cost
                return True, f"{delivered_qty['cases']} cases × {price:.2f}", calculated, unit_cost

        # Method 2: Unit price calculation (works for mixed cases/units or units only)
        if case_size and case_size > 0:
            # Try: price is per unit
            calculated = total_delivered_units * price
            if abs(value - calculated) <= (calculated * tolerance) if calculated > 0 else abs(value) < 0.01:
                return True, f"{total_delivered_units} units × {price:.2f}", calculated, price

            # Try: price is per case, calculate unit price
            unit_price = price / case_size
            calculated = total_delivered_units * unit_price
            if abs(value - calculated) <= (calculated * tolerance) if calculated > 0 else abs(value) < 0.01:
                return True, f"{total_delivered_units} units × {unit_price:.2f} (case price/{case_size})", calculated, unit_price

        # Method 3: Simple quantity (for items without case size)
        if case_size is None:
            calculated = total_delivered_units * price
            if abs(value - calculated) <= (calculated * tolerance) if calculated > 0 else abs(value) < 0.01:
                unit_cost = price  # For simple quantity, price is unit cost
                return True, f"{total_delivered_units} × {price:.2f}", calculated, unit_cost

        # Method 4: Derive unit cost from actual value (fallback for complex cases)
        if total_delivered_units > 0:
            derived_unit_cost = value / total_delivered_units
            # Check if this derived cost makes sense with the given price
            if case_size and case_size > 0:
                expected_unit_cost_from_case_price = price / case_size
                if abs(derived_unit_cost - expected_unit_cost_from_case_price) <= (expected_unit_cost_from_case_price * tolerance):
                    return True, f"{total_delivered_units} units × {derived_unit_cost:.2f} (derived)", value, derived_unit_cost

            # Direct price match check
            if abs(derived_unit_cost - price) <= (price * tolerance):
                return True, f"{total_delivered_units} units × {price:.2f} (direct)", value, price

        # No valid calculation found - still derive unit cost for reporting
        unit_cost = value / total_delivered_units if total_delivered_units > 0 else 0
        return False, "No matching calculation", 0.0, unit_cost

    def assess_confidence(self, line: str) -> str:
        """Assess confidence level that a line is a product line"""
        confidence_score = 0

        # Check for product code pattern (various formats)
        if re.match(r'^[A-Z0-9]{3,}[A-Z]?\s', line):
            confidence_score += 3

        # Count decimal numbers (prices, values)
        decimal_count = len(re.findall(r'\d+\.\d{2}', line))
        confidence_score += min(decimal_count * 2, 6)

        # Special boost for lines with exactly 3 decimals (likely missing RSP)
        if decimal_count == 3 and re.match(r'^[A-Z0-9]{3,}[A-Z]?\s', line):
            confidence_score += 2  # Boost confidence for potential missing RSP lines

        # Check for quantity patterns
        if re.search(r'\d+/\d+', line):
            confidence_score += 2

        # Check for case size pattern in product description
        if re.search(r'\d+x\d+', line):
            confidence_score += 1

        # Word count check
        words = line.split()
        if len(words) >= 6:
            confidence_score += 1

        if confidence_score >= 8:
            return "HIGH"
        elif confidence_score >= 5:
            return "MEDIUM"
        else:
            return "LOW"

    def parse_invoice(self, pdf_path: str) -> Tuple[List[Dict], List[Dict]]:
        """Parse invoice and return parsed rows and potential missed lines"""
        rows = []
        potential_missed_lines = []
        filename = os.path.basename(pdf_path)

        self.log(f"Processing file: {filename}")

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

        # Pattern 1.5: Missing RSP pattern (7 fields) - for lines where RSP is omitted
        missing_rsp_pattern = re.compile(
            r'^(\S+)\s+'  # Code
            r'(.*?)\s+'   # Product description
            r'(\d+/?[\d]*)\s+'  # Ordered qty
            r'(\d+/?[\d]*)\s+'  # Delivered qty
            r'(\d+\.\d{2})\s+'  # Price (no RSP)
            r'(\d+\.\d{2})\s+'  # Tax
            r'(\d+\.\d{2})$'    # Value
        )

        # Pattern 2: Relaxed pattern (missing some decimals)
        relaxed_pattern = re.compile(
            r'^(\S+)\s+'  # Code
            r'(.*?)\s+'   # Product description
            r'(\d+/?[\d]*)\s+'  # Ordered qty
            r'(\d+/?[\d]*)\s+'  # Delivered qty
            r'.*?(\d+\.\d{2})'  # At least one price at the end
        )

        # Pattern 3: Fallback pattern (minimum viable)
        fallback_pattern = re.compile(
            r'^([A-Z0-9]+[A-Z]?)\s+'  # Code (alphanumeric)
            r'(.*?)\s+'                # Description
            r'(\d+/?[\d]*)'           # At least one quantity
        )

        with pdfplumber.open(pdf_path) as pdf:
            for page_idx, page in enumerate(pdf.pages):
                page_num = page_idx + 1
                text = page.extract_text()
                if not text:
                    continue

                lines = text.split("\n")

                for line_idx, raw_line in enumerate(lines):
                    line_on_page = line_idx + 1
                    line = raw_line.strip()

                    if not line:
                        continue

                    self.stats['total_lines'] += 1

                    # Skip known non-product lines
                    if any(skip_term in line for skip_term in skip_terms):
                        self.log(f"Skipping header/footer line: {line[:50]}...", "DEBUG") if self.debug else None
                        continue

                    # Try strict pattern first
                    match = strict_pattern.match(line)
                    missing_rsp_match = None

                    if match:
                        code = match.group(1).strip()
                        product = match.group(2).strip()
                        ordered_raw = match.group(3).strip()
                        delivered_raw = match.group(4).strip()
                        rsp = match.group(5)
                        price = match.group(6)
                        tax = match.group(7)
                        value = match.group(8)

                        # Parse quantities
                        ordered_qty = self.parse_quantity(ordered_raw)
                        delivered_qty = self.parse_quantity(delivered_raw)

                        # Extract case size
                        case_size = self.extract_case_size(product)

                        # Calculate total units
                        ordered_units = self.calculate_total_units(ordered_qty, case_size)
                        delivered_units = self.calculate_total_units(delivered_qty, case_size)

                        # Validate price calculation
                        try:
                            price_float = float(price)
                            value_float = float(value)
                            is_valid, calc_method, calculated_value, unit_cost = self.validate_price_calculation(
                                delivered_qty, case_size, price_float, value_float
                            )
                        except ValueError:
                            is_valid = False
                            calc_method = "Parse error"
                            calculated_value = 0.0
                            unit_cost = 0.0

                        row = {
                            "Filename": filename,
                            "Code": code,
                            "Product": product,
                            "Ordered": ordered_raw,
                            "Qty": delivered_raw,
                            "Ordered_Cases": ordered_qty['cases'],
                            "Ordered_Units": ordered_qty['units'],
                            "Delivered_Cases": delivered_qty['cases'],
                            "Delivered_Units": delivered_qty['units'],
                            "Case_Size": case_size or "",
                            "Total_Ordered_Units": ordered_units,
                            "Total_Delivered_Units": delivered_units,
                            "RSP": rsp,
                            "Price": price,
                            "Unit_Cost": f"{unit_cost:.2f}" if unit_cost > 0 else "0.00",
                            "Tax": tax,
                            "Value": value,
                            "Price_Valid": "✓" if is_valid else "✗",
                            "Calc_Method": calc_method if not is_valid else "",
                            "Expected_Value": f"{calculated_value:.2f}" if not is_valid and calculated_value > 0 else ""
                        }

                        rows.append(row)
                        self.stats['parsed_lines'] += 1

                        if not is_valid:
                            self.stats['price_mismatches'] = self.stats.get('price_mismatches', 0) + 1
                            self.log(f"⚠ Price mismatch: {code} - Expected: {calculated_value:.2f}, Got: {value}", "WARNING")
                        else:
                            # Track successful validations
                            self.stats['price_validations_passed'] = self.stats.get('price_validations_passed', 0) + 1

                        self.log(f"✓ Parsed: {code} - {product[:30]}... [Price check: {'PASS' if is_valid else 'FAIL'}]", "DEBUG") if self.debug else None

                    else:
                        # Try missing RSP pattern (7 fields)
                        missing_rsp_match = missing_rsp_pattern.match(line)
                        if missing_rsp_match:
                            code = missing_rsp_match.group(1).strip()
                            product = missing_rsp_match.group(2).strip()
                            ordered_raw = missing_rsp_match.group(3).strip()
                            delivered_raw = missing_rsp_match.group(4).strip()
                            price = missing_rsp_match.group(5)
                            tax = missing_rsp_match.group(6)
                            value = missing_rsp_match.group(7)
                            rsp = "N/A"  # RSP is missing

                            # Parse quantities
                            ordered_qty = self.parse_quantity(ordered_raw)
                            delivered_qty = self.parse_quantity(delivered_raw)

                            # Extract case size
                            case_size = self.extract_case_size(product)

                            # Calculate total units
                            ordered_units = self.calculate_total_units(ordered_qty, case_size)
                            delivered_units = self.calculate_total_units(delivered_qty, case_size)

                            # Validate price calculation
                            try:
                                price_float = float(price)
                                value_float = float(value)
                                is_valid, calc_method, calculated_value, unit_cost = self.validate_price_calculation(
                                    delivered_qty, case_size, price_float, value_float
                                )
                            except ValueError:
                                is_valid = False
                                calc_method = "Parse error"
                                calculated_value = 0.0
                                unit_cost = 0.0

                            row = {
                                "Filename": filename,
                                "Code": code,
                                "Product": product,
                                "Ordered": ordered_raw,
                                "Qty": delivered_raw,
                                "Ordered_Cases": ordered_qty['cases'],
                                "Ordered_Units": ordered_qty['units'],
                                "Delivered_Cases": delivered_qty['cases'],
                                "Delivered_Units": delivered_qty['units'],
                                "Case_Size": case_size or "",
                                "Total_Ordered_Units": ordered_units,
                                "Total_Delivered_Units": delivered_units,
                                "RSP": rsp,
                                "Price": price,
                                "Unit_Cost": f"{unit_cost:.2f}" if unit_cost > 0 else "0.00",
                                "Tax": tax,
                                "Value": value,
                                "Price_Valid": "✓" if is_valid else "✗",
                                "Calc_Method": calc_method if not is_valid else "",
                                "Expected_Value": f"{calculated_value:.2f}" if not is_valid and calculated_value > 0 else ""
                            }

                            rows.append(row)
                            self.stats['parsed_lines'] += 1
                            self.stats['missing_rsp_lines'] = self.stats.get('missing_rsp_lines', 0) + 1

                            if not is_valid:
                                self.stats['price_mismatches'] = self.stats.get('price_mismatches', 0) + 1
                                self.log(f"⚠ Price mismatch: {code} - Expected: {calculated_value:.2f}, Got: {value}", "WARNING")
                            else:
                                # Track successful validations
                                self.stats['price_validations_passed'] = self.stats.get('price_validations_passed', 0) + 1

                            self.log(f"✓ Parsed (No RSP): {code} - {product[:30]}... [Price check: {'PASS' if is_valid else 'FAIL'}]", "DEBUG") if self.debug else None

                        else:
                            # Check if it's a potential product line
                            confidence = self.assess_confidence(line)

                            # Only track lines with at least LOW confidence
                            if confidence in ["HIGH", "MEDIUM", "LOW"]:
                                # Try relaxed pattern
                                relaxed_match = relaxed_pattern.match(line)
                                if relaxed_match:
                                    confidence = "MEDIUM"  # Upgrade confidence if relaxed pattern matches

                                # Extract product code if available
                                extracted_code = self.extract_product_code(line)

                                miss_entry = {
                                    "filename": filename,
                                    "page": page_num,
                                    "line_on_page": line_on_page,
                                    "code": extracted_code or "",
                                    "text": line,
                                    "confidence": confidence,
                                    "reason": self.analyze_failure_reason(line)
                                }

                                potential_missed_lines.append(miss_entry)
                                self.all_unparsed_lines.append(miss_entry)
                                self.stats['potential_misses'] += 1
                                self.stats[f'{confidence.lower()}_confidence_misses'] += 1

                                # Only log HIGH and MEDIUM confidence misses to reduce noise
                                # Enhanced warning with product code and more context
                                if confidence in ["HIGH", "MEDIUM"]:
                                    code_str = f"Code: {extracted_code}" if extracted_code else "Code: N/A"
                                    # Show more of the line for better context (80 chars instead of 60)
                                    line_preview = line[:80] + "..." if len(line) > 80 else line
                                    self.log(f"⚠ Potential miss [{confidence}] {code_str} - Line: {line_preview}", "WARNING")

        return rows, potential_missed_lines

    def analyze_failure_reason(self, line: str) -> str:
        """Analyze why a line failed to parse"""
        reasons = []

        # Check for missing decimal points
        if not re.search(r'\d+\.\d{2}', line):
            reasons.append("No decimal prices found")

        # Check for quantity format
        if not re.search(r'\d+/?[\d]*', line):
            reasons.append("No quantity pattern found")

        # Check field count
        fields = line.split()
        if len(fields) < 8:
            reasons.append(f"Only {len(fields)} fields (expected 8)")

        # Check for unusual characters
        if re.search(r'[^\w\s\.\-/]', line):
            reasons.append("Contains special characters")

        return "; ".join(reasons) if reasons else "Unknown"

    def write_legacy_csv(self, all_rows: List[Dict], output_base: str):
        """Write legacy format CSV with 8 columns for old system compatibility"""
        legacy_path = f"{output_base}_legacy.csv"

        # Define legacy field order (8 columns - no Filename)
        legacy_fieldnames = ["Code", "Product", "Ordered", "Qty", "RSP", "Price", "Tax", "Value"]

        # Extract only legacy fields from each row
        legacy_rows = []
        for row in all_rows:
            legacy_row = {field: row.get(field, "") for field in legacy_fieldnames}
            legacy_rows.append(legacy_row)

        # Write legacy CSV
        with open(legacy_path, 'w', newline='', encoding='utf-8') as f:
            writer = csv.DictWriter(f, fieldnames=legacy_fieldnames)
            writer.writeheader()
            writer.writerows(legacy_rows)

        print(f"📄 Legacy CSV written to {legacy_path}")

    def generate_report(self, all_rows: List[Dict], output_base: str):
        """Generate comprehensive parsing report"""
        report_lines = []
        report_lines.append("# IIH Invoice Parsing Report (v8)")
        report_lines.append(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")

        # Summary statistics
        report_lines.append("## Summary Statistics")
        report_lines.append(f"- Total lines processed: {self.stats['total_lines']}")
        report_lines.append(f"- Successfully parsed: {self.stats['parsed_lines']}")
        if 'missing_rsp_lines' in self.stats and self.stats['missing_rsp_lines'] > 0:
            report_lines.append(f"- Lines with missing RSP field: {self.stats['missing_rsp_lines']}")
        if 'price_mismatches' in self.stats and self.stats['price_mismatches'] > 0:
            report_lines.append(f"- **Price validation warnings: {self.stats['price_mismatches']}**")
        report_lines.append(f"- Potential missed products: {self.stats['potential_misses']}")
        if self.stats['potential_misses'] > 0:
            report_lines.append(f"  - High confidence: {self.stats['high_confidence_misses']}")
            report_lines.append(f"  - Medium confidence: {self.stats['medium_confidence_misses']}")
            report_lines.append(f"  - Low confidence: {self.stats['low_confidence_misses']}")

        success_rate = (self.stats['parsed_lines'] / self.stats['total_lines'] * 100) if self.stats['total_lines'] > 0 else 0
        report_lines.append(f"- Success rate: {success_rate:.1f}%\n")

        # Files processed
        files_processed = set(row['Filename'] for row in all_rows)
        report_lines.append("## Files Processed")
        for filename in sorted(files_processed):
            file_rows = [r for r in all_rows if r['Filename'] == filename]
            report_lines.append(f"- {filename}: {len(file_rows)} products")

        # Common failure patterns
        if self.all_unparsed_lines:
            report_lines.append("\n## Common Failure Reasons")
            reason_counts = {}
            for line in self.all_unparsed_lines:
                reason = line['reason']
                reason_counts[reason] = reason_counts.get(reason, 0) + 1

            for reason, count in sorted(reason_counts.items(), key=lambda x: x[1], reverse=True)[:5]:
                report_lines.append(f"- {reason}: {count} occurrences")

        # Write report
        report_path = f"{output_base}_report.md"
        with open(report_path, 'w', encoding='utf-8') as f:
            f.write('\n'.join(report_lines))

        print(f"📊 Report saved to {report_path}")

        # Save unparsed lines to CSV with code column
        if self.all_unparsed_lines:
            unparsed_path = f"{output_base}_unparsed.csv"
            with open(unparsed_path, 'w', newline='', encoding='utf-8') as f:
                fieldnames = ['filename', 'page', 'line_on_page', 'code', 'confidence', 'reason', 'text']
                writer = csv.DictWriter(f, fieldnames=fieldnames)
                writer.writeheader()
                writer.writerows(self.all_unparsed_lines)
            print(f"📝 Unparsed lines saved to {unparsed_path}")

        # Save parsing log if debug mode
        if self.debug:
            log_path = f"{output_base}_log.txt"
            with open(log_path, 'w', encoding='utf-8') as f:
                f.write('\n'.join(self.parsing_log))
            print(f"📋 Debug log saved to {log_path}")

def clear_target_directory_pdfs_selective(target_dir: str, preserve_files: List[str]) -> int:
    """
    Delete all PDF files in the target directory except those in the preserve_files list.
    Returns the number of files deleted.
    """
    deleted_count = 0
    if not os.path.exists(target_dir):
        print(f"Target directory does not exist, creating: {target_dir}")
        os.makedirs(target_dir, exist_ok=True)
        return 0

    pdf_pattern = os.path.join(target_dir, "*.pdf")
    existing_pdfs = glob.glob(pdf_pattern)

    for pdf_file in existing_pdfs:
        filename = os.path.basename(pdf_file)
        if filename in preserve_files:
            print(f"🔒 Preserving: {filename}")
            continue

        try:
            os.remove(pdf_file)
            deleted_count += 1
            print(f"🗑️  Deleted: {filename}")
        except Exception as e:
            print(f"❌ Failed to delete {filename}: {e}")

    return deleted_count

def copy_pdfs_to_target(pdf_folder: str, target_dir: str) -> Tuple[int, int]:
    """
    Copy all PDF files from the source folder to the target directory.
    Returns a tuple of (successful_copies, failed_copies).
    """
    success_count = 0
    failure_count = 0

    if not os.path.exists(target_dir):
        os.makedirs(target_dir, exist_ok=True)
        print(f"Created target directory: {target_dir}")

    for fname in os.listdir(pdf_folder):
        if fname.lower().endswith(".pdf"):
            source_path = os.path.join(pdf_folder, fname)
            target_path = os.path.join(target_dir, fname)

            try:
                shutil.copy2(source_path, target_path)
                success_count += 1
                print(f"✅ Copied: {fname}")
            except Exception as e:
                failure_count += 1
                print(f"❌ Failed to copy {fname}: {e}")

    return success_count, failure_count

def move_parsed_pdfs_to_limbo(pdf_files: List[str], target_dir: str) -> Tuple[int, int]:
    """
    Move successfully parsed PDF files to the limbo directory.
    Returns a tuple of (successful_moves, failed_moves).
    """
    success_count = 0
    failure_count = 0

    if not os.path.exists(target_dir):
        os.makedirs(target_dir, exist_ok=True)
        print(f"Created limbo directory: {target_dir}")

    for pdf_file in pdf_files:
        fname = os.path.basename(pdf_file)
        target_path = os.path.join(target_dir, fname)

        try:
            shutil.move(pdf_file, target_path)
            success_count += 1
            print(f"📦 Moved to limbo: {fname}")
        except Exception as e:
            failure_count += 1
            print(f"❌ Failed to move {fname}: {e}")

    return success_count, failure_count

def main():
    parser = argparse.ArgumentParser(description='IIH Invoice Parser v8 - Dual output (new Laravel + legacy system)')
    parser.add_argument('path', nargs='?', default='.', help='Path to PDF file or directory (default: current directory)')
    parser.add_argument('-o', '--output', default='iih8_output', help='Output filename base (default: iih8_output)')
    parser.add_argument('-v', '--verbose', action='store_true', help='Enable verbose output')
    parser.add_argument('-d', '--debug', action='store_true', help='Enable debug mode with detailed logging')

    args = parser.parse_args()

    # Initialize parser
    invoice_parser = IIHInvoiceParser(verbose=args.verbose, debug=args.debug)

    # Get PDF files
    if os.path.isfile(args.path) and args.path.lower().endswith('.pdf'):
        pdf_files = [args.path]
    else:
        directory = args.path if os.path.isdir(args.path) else '.'
        pdf_files = [os.path.join(directory, f) for f in os.listdir(directory) if f.lower().endswith('.pdf')]

    if not pdf_files:
        print("❌ No PDF files found.")
        return

    print(f"🔍 Found {len(pdf_files)} PDF file(s). Processing...")

    all_rows = []
    all_potential_missed = []
    successfully_parsed_pdfs = []  # Track PDFs that had at least one product parsed

    # Process each PDF
    for pdf_file in pdf_files:
        try:
            parsed_rows, potential_missed = invoice_parser.parse_invoice(pdf_file)
            all_rows.extend(parsed_rows)
            all_potential_missed.extend(potential_missed)

            # Track successfully parsed PDFs (those with at least one product)
            if parsed_rows:
                successfully_parsed_pdfs.append(pdf_file)

            filename = os.path.basename(pdf_file)
            if args.verbose:
                print(f"✅ {filename}: {len(parsed_rows)} products parsed")
                # Show price validation status
                valid_prices = len([r for r in parsed_rows if r.get('Price_Valid') == '✓'])
                if valid_prices == len(parsed_rows):
                    print(f"   ✓ Price validation: All {valid_prices} products passed")
                else:
                    invalid_prices = len(parsed_rows) - valid_prices
                    print(f"   ⚠ Price validation: {valid_prices} passed, {invalid_prices} failed")

                if potential_missed:
                    high = len([m for m in potential_missed if m['confidence'] == 'HIGH'])
                    med = len([m for m in potential_missed if m['confidence'] == 'MEDIUM'])
                    if high > 0 or med > 0:
                        print(f"   ⚠ Potential misses: {high} high, {med} medium confidence")
            else:
                print(f"✅ {filename}: {len(parsed_rows)} lines extracted.")

        except Exception as e:
            print(f"❌ Error parsing {pdf_file}: {e}")

    # Display potential missed lines
    if all_potential_missed:
        high_conf = [m for m in all_potential_missed if m['confidence'] == 'HIGH']
        if high_conf:
            print("\n" + "="*60)
            print("⚠️  HIGH CONFIDENCE MISSED PRODUCT LINES (Review Required):")
            print("="*60)
            for item in high_conf[:10]:  # Show first 10
                code_str = f"Code: {item['code']}" if item['code'] else "Code: N/A"
                print(f"  📄 {item['filename']} (Page {item['page']}, Line {item['line_on_page']})")
                print(f"     {code_str} | Confidence: {item['confidence']}")
                print(f"     Reason: {item['reason']}")
                print(f"     Text: \"{item['text'][:100]}...\"" if len(item['text']) > 100 else f"     Text: \"{item['text']}\"")
                print()
            if len(high_conf) > 10:
                print(f"  ... and {len(high_conf) - 10} more high confidence misses")
            print("="*60 + "\n")

    # Save main output CSV
    if all_rows:
        csv_path = f"{args.output}.csv"

        # Define field order
        fieldnames = [
            "Filename", "Code", "Product", "Ordered", "Qty",
            "Ordered_Cases", "Ordered_Units", "Delivered_Cases", "Delivered_Units",
            "Case_Size", "Total_Ordered_Units", "Total_Delivered_Units",
            "RSP", "Price", "Unit_Cost", "Tax", "Value", "Price_Valid", "Calc_Method", "Expected_Value"
        ]

        with open(csv_path, 'w', newline='', encoding='utf-8') as f:
            writer = csv.DictWriter(f, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(all_rows)

        # Display preview
        print(f"\n📋 CSV Preview (first 10 rows of {len(all_rows)} total):\n")
        # Select only key columns for preview
        preview_columns = ["Code", "Product", "Qty", "Case_Size", "Unit_Cost", "Value"]
        preview_rows = [{k: row[k] for k in preview_columns if k in row} for row in all_rows[:10]]
        print(tabulate(preview_rows, headers="keys", tablefmt="grid"))
        print(f"\n✅ Data written to {csv_path}")

        # Write legacy format CSV
        invoice_parser.write_legacy_csv(all_rows, args.output)

    # Generate comprehensive report
    invoice_parser.generate_report(all_rows, args.output)

    # Copy PDFs to target directory
    if all_rows:
        print(f"\n📂 Managing PDF files in: {PDF_COPY_TARGET_DIR}")

        # Determine the source directory for PDFs
        if os.path.isfile(args.path) and args.path.lower().endswith('.pdf'):
            source_directory = os.path.dirname(args.path) if os.path.dirname(args.path) else '.'
        else:
            source_directory = args.path if os.path.isdir(args.path) else '.'

        # Delete existing invoice PDFs in target directory, but preserve the Goods Returns Sheet
        preserve_files = ['Goods Returns Sheet New.pdf']
        deleted_count = clear_target_directory_pdfs_selective(PDF_COPY_TARGET_DIR, preserve_files)
        if deleted_count > 0:
            print(f"🗑️  Deleted {deleted_count} old invoice PDF(s)")

        # Copy processed PDFs to target directory
        success_count, failure_count = copy_pdfs_to_target(source_directory, PDF_COPY_TARGET_DIR)
        if success_count > 0:
            print(f"✅ Copied {success_count} PDF(s) to target directory")
        if failure_count > 0:
            print(f"❌ Failed to copy {failure_count} PDF(s)")

        # Move successfully parsed PDFs to limbo directory
        if successfully_parsed_pdfs:
            print(f"\n📦 Moving {len(successfully_parsed_pdfs)} successfully parsed PDF(s) to: {PDF_MOVE_TARGET_DIR}")
            moved_count, move_failures = move_parsed_pdfs_to_limbo(successfully_parsed_pdfs, PDF_MOVE_TARGET_DIR)
            if moved_count > 0:
                print(f"✅ Moved {moved_count} PDF(s) to limbo directory")
            if move_failures > 0:
                print(f"❌ Failed to move {move_failures} PDF(s)")
        else:
            print(f"\n⚠️  No PDFs to move (no successful parses)")

    # Final summary
    print("\n" + "="*60)
    print("📊 PARSING SUMMARY (v8)")
    print("="*60)
    print(f"Total products parsed: {len(all_rows)}")

    # Show price validation results
    if 'price_validations_passed' in invoice_parser.stats:
        passed = invoice_parser.stats['price_validations_passed']
        total = len(all_rows)
        if passed == total:
            print(f"✅ Price validation: All {total} products passed")
        else:
            failed = total - passed
            print(f"⚠️  Price validation: {passed} passed, {failed} failed")
            print(f"   Check 'Price_Valid' column in CSV for details")

    print(f"Potential missed products: {len(all_potential_missed)}")
    if all_potential_missed:
        high = len([m for m in all_potential_missed if m['confidence'] == 'HIGH'])
        med = len([m for m in all_potential_missed if m['confidence'] == 'MEDIUM'])
        low = len([m for m in all_potential_missed if m['confidence'] == 'LOW'])
        print(f"  - High confidence: {high}")
        print(f"  - Medium confidence: {med}")
        print(f"  - Low confidence: {low}")

    success_rate = (invoice_parser.stats['parsed_lines'] / invoice_parser.stats['total_lines'] * 100) if invoice_parser.stats['total_lines'] > 0 else 0
    print(f"Overall success rate: {success_rate:.1f}%")
    print("\n📦 Output files generated:")
    print(f"  - New Laravel system: {args.output}.csv (20 columns)")
    print(f"  - Legacy system: {args.output}_legacy.csv (8 columns)")
    print("="*60)

if __name__ == "__main__":
    main()
