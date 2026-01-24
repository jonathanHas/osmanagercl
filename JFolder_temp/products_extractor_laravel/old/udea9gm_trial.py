import os
import re
import csv
import argparse
import logging
from typing import List, Dict, Optional, Tuple, Pattern, Final
import pdfplumber

# ——— Configuration ———
DIAGNOSTIC: Final[bool] = True  # Toggle detailed per-line diagnostics
TOKEN_THRESHOLD: Final[int] = 13  # Tokens count threshold for forced diagnostics

# ——— Shared number sub-pattern: European style ———
#   '.' as thousands sep, ',' as decimal sep
NUMBER: Final[str] = r"\d{1,3}(?:\.\d{3})*(?:,\d{2})?"

# Regex patterns for strict parsing
NORMAL_REGEX: Final[Pattern[str]] = re.compile(rf"""
    ^
    (\d+)\s+                        # 1) Code
    (\d+)\s+                        # 2) Ordered
    (\d+)\s+                        # 3) Qty
    (\d+)\s+                        # 4) SKU
    (\S+)\s+                        # 5) Unit
    (.+?)\s+                        # 6) Description
    ({NUMBER})\s+                   # 7) Price
    ({NUMBER})\s+                   # 8) Sale
    (?:\d+\s+\d+%?\s+)           #    VAT/profit tokens
    (\d{1,3}(?:\.\d{3})*,\d{2})   # 9) Total
    $
""", re.VERBOSE)

QUANTITY_SKU_REGEX: Final[Pattern[str]] = re.compile(rf"""
    ^
    (\d+)\s+                        # 1) Code
    (\d+)\s+                        # 2) Ordered
    (\d+)\s+                        # 3) Qty
    ({NUMBER})\s+                   # 4) ActualQty
    (\d+)\s+                        # 5) SKU
    (\S+)\s+                        # 6) Unit
    (.+?)\s+                        # 7) Description
    ({NUMBER})\s+                   # 8) Price
    ({NUMBER})\s+                   # 9) Sale
    (?:\d+\s+\d+%?\s+)           #    VAT/profit tokens
    (\d{1,3}(?:\.\d{3})*,\d{2})   # 10) Total
    $
""", re.VERBOSE)

FALLBACK_REGEX: Final[Pattern[str]] = re.compile(rf"""
    ^
    (\d+)\s+                        # 1) Code
    (\d+)\s+                        # 2) Ordered
    (\d+)\s+                        # 3) Qty
    (\S+)\s+                        # 4) raw_sku_content
    (.+?)\s+                        # 5) Description
    ({NUMBER})\s+                   # 6) Price
    ({NUMBER})\s+                   # 7) Sale
    (?:\d+\s+\d+%?\s+)           #    VAT/profit tokens
    (\d{1,3}(?:\.\d{3})*,\d{2})   # 8) Total
    $
""", re.VERBOSE)

# Utility regexes\HEADER_REGEX: Final[Pattern[str]] = re.compile(r"^(\d+)\s+(\d+)\s+(\d+)")
LINE_STARTS_WITH_CODE_REGEX: Final[Pattern[str]] = re.compile(r'^\d+')
SECTION_HEADERS: Final[Tuple[str, ...]] = ("Underdelivery", "Products to deliver")
CSV_FIELDNAMES: Final[List[str]] = [
    "Code", "Ordered", "Qty", "SKU", "Content", "Description",
    "Price", "Sale", "Total", "SourcePDF"
]

logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')


def _clean_number_string(num_str: str) -> str:
    """
    Convert European-style number
    (e.g. '1.234,56' or '2,810') to standard
    Python float string ('1234.56' or '2.810').
    """
    s = num_str.strip()
    s = s.replace('.', '')
    s = s.replace(',', '.')
    return s


def _diagnose_line(line: str) -> None:
    """
    Log header match, token list, and full-match status for each regex.
    """
    logging.info(f"─── Diagnosing: {line}")
    h = HEADER_REGEX.match(line)
    if h:
        logging.info(f" header OK: code={h.group(1)}, ordered={h.group(2)}, qty={h.group(3)}")
    else:
        logging.info(" header FAILED: cannot extract code/ordered/qty")
    toks = line.split()
    logging.info(f" tokens ({len(toks)}): {toks}")
    for name, pat in [
        ("NORMAL", NORMAL_REGEX),
        ("QUANTITY_SKU", QUANTITY_SKU_REGEX),
        ("FALLBACK", FALLBACK_REGEX)
    ]:
        m = pat.match(line)
        if m:
            logging.info(f" {name} FULL MATCH -> groups={m.groups()}")
        else:
            logging.info(f" {name} NO MATCH")
    logging.info("────────────────────────────────")


def _parse_columnar(line: str) -> Optional[Dict[str, str]]:
    """
    Columnar fallback: peel off fixed head/tail cols, parse middle loosely.
    """
    toks = line.split()
    if len(toks) < 7:
        return None
    if not re.match(r"^\d{1,3}(?:\.\d{3})*,\d{2}$", toks[-1]):
        return None
    total = toks.pop()
    if not re.match(r"^\d+%$", toks[-1]):
        return None
    profit = toks.pop()
    vat = ''
    if re.match(r"^\d+$", toks[-1]):
        vat = toks.pop()
    if not re.match(rf"^{NUMBER}$", toks[-1]):
        return None
    sale = toks.pop()
    if not re.match(rf"^\d{{1,3}}(?:\.\d{{3}})*,\d{{2}}$", toks[-1]):
        return None
    price = toks.pop()
    if len(toks) < 4:
        return None
    code, ordered, qty = toks[0], toks[1], toks[2]
    middle = toks[3:]
    sku = middle[0]
    unit = middle[1] if len(middle) >= 2 else ''
    desc = ' '.join(middle[2:]) if len(middle) >= 3 else ''
    logging.warning(f"Using columnar fallback for code={code}: tokens={len(line.split())}")
    return {
        'Code': code,
        'Ordered': ordered,
        'Qty': qty,
        'SKU': _clean_number_string(sku),
        'Content': unit,
        'Description': desc,
        'Price': _clean_number_string(price),
        'Sale': _clean_number_string(sale),
        'Total': _clean_number_string(total),
    }


def _parse_line(line: str) -> Optional[Dict[str, str]]:
    """Try strict patterns, then fallback regex, then columnar."""
    m = NORMAL_REGEX.match(line)
    if m:
        code, ordered, qty, sku, unit, desc, pr, sa, to = m.groups()
        content = f"{qty} {unit}"
        description = desc.strip()
        return {
            'Code': code,
            'Ordered': ordered,
            'Qty': qty,
            'SKU': sku,
            'Content': content,
            'Description': description,
            'Price': _clean_number_string(pr),
            'Sale': _clean_number_string(sa),
            'Total': _clean_number_string(to)
        }
    m = QUANTITY_SKU_REGEX.match(line)
    if m:
        code, ordered, qty, aq, sku, unit, desc, pr, sa, to = m.groups()
        content = f"{_clean_number_string(aq)} {unit}"
        return {
            'Code': code,
            'Ordered': ordered,
            'Qty': qty,
            'SKU': sku,
            'Content': content,
            'Description': desc,
            'Price': _clean_number_string(pr),
            'Sale': _clean_number_string(sa),
            'Total': _clean_number_string(to)
        }
    m = FALLBACK_REGEX.match(line)
    if m:
        code, ordered, qty, raw, desc, pr, sa, to = m.groups()
        sku, content = raw, ''
        if ',' in raw and raw.split(',', 1)[0].isdigit():
            sku, content = raw.split(',', 1)
        return {
            'Code': code,
            'Ordered': ordered,
            'Qty': qty,
            'SKU': _clean_number_string(sku),
            'Content': content,
            'Description': desc,
            'Price': _clean_number_string(pr),
            'Sale': _clean_number_string(sa),
            'Total': _clean_number_string(to)
        }
    return _parse_columnar(line)


def extract_products_from_pdf(pdf_path: str) -> List[Dict[str, str]]:
    products: List[Dict[str, str]] = []
    capture = False
    fname = os.path.basename(pdf_path)

    try:
        with pdfplumber.open(pdf_path) as pdf:
            logging.info(f"Processing PDF: {fname} ({len(pdf.pages)} pages)")
            for pnum, page in enumerate(pdf.pages, start=1):
                text = page.extract_text(x_tolerance=2, y_tolerance=2)
                if not text:
                    logging.warning(f"Page {pnum} has no extractable text.")
                for lnum, raw in enumerate(text.split("\n"), start=1):
                    line = raw.strip()
                    if any(h in raw for h in SECTION_HEADERS):
                        capture = True
                        continue
                    if not capture or not line:
                        continue
                    parsed = _parse_line(line)
                    if parsed:
                        parsed['SourcePDF'] = fname
                        products.append(parsed)
                    elif LINE_STARTS_WITH_CODE_REGEX.match(line):
                        toks = line.split()
                        tc = len(toks)
                        if tc >= TOKEN_THRESHOLD:
                            logging.warning(f"P{pnum} L{lnum}: high token count ({tc}), running diagnostics")
                            if DIAGNOSTIC:
                                _diagnose_line(line)
                        else:
                            logging.warning(f"P{pnum} L{lnum}: no pattern matched")
    except Exception as e:
        logging.error(f"❌ Failed to process {fname}: {e}", exc_info=True)
        return []

    logging.info(f"Found {len(products)} products in {fname}.")
    return products


def parse_all_pdfs_to_csv(pdf_folder: str, output_csv_path: str) -> None:
    all_products: List[Dict[str, str]] = []
    logging.info(f"Scanning folder: {pdf_folder}")
    pdf_count = 0
    for fn in os.listdir(pdf_folder):
        if fn.lower().endswith('.pdf'):
            pdf_count += 1
            all_products.extend(extract_products_from_pdf(os.path.join(pdf_folder, fn)))

    if pdf_count == 0:
        logging.warning("No PDF files found.")
        return
    if not all_products:
        logging.warning("⚠️ No products extracted.")
        return

    logging.info(f"Writing {len(all_products)} products to {output_csv_path}")
    with open(output_csv_path, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=CSV_FIELDNAMES, extrasaction='ignore')
        writer.writeheader()
        writer.writerows(all_products)
    logging.info(f"✅ CSV written: {output_csv_path}")


def main():
    parser = argparse.ArgumentParser(description="PDF → CSV parser with diagnostics and content fix.")
    parser.add_argument('pdf_folder', nargs='?', default=os.getcwd(), help='Folder containing PDFs')
    parser.add_argument('-o', '--output', dest='output_csv', default='udea_output.csv', help='Output CSV path')
    parser.add_argument('-v', '--verbose', action='store_const', dest='loglevel', const=logging.DEBUG, default=logging.INFO, help='Enable debug')

    args = parser.parse_args()
    logging.getLogger().setLevel(args.loglevel)

    if not os.path.isdir(args.pdf_folder):
        logging.error(f"❌ Input folder not found: {args.pdf_folder}")
        return

    output_path = os.path.join(os.path.dirname(__file__) if '__file__' in locals() else os.getcwd(), args.output_csv)
    parse_all_pdfs_to_csv(args.pdf_folder, output_path)

if __name__ == '__main__':
    main()
