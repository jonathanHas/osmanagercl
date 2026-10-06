"""
Regression tests for the Sean Glennon & Sons parser.

Glennon's cash & carry invoices end with a VAT analysis block ("<code> <rate>% <goods> <vat>")
that is the authoritative source; Total Goods, Total VAT, the line values and the Invoice
Total cross-check it. The fixture is real pdfplumber text from INV-186536.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import glennon  # noqa: E402
from invoice_parser_laravel import detect_supplier, format_parsed_invoice  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'glennon')
FIXTURE = '2026-09-14_186536.txt'


def fixture_text():
    with open(os.path.join(FIXTURE_DIR, FIXTURE), encoding='utf-8') as fh:
        return fh.read()


@pytest.fixture(scope='module')
def invoice():
    """No. 186536, 2026-09-14. Seven item lines, all at 23%."""
    return glennon.parse_invoice(fixture_text(), FIXTURE)


def test_header(invoice):
    assert invoice['Supplier'] == 'Sean Glennon & Sons'
    assert invoice['Invoice Number'] == '186536'
    assert invoice['Invoice Date'] == '14/09/2026'
    assert invoice['Credit Note'] is False
    assert invoice['Tax Free'] is False


def test_net_all_at_23(invoice):
    assert invoice['VAT 23%'] == '154.25'
    assert invoice['VAT 0%'] == '0.00'
    assert invoice['VAT 9%'] == '0.00'
    assert invoice['VAT 13.5%'] == '0.00'


def test_vat_and_total(invoice):
    assert invoice['VAT Amounts']['23'] == 35.48
    assert invoice['Total_VAT'] == 35.48
    assert invoice['Total'] == '189.73'


def test_everything_reconciles_without_warnings(invoice):
    # Includes the line values, two of which have descriptions wrapped around the row
    assert 'Parse_Warnings' not in invoice


def test_laravel_output_is_clean(invoice):
    formatted, has_anomalies, warnings = format_parsed_invoice(invoice, FIXTURE)
    assert formatted['invoice_date'] == '2026-09-14'
    assert formatted['total_amount'] == 189.73
    assert has_anomalies is False, warnings


def test_detected_from_letterhead():
    parser, name = detect_supplier(fixture_text())
    assert parser is glennon
    assert name == 'Sean Glennon & Sons'


def test_line_items_found():
    total, rows = glennon._line_items_total(fixture_text())
    assert rows == 7
    assert total == 154.25


def test_mixed_rates_and_thousands_separator():
    text = fixture_text().replace(
        '0.00% 0.00 0.00 Cheque 0.00', '1 13.50% 1,000.00 135.00 Cheque 0.00'
    )
    text = text.replace('Total Goods: 154.25', 'Total Goods: 1,154.25')
    text = text.replace('Total VAT: 35.48', 'Total VAT: 170.48')
    text = text.replace('Invoice Total 189.73', 'Invoice Total 1,324.73')
    result = glennon.parse_invoice(text, 'synthetic')
    assert result['VAT 13.5%'] == '1000.00'
    assert result['VAT 23%'] == '154.25'
    assert result['Total_VAT'] == 170.48
    assert result['Total'] == '1324.73'
    # Only the line items fall short, as no 13.5% line was added
    assert all('Line items total' in w for w in result['Parse_Warnings'])


def test_deposit_fee_is_flagged():
    text = fixture_text().replace('Deposit Fee: 0.00', 'Deposit Fee: 2.40')
    text = text.replace('Invoice Total 189.73', 'Invoice Total 192.13')
    result = glennon.parse_invoice(text, 'synthetic')
    assert result['VAT 23%'] == '154.25'
    assert result['Total'] == '192.13'
    assert result['Parse_Warnings'] == ['Deposit Fee of €2.40 is included in the invoice total']


def test_mismatched_total_is_flagged():
    text = fixture_text().replace('Invoice Total 189.73', 'Invoice Total 199.73')
    result = glennon.parse_invoice(text, 'synthetic')
    assert any('does not reach' in w for w in result['Parse_Warnings'])
