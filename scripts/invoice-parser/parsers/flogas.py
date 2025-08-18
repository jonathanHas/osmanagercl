import re

def parse_invoice(text, filename):
    lines = text.splitlines()
    data = {
        'Filename': filename,
        'Supplier': 'Flo Gas',
        'Invoice Date': '',
        'Tax Free': '',
        'Credit Note': '',
        'VAT 0%': '',
        'VAT 9%': '',
        'VAT 13.5%': '',
        'VAT 23%': '',
    }

    vat_amount = None  # Track VAT explicitly

    for line in lines:
        if "Date of issue" in line:
            date_match = re.search(r'(\d{2}/\d{2}/\d{2,4}|\d{4}-\d{2}-\d{2})', line)
            if date_match:
                raw_date = date_match.group(1)
                if "-" in raw_date:
                    y, m, d = raw_date.split("-")
                else:
                    d, m, y = raw_date.split("/")
                    if len(y) == 2:
                        y = '20' + y
                data['Invoice Date'] = f"{d}/{m}/{y}"

        if "Net Bill for this period" in line:
            net_match = re.search(r'(-?\d[\d,]*\.\d{2})', line)
            if net_match:
                amount = net_match.group(1).replace(",", "")
                data['Tax Free'] = amount

        if "VAT (R) 9.00%" in line:
            vat_values = re.findall(r'-?\d[\d,]*\.\d{2}', line)
            if len(vat_values) >= 2:
                vat_amount = vat_values[1].replace(",", "")
                data['VAT 9%'] = vat_amount

    # If no VAT recorded and the invoice is negative, assume 0% VAT
    if vat_amount in [None, '', '0.00']:
        try:
            if float(data['Tax Free']) < 0:
                data['VAT 0%'] = data['Tax Free']
        except ValueError:
            pass

    try:
        if float(data['Tax Free']) < 0:
            data['Credit Note'] = 'YES'
    except ValueError:
        pass

    return data
