"""
Regression tests for the Sonett parser.

Sonett Ireland (Frank van Gent) invoice a single-rate, single-page layout: net line totals,
an unlabelled net subtotal above one "V.A.T. 23%" row, and the "Final amount in Euro". The
date is written long-hand ("3 April 2026") and a due date in the same style follows later.

Only one PDF exists so far — earlier invoices were captured as photos — so the synthetic
cases below carry more of the weight than usual. The fixture is real extracted invoice
text with the store's phone number redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import sonett  # noqa: E402
from invoice_parser_laravel import detect_supplier  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'sonett')
FIXTURE = '2026-04-03_2603.txt'


def fixture_text():
    with open(os.path.join(FIXTURE_DIR, FIXTURE), encoding='utf-8') as fh:
        return fh.read()


@pytest.fixture(scope='module')
def invoice():
    """No. 2603, 2026-04-03. Eleven item lines, all at 23%."""
    return sonett.parse_invoice(fixture_text(), FIXTURE)


def test_header(invoice):
    assert invoice['Supplier'] == 'Sonett'
    assert invoice['Invoice Number'] == '2603'
    assert invoice['Credit Note'] is False
    assert invoice['Tax Free'] is False


def test_invoice_date_not_due_date(invoice):
    # "to be paid before 3 May 2026" must not be taken for the invoice date
    assert invoice['Invoice Date'] == '03/04/2026'


def test_net_all_at_23(invoice):
    assert invoice['VAT 23%'] == '418.38'
    assert invoice['VAT 0%'] == '0.00'
    assert invoice['VAT 9%'] == '0.00'
    assert invoice['VAT 13.5%'] == '0.00'


def test_vat_and_total(invoice):
    assert invoice['VAT Amounts']['23'] == 96.23
    assert invoice['Total_VAT'] == 96.23
    assert invoice['Total'] == '514.61'


def test_line_items_reconcile_without_warnings(invoice):
    assert 'Parse_Warnings' not in invoice


def test_detected_from_letterhead():
    parser, name = detect_supplier(fixture_text())
    assert parser is sonett
    assert name == 'Sonett'


def test_sonett_products_on_a_wholesaler_invoice_do_not_route_here():
    text = 'Some Wholesaler Ltd\nSonett Laundry Liquid Lavender 2l 7.56\n'
    parser, _ = detect_supplier(text)
    assert parser is not sonett


def test_thousands_separator():
    text = fixture_text()
    text = text.replace(
        '1 Lavender 100% Organic Bodywash Soap 10 l refill 70.19 70.19',
        '1 Lavender 100% Organic Bodywash Soap 10 l refill 70.19 70.19\n'
        '20 Dishwashing Liquid Lemon 20 l 49.86 997.20',
    )
    text = text.replace('418.38\nV.A.T. 23% 96.23', '1,415.58\nV.A.T. 23% 325.58')
    text = text.replace('Final amount in Euro\n514.61', 'Final amount in Euro\n1,741.16')
    result = sonett.parse_invoice(text, 'synthetic')
    assert result['VAT 23%'] == '1415.58'
    assert result['Total'] == '1741.16'
    assert 'Parse_Warnings' not in result


def test_freight_line_without_quantity_is_covered_by_the_stated_net():
    text = fixture_text().replace(
        '1 Lavender 100% Organic Bodywash Soap 10 l refill 70.19 70.19',
        '1 Lavender 100% Organic Bodywash Soap 10 l refill 70.19 70.19\nfreight 7.50',
    )
    text = text.replace('418.38\nV.A.T. 23% 96.23', '425.88\nV.A.T. 23% 97.95')
    text = text.replace('Final amount in Euro\n514.61', 'Final amount in Euro\n523.83')
    result = sonett.parse_invoice(text, 'synthetic')
    assert result['VAT 23%'] == '425.88'
    assert result['Total'] == '523.83'
    # The unmatched freight line shows up as a reconciliation warning, not a wrong net
    assert any('Line items total' in w for w in result['Parse_Warnings'])


def test_mismatched_total_is_flagged():
    text = fixture_text().replace('Final amount in Euro\n514.61', 'Final amount in Euro\n520.00')
    result = sonett.parse_invoice(text, 'synthetic')
    assert any('does not reach' in w for w in result['Parse_Warnings'])
