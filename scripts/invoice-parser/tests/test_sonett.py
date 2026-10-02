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


# --- S26-0824-2606: the RRP column is filled in ---------------------------------------

RRP_FIXTURE = '2026-08-24_S26-0824-2606.txt'


def rrp_text():
    with open(os.path.join(FIXTURE_DIR, RRP_FIXTURE), encoding='utf-8') as fh:
        return fh.read()


@pytest.fixture(scope='module')
def rrp_invoice():
    """No. S26-0824-2606, 2026-08-24. 21 item lines, each ending unit / total / RRP."""
    return sonett.parse_invoice(rrp_text(), RRP_FIXTURE)


def test_rrp_invoice_header(rrp_invoice):
    assert rrp_invoice['Invoice Number'] == 'S26-0824-2606'
    assert rrp_invoice['Invoice Date'] == '24/08/2026'


def test_rrp_invoice_amounts(rrp_invoice):
    assert rrp_invoice['VAT 23%'] == '739.60'
    assert rrp_invoice['Total_VAT'] == 170.11
    assert rrp_invoice['Total'] == '909.71'


def test_rrp_column_is_not_summed_as_the_line_total(rrp_invoice):
    # Taking the last figure on each row sums the RRPs and reports €597.20 of lines
    # against the stated €739.60 net
    assert 'Parse_Warnings' not in rrp_invoice


def test_line_total_is_found_by_quantity_times_unit_price():
    # "2 Laundry Liquid Lavender 30-95C 20 l 65.05 130.10 120.00"
    assert sonett._line_total(2, [65.05, 130.10, 120.00]) == 130.10
    # A money-shaped figure in the description ahead of the unit price
    assert sonett._line_total(4, [0.30, 4.31, 17.24]) == 17.24
    assert sonett._line_total(6, [7.56, 45.36]) == 45.36


def test_line_that_fits_no_figure_is_flagged():
    text = rrp_text().replace(
        '6 Decalcifier 1 l 3.23 19.38 5.95', '6 Decalcifier 1 l 3.23 17.00 5.95'
    )
    result = sonett.parse_invoice(text, 'synthetic')
    assert any('Decalcifier' in w for w in result['Parse_Warnings'])
    # and the line sum no longer reconciles with the stated net
    assert any('Line items total' in w for w in result['Parse_Warnings'])
