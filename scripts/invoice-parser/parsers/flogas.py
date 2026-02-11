import re
import sys


def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Flo Gas invoice: {filename}", file=sys.stderr)

    try:
        vat_0 = 0.0
        vat_9 = 0.0
        vat_135 = 0.0
        vat_23 = 0.0
        net_bill = None
        invoice_date = "Not found"
        invoice_number = None
        is_credit_note = False

        lines = text.splitlines()

        # === Invoice Date ===
        for line in lines:
            if re.search(r'Date of issue', line, re.IGNORECASE):
                date_match = re.search(r'(\d{2}/\d{2}/\d{2,4}|\d{4}-\d{2}-\d{2})', line)
                if date_match:
                    raw_date = date_match.group(1)
                    if "-" in raw_date:
                        y, m, d = raw_date.split("-")
                    else:
                        d, m, y = raw_date.split("/")
                        if len(y) == 2:
                            y = '20' + y
                    invoice_date = f"{d}/{m}/{y}"
                    break

        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === Invoice Number ===
        for line in lines:
            inv_match = re.search(r'Invoice Number[:\s]*(\S+)', line, re.IGNORECASE)
            if inv_match:
                invoice_number = inv_match.group(1)
                break

        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        # === Net Bill for this period ===
        for line in lines:
            if "Net Bill for this period" in line:
                net_match = re.search(r'(-?\d[\d,]*\.\d{2})', line)
                if net_match:
                    net_bill = float(net_match.group(1).replace(",", ""))
                    break

        if net_bill is None:
            for line in lines:
                if re.search(r'Net total', line, re.IGNORECASE):
                    net_match = re.search(r'(-?\d[\d,]*\.\d{2})', line)
                    if net_match:
                        net_bill = float(net_match.group(1).replace(",", ""))
                        break

        print(f"[DEBUG] Net Bill: {net_bill}", file=sys.stderr)

        # === VAT Breakdown ===
        # Strategy 1: pdfplumber format — "VAT (R) 9.00% on <net_amount> <vat_charge>"
        # These lines already include everything (export credits are in VAT (Z))
        pdfplumber_vat_found = False
        for line in lines:
            m = re.search(r'VAT\s*\(R\)\s*9\.00%\s+on\s+(-?\d[\d,]*\.\d{2})', line)
            if m:
                vat_9 += float(m.group(1).replace(",", ""))
                pdfplumber_vat_found = True
                print(f"[DEBUG] VAT 9% (pdfplumber R): {m.group(1)}", file=sys.stderr)

            m = re.search(r'VAT\s*\(Z\)\s*0\.00%\s+on\s+(-?\d[\d,]*\.\d{2})', line)
            if m:
                vat_0 += float(m.group(1).replace(",", ""))
                pdfplumber_vat_found = True
                print(f"[DEBUG] VAT 0% (pdfplumber Z): {m.group(1)}", file=sys.stderr)

        # Strategy 2: OCR format — "VAT 9% <vat_charge>"
        ocr_vat_rates_found = []
        if not pdfplumber_vat_found:
            for line in lines:
                m = re.search(r'^VAT\s+9%\s+(-?\d[\d,]*\.\d{2})', line.strip())
                if m:
                    ocr_vat_rates_found.append(('9', float(m.group(1).replace(",", ""))))
                    print(f"[DEBUG] VAT 9% (OCR): charge={m.group(1)}", file=sys.stderr)

                m = re.search(r'^VAT\s+23%\s+(-?\d[\d,]*\.\d{2})', line.strip())
                if m:
                    ocr_vat_rates_found.append(('23', float(m.group(1).replace(",", ""))))
                    print(f"[DEBUG] VAT 23% (OCR): charge={m.group(1)}", file=sys.stderr)

            if len(ocr_vat_rates_found) == 1 and net_bill is not None:
                # Single VAT rate: use net_bill directly (avoids back-calculation rounding)
                rate = ocr_vat_rates_found[0][0]
                if rate == '9':
                    vat_9 = net_bill
                elif rate == '23':
                    vat_23 = net_bill
                print(f"[DEBUG] Single OCR rate {rate}%: using net_bill={net_bill}", file=sys.stderr)
            elif len(ocr_vat_rates_found) > 1:
                # Multiple rates: back-calculate net from VAT charges
                for rate, charge in ocr_vat_rates_found:
                    if rate == '9':
                        vat_9 += round(charge / 0.09, 2)
                    elif rate == '23':
                        vat_23 += round(charge / 0.23, 2)

        vat_found = pdfplumber_vat_found or len(ocr_vat_rates_found) > 0

        # Strategy 3: Fallback — if we have net bill but no VAT breakdown, assume all 9%
        if not vat_found and net_bill is not None and net_bill > 0:
            vat_9 = net_bill
            print(f"[DEBUG] Fallback: all net to VAT 9%: {net_bill}", file=sys.stderr)

        # === Export Credits (0% VAT) — only for OCR format ===
        # In pdfplumber format, export credits are already included in VAT (Z) 0.00%
        if not pdfplumber_vat_found:
            for line in lines:
                if re.search(r'Export [Cc]redit', line):
                    # Try €amount first (e.g., "Export Credit €-55.86")
                    euro_match = re.search(r'€(-?\d[\d,]*\.\d{2})\s', line)
                    if euro_match:
                        export_val = float(euro_match.group(1).replace(",", ""))
                        vat_0 += export_val
                        print(f"[DEBUG] Export Credit (€): {export_val}", file=sys.stderr)
                    else:
                        # Take the last decimal number (e.g., "Export credit -8.16 0.185000 0% €/kWh -1.51")
                        all_nums = re.findall(r'-?\d[\d,]*\.\d{2}', line)
                        if all_nums:
                            export_val = float(all_nums[-1].replace(",", ""))
                            vat_0 += export_val
                            print(f"[DEBUG] Export Credit (last num): {export_val}", file=sys.stderr)
                    break

        # === Credit Note Detection ===
        if net_bill is not None and net_bill < 0:
            is_credit_note = True
        if invoice_number and invoice_number.startswith('CN'):
            is_credit_note = True

        # For credit notes where no VAT breakdown was found, determine rate from Total VAT
        if is_credit_note and not vat_found and net_bill is not None:
            for line in lines:
                vat_match = re.search(r'Total VAT\s+€?(-?\d[\d,]*\.\d{2})', line)
                if vat_match:
                    total_vat = float(vat_match.group(1).replace(",", ""))
                    if abs(total_vat) > 0.01:
                        vat_9 = net_bill
                    else:
                        vat_0 = net_bill
                    break
            else:
                if 'VAT (Z)' in text or '0.00%' in text:
                    vat_0 = net_bill
                else:
                    vat_9 = net_bill

        print(f"[DEBUG] Credit Note: {is_credit_note}", file=sys.stderr)
        print(f"[DEBUG] VAT 0%={vat_0}, 9%={vat_9}, 13.5%={vat_135}, 23%={vat_23}", file=sys.stderr)

        parsed_data = {
            'Filename': filename,
            'Supplier': 'Flo Gas',
            'Invoice Date': invoice_date,
            'Tax Free': False,
            'Credit Note': True if is_credit_note else False,
            'VAT 0%': f"{vat_0:.2f}",
            'VAT 9%': f"{vat_9:.2f}",
            'VAT 13.5%': f"{vat_135:.2f}",
            'VAT 23%': f"{vat_23:.2f}",
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise
