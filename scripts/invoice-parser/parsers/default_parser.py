def parse_invoice(text, filename):
    return {
        'Filename': filename,
        'Supplier': 'Unknown',
        'Total': 'Not found',
        'Invoice Date': 'Not found',
        'Tax Free': False,
        'Credit Note': False
    }
