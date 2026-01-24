# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PDF invoice/order parsing tool that extracts product data from PDF files and exports it to CSV format. The codebase contains multiple iterative implementations for parsing different invoice formats, primarily focused on extracting product information including codes, quantities, prices, and totals.

## Key Scripts and Usage

### Primary Scripts
- `iih9.py` - **Production IIH parser** - Latest IIH invoice parser with comprehensive case/unit quantity parsing, price validation, unit cost calculation, and enhanced reporting. Includes PDF management (copy to Dropbox, move to limbo).
- `udea_cl4.py` - **Production UDEA parser** - Advanced UDEA invoice parser with sophisticated regex patterns, weight/case-based product handling, and validation. Includes PDF copying to Dropbox.
- `tnmc.py` - **Production TNMC parser** - The Natural Medicine Company invoice parser with straightforward product line extraction and validation. Includes PDF copying to Dropbox.
- `udea10gemini_5.py` - Legacy UDEA parser with comprehensive regex patterns, validation, and error handling
- `iih4.py` - Legacy IIH parser with case/unit quantity parsing
- `iih3.py` - Legacy IIH invoice parser with potential missed line detection
- `iih2.py` - Legacy basic IIH invoice parser
- `main2_debug.py` - PDF text extraction debugging tool for analyzing raw PDF content

### Running the Scripts

**Production Parsers:**

**IIH Invoice Parser (iih9.py):**
```bash
source venv/bin/activate  # Activate virtual environment first
python iih9.py [pdf_folder] [-v] [-d] [-o output_base]
```
- `pdf_folder` - Directory containing PDFs (defaults to current directory)
- `-v, --verbose` - Enable verbose console output
- `-d, --debug` - Enable full debug logging to file
- `-o, --output` - Output filename base (defaults to `iih8_output`)

**Outputs:**
- Enhanced CSV: Product data with 20 columns including validation
- Legacy CSV: `*_legacy.csv` with 8 columns for old system
- Unparsed lines CSV: `*_unparsed.csv` with confidence scoring
- Report file: `*_report.md` with parsing statistics
- Debug log: `*_log.txt` (when using -d flag)

**UDEA Invoice Parser (udea_cl4.py):**
```bash
source venv/bin/activate
python udea_cl4.py [pdf_folder] [-v] [-o output.csv]
```
- `pdf_folder` - Directory containing PDFs (defaults to current directory)
- `-v, --verbose` - Enable verbose/debug output
- `-o, --output` - Output CSV filename (defaults to `udea_cl4_output.csv`)

**Outputs:**
- Enhanced CSV: Product data with 12 columns including validation
- Legacy CSV: `*_legacy.csv` with 10 columns for old system

**TNMC Invoice Parser (tnmc.py):**
```bash
source venv/bin/activate
python tnmc.py [pdf_folder] [-v] [-d] [-o output_base]
```
- `pdf_folder` - Directory containing PDFs (defaults to current directory)
- `-v, --verbose` - Enable verbose console output
- `-d, --debug` - Enable full debug logging to file
- `-o, --output` - Output filename base (defaults to `tnmc_output`)

**Outputs:**
- Enhanced CSV: Product data with 12 columns including validation
- Legacy CSV: `*_legacy.csv` with 10 columns for old system
- Report file: `*_report.md` with parsing statistics
- Debug log: `*_log.txt` (when using -d flag)

**Debug PDF text extraction:**
```bash
python main2_debug.py
```
Analyzes the first PDF found and shows raw text extraction line by line.

## Architecture

### Core Components

**PDF Processing Pipeline:**
1. PDF text extraction using `pdfplumber`
2. Section detection (looks for "Underdelivery" or "Products to deliver" headers)
3. Multi-pattern regex matching (NORMAL_REGEX, QUANTITY_SKU_REGEX, FALLBACK_REGEX)
4. Data validation (Qty × Price × SKU = Total)
5. CSV export with standardized fields

**Parser Evolution:**
- `iih2.py`: Basic IIH parser with simple product line extraction
- `iih3.py`: Enhanced with potential missed line detection
- `iih4.py`: **Most advanced IIH parser** with comprehensive case/unit parsing, price validation, and reporting
- `udea10gemini_5.py`: Advanced parser handling multiple invoice formats with sophisticated regex patterns and validation

### Key Patterns

**Regex Architecture in udea10gemini_5.py:**
- Uses shared NUMBER pattern for flexible numeric matching (handles European formatting)
- Three-tier regex matching: NORMAL → QUANTITY_SKU → FALLBACK
- VAT_PROFIT_TOKEN for ignoring tax-related fields
- Comprehensive validation with calculation tolerance (0.015)

**Data Flow:**
```
PDF → Text Extraction → Line-by-Line Processing → Pattern Matching → Validation → CSV Export
```

## Dependencies

The project uses a virtual environment (`venv/`) with these key dependencies:
- `pdfplumber` - PDF text extraction
- `pandas` - Data processing (in some versions)
- `tabulate` - Table formatting for output
- Standard library: `re`, `csv`, `os`, `argparse`, `logging`

## Development Notes

### Working with Different Invoice Formats

**IIH Format (use `iih9.py`):**
- Handles case/unit quantities (e.g., "1/0" = 1 case, "0/2" = 2 units from case)
- Three-tier regex matching: strict → relaxed → fallback patterns
- Comprehensive price validation with unit cost calculation
- Enhanced feedback with confidence scoring for unparsed lines
- Exports detailed reporting and statistics
- **Key Features:**
  - **Quantity Parsing**: Interprets case/unit format (cases/units_from_case)
  - **Price Validation**: Validates calculations with configurable tolerance
  - **Unit Cost Calculation**: Derives accurate unit costs even for non-delivered items
  - **Confidence Scoring**: HIGH/MEDIUM/LOW scoring for potential missed products
  - **PDF Management**: Copies to Dropbox, moves parsed PDFs to limbo directory

**UDEA Format (use `udea_cl4.py`):**
- Complex multi-pattern structure with sophisticated regex patterns
- Handles weight-based products (kg/g) and case-based products
- Three-tier regex matching: NORMAL → QUANTITY_SKU → FALLBACK
- Uses shared NUMBER pattern for European-style number formatting
- Validates Qty × Price × SKU = Total with tolerance
- **Key Features:**
  - **Weight Handling**: Detects and handles weight-based products (kilogram/gram)
  - **SKU Multiplier Logic**: Determines correct SKU multiplier for calculations
  - **Price Corruption Detection**: Fixes corrupted prices in product descriptions
  - **PDF Copying**: Copies processed PDFs to Dropbox target directory

**TNMC Format (use `tnmc.py`):**
- Straightforward product line format with consistent structure
- All products sold by "Each" unit
- Single regex pattern matching for reliable parsing
- Validates Qty × Tr. Price = Total
- **Key Features:**
  - **Simple Structure**: Stock Code, Description, Unit, RRP, Qty, Tr. Price, Disc %, Total, VAT %
  - **Price Validation**: Validates quantity × trade price = total
  - **Multiple VAT Rates**: Handles 0.0%, 13.5%, and 23.0% VAT rates
  - **PDF Copying**: Copies processed PDFs to Dropbox target directory
  - **Comprehensive Reporting**: Statistics and validation results

### Debugging Approach

**For IIH invoices (using iih9.py):**
1. Run with `-v` flag to see HIGH/MEDIUM confidence unparsed lines
2. Run with `-d` flag to generate comprehensive debug logs
3. Review `*_unparsed.csv` for potential missed products
4. Check `*_report.md` for parsing statistics and failure analysis
5. Verify price validation results in console output
6. Examine unit cost calculations for accuracy

**For UDEA invoices (using udea_cl4.py):**
1. Run with `-v` flag for detailed debug output
2. Check console warnings for potential product lines that couldn't be parsed
3. Verify validation status in the enhanced CSV (Validation_Status column)
4. Look for price mismatch warnings in console output

**For TNMC invoices (using tnmc.py):**
1. Run with `-v` flag for verbose output showing validation results
2. Run with `-d` flag to generate debug logs
3. Check `*_report.md` for parsing statistics
4. Verify price validation status (all should pass for TNMC format)
5. Look for unmatched line warnings in console output

**General debugging:**
1. Use `main2_debug.py` to examine raw PDF text extraction
2. Check section headers are properly detected (for IIH/UDEA formats)
3. Validate regex patterns against actual line formats
4. Monitor validation warnings for calculation mismatches

### File Structure
- Main scripts in root directory
- `old/` - Legacy/experimental versions
- `temp/` - Development testing files
- `venv/` - Python virtual environment
- **Output files:**
  - **IIH (iih9.py):** `iih8_output.csv`, `iih8_output_legacy.csv`, `iih8_output_unparsed.csv`, `iih8_output_report.md`, `iih8_output_log.txt`
  - **UDEA (udea_cl4.py):** `udea_cl4_output.csv`, `udea_cl4_output_legacy.csv`
  - **TNMC (tnmc.py):** `tnmc_output.csv`, `tnmc_output_legacy.csv`, `tnmc_output_report.md`, `tnmc_output_log.txt`
- **PDF Copy Targets:**
  - IIH: `/home/jon/Dropbox/Daily/invoices/Print/independent`
  - UDEA: `/home/jon/Dropbox/Daily/invoices/Print/Udea`
  - TNMC: `/home/jon/Dropbox/Daily/invoices/Print/TNMC`