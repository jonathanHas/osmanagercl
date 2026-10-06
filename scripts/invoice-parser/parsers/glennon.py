import re
import sys

# Comma-aware, as a money pattern that cannot span a thousands separator is how the Klee
# Paper parser silently truncated a €1,572.82 invoice (see docs/development/known-issues.md).
MONEY = r'-?[\d,]+\.\d{2}'

# The header values sit on the line under their labels:
#   INVOICE NO. INVOICE DATE A/C NO. YOUR REF ORDER NO. OPERATOR
#   186536 14/09/2026 O015 1 87532 KEALIN
HEADER_RE = re.compile(r'INVOICE NO\..*?INVOICE DATE.*?\n\s*(\d+)\s+(\d{2}/\d{2}/\d{4})')

# VAT analysis rows, one per rate: "[<vat code>] <rate>% <goods> <vat>". Only rows in use
# carry the code; the unused ones print as "0.00% 0.00 0.00". The payment-method columns
# (Cash, Cheque, ...) follow on the same line and are ignored.
VAT_ROW_RE = re.compile(r'^(?:\d+\s+)?(\d+(?:\.\d+)?)%\s+(' + MONEY + r')\s+(' + MONEY + r')',
                        re.MULTILINE)

TOTAL_GOODS_RE = re.compile(r'Total Goods:\s*(' + MONEY + r')')
TOTAL_VAT_RE = re.compile(r'Total VAT:\s*(' + MONEY + r')')
DEPOSIT_RE = re.compile(r'Deposit Fee:\s*(' + MONEY + r')')
INVOICE_TOTAL_RE = re.compile(r'Invoice Total\s*(' + MONEY + r')')

# Item rows end "<ordered> <supplied> <price> <value> <vat code> <SRP>", with quantities
# as cases/units ("3/0"). The SRP's euro sign comes out of pdfplumber as "(cid:128)".
# Long descriptions wrap above and below the row, which is why only the tail is matched.
LINE_ITEM_RE = re.compile(
    r'(\d+)/(\d+)\s+(\d+)/(\d+)\s+(' + MONEY + r')\s+(' + MONEY + r')\s+(\d+)\s+\S*?'
    + MONEY + r'\s*$',
    re.MULTILINE
)

RATE_KEYS = {0.0: '0', 9.0: '9', 13.5: '13.5', 23.0: '23'}


def _amount(raw):
    """Convert '1,234.56' into a float."""
    return float(raw.replace(',', '').strip())


def _parse_vat_rows(text, warnings):
    """Net and VAT per rate from the VAT analysis block."""
    nets = {key: 0.0 for key in RATE_KEYS.values()}
    vats = {key: 0.0 for key in RATE_KEYS.values()}
    for rate_raw, net_raw, vat_raw in VAT_ROW_RE.findall(text):
        key = RATE_KEYS.get(float(rate_raw))
        net, vat = _amount(net_raw), _amount(vat_raw)
        if key is None:
            if net or vat:
                warnings.append(f"Unrecognised VAT rate {rate_raw}% on €{net:.2f} of goods")
            continue
        nets[key] = round(nets[key] + net, 2)
        vats[key] = round(vats[key] + vat, 2)
    return nets, vats


def _line_items_total(text):
    total = 0.0
    rows = 0
    for match in LINE_ITEM_RE.finditer(text):
        total += _amount(match.group(6))
        rows += 1
    return round(total, 2), rows


def parse_invoice(text, filename):
    """
    Parser for Sean Glennon & Sons cash & carry invoices (Drumbane, Birr).

    The VAT analysis block at the foot gives goods and VAT per rate and is authoritative.
    Total Goods, Total VAT, the line-item values and the Invoice Total are cross-checks.
    A non-zero Deposit Fee (Re-Turn deposit) is outside VAT, so it is reported as a
    warning rather than folded into a VAT bucket.
    """
    print(f"[DEBUG] Parsing Sean Glennon invoice: {filename}", file=sys.stderr)

    try:
        warnings = []

        header = HEADER_RE.search(text)
        invoice_number = header.group(1) if header else 'Not found'
        invoice_date = header.group(2) if header else 'Not found'
        print(f"[DEBUG] Invoice {invoice_number} dated {invoice_date}", file=sys.stderr)

        nets, vats = _parse_vat_rows(text, warnings)
        net_sum = round(sum(nets.values()), 2)
        vat_sum = round(sum(vats.values()), 2)
        if not net_sum:
            warnings.append('VAT analysis block not found or empty')

        goods_match = TOTAL_GOODS_RE.search(text)
        if goods_match and abs(_amount(goods_match.group(1)) - net_sum) > 0.01:
            warnings.append(
                f"VAT analysis goods €{net_sum:.2f} do not match Total Goods "
                f"€{_amount(goods_match.group(1)):.2f}"
            )

        vat_match = TOTAL_VAT_RE.search(text)
        if vat_match and abs(_amount(vat_match.group(1)) - vat_sum) > 0.01:
            warnings.append(
                f"VAT analysis VAT €{vat_sum:.2f} does not match Total VAT "
                f"€{_amount(vat_match.group(1)):.2f}"
            )

        lines_total, rows = _line_items_total(text)
        print(f"[DEBUG] {rows} line items totalling {lines_total}", file=sys.stderr)
        if rows and abs(lines_total - net_sum) > 0.01:
            warnings.append(
                f"Line items total €{lines_total:.2f} but the VAT analysis states "
                f"€{net_sum:.2f} of goods"
            )

        deposit_match = DEPOSIT_RE.search(text)
        deposit = _amount(deposit_match.group(1)) if deposit_match else 0.0
        if deposit:
            warnings.append(f"Deposit Fee of €{deposit:.2f} is included in the invoice total")

        total_match = INVOICE_TOTAL_RE.search(text)
        if total_match:
            total_amount = _amount(total_match.group(1))
            if abs(net_sum + vat_sum + deposit - total_amount) > 0.01:
                warnings.append(
                    f"Goods €{net_sum:.2f} plus VAT €{vat_sum:.2f} plus deposit "
                    f"€{deposit:.2f} does not reach the invoice total €{total_amount:.2f}"
                )
        else:
            warnings.append('Invoice Total not found; using goods plus VAT plus deposit')
            total_amount = round(net_sum + vat_sum + deposit, 2)
        print(f"[DEBUG] Net {net_sum} | VAT {vat_sum} | Total {total_amount}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            # Must match accounting_suppliers exactly (id 38)
            'Supplier': 'Sean Glennon & Sons',
            'Invoice Number': invoice_number,
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': total_amount < 0 or 'CREDIT NOTE' in text.upper(),
            'VAT 0%': f"{nets['0']:.2f}",
            'VAT 9%': f"{nets['9']:.2f}",
            'VAT 13.5%': f"{nets['13.5']:.2f}",
            'VAT 23%': f"{nets['23']:.2f}",
            'VAT Amounts': {rate: vats[rate] for rate in ('0', '9', '13.5', '23')},
            'Total': f"{total_amount:.2f}",
            'Total_VAT': vat_sum,
        }
        if warnings:
            parsed_data['Parse_Warnings'] = warnings

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        raise
