import re
import sys

def parse_invoice(text, filename):
    # Debug output to stderr only
    print(f"[DEBUG] Parsing BreaDelicious invoice: {filename}", file=sys.stderr)

    try:
        # === Date ===
        date_match = re.search(r"Issue date:\s*(\d{4}-\d{2}-\d{2})", text)
        invoice_date = date_match.group(1) if date_match else "Not found"
        if invoice_date != "Not found":
            parts = invoice_date.split("-")
            invoice_date = f"{parts[2]}/{parts[1]}/{parts[0]}"
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        is_credit_note = False
        tax_free = False

        vat_totals = {
            "0": 0.0,
            "9": 0.0,
            "13.5": 0.0,
            "23": 0.0
        }

        # === Match only VAT summary-style lines (Net → Rate → VAT → Gross) ===
        vat_summary_matches = re.findall(
            r'(?<!\d)(\d{1,6}[.,]\d{2})\s+(0|9|13\.5|23)\s+(\d{1,6}[.,]\d{2})\s+(\d{1,6}[.,]\d{2})',
            text
        )

        print(f"[DEBUG] VAT Summary Matches: {vat_summary_matches}", file=sys.stderr)

        for net, rate, vat_amt, gross in vat_summary_matches:
            try:
                net_clean = float(net.replace(",", ""))
                vat_totals[rate] = net_clean
            except Exception as e:
                print(f"[DEBUG] Skipping malformed VAT line: {net}, {rate} — {e}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'BreaDelicious',
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': is_credit_note,
            'VAT 0%': f"{vat_totals['0']:.2f}",
            'VAT 9%': f"{vat_totals['9']:.2f}",
            'VAT 13.5%': f"{vat_totals['13.5']:.2f}",
            'VAT 23%': f"{vat_totals['23']:.2f}"
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
