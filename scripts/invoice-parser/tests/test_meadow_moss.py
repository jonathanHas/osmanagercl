"""
Regression tests for the Meadow & Moss parser.

Meadow & Moss deliver cut flower bouquets weekly and are not VAT registered — no VAT
number appears on the invoice, the Tax row is printed empty and Subtotal equals Total, so
the whole amount is tax free and belongs in the 0% bucket. Returned bouquets appear as
negative lines inside an ordinary invoice. The layout has been stable since 2026-07-23.
The fixtures are real extracted invoice text with bank details redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import meadow_moss  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'meadow_moss')

FIXTURES = [
    '2026-08-31_invoice_005.txt',
    '2026-08-10_invoice_004_credit_lines.txt',
    '2026-07-23_invoice_001.txt',
]


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return meadow_moss.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def invoice_005():
    """005, 2026-08-31. Nine lines, two of them negative returns."""
    return parse('2026-08-31_invoice_005.txt')


@pytest.fixture(scope='module')
def invoice_004():
    """004, 2026-08-10. Two lines are explicitly labelled 'Credit Lrg Bouq'."""
    return parse('2026-08-10_invoice_004_credit_lines.txt')


@pytest.fixture(scope='module')
def invoice_001():
    """001, 2026-07-23. Seven lines, all positive, two different Large Bouquet prices."""
    return parse('2026-07-23_invoice_001.txt')


def test_invoice_005_header(invoice_005):
    assert invoice_005['Supplier'] == 'Meadow & Moss'
    assert invoice_005['Invoice Number'] == '005'
    assert invoice_005['Invoice Date'] == '31/08/2026'
    assert invoice_005['Credit Note'] is False


def test_invoice_005_is_tax_free_and_all_zero_rated(invoice_005):
    # The supplier is not VAT registered: the Tax row is empty and Subtotal equals Total
    assert invoice_005['Tax Free'] is True
    assert invoice_005['VAT 0%'] == '360.80'
    assert invoice_005['VAT 9%'] == '0.00'
    assert invoice_005['VAT 13.5%'] == '0.00'
    assert invoice_005['VAT 23%'] == '0.00'
    assert invoice_005['Total_VAT'] == 0.0


def test_invoice_005_negative_lines_reduce_the_total(invoice_005):
    # 54.96 + 52.86 + 27.48 + 70.48 + 73.28 - 35.24 + 52.86 + 73.28 - 9.16 = 360.80
    assert invoice_005['Total'] == '360.80'
    assert 'Parse_Warnings' not in invoice_005


def test_invoice_number_is_taken_not_the_payment_terms(invoice_005):
    # "Invoice No: 005 Payment Terms: 14 Day" — both labels share one extracted line
    assert invoice_005['Invoice Number'] == '005'


def test_invoice_date_is_taken_not_the_payment_due_date(invoice_005):
    # "Invoice Date: 31/08/2026 Payment Due: 14/09/2026" — again one shared line
    assert invoice_005['Invoice Date'] == '31/08/2026'


def test_credit_lines_do_not_make_the_invoice_a_credit_note(invoice_004):
    # Two lines read "Credit Lrg Bouq ... -1 €17.62 -€17.62". The document is still an
    # ordinary invoice with a positive total.
    assert invoice_004['Credit Note'] is False
    assert invoice_004['Invoice Number'] == '004'
    assert invoice_004['Invoice Date'] == '10/08/2026'
    assert invoice_004['VAT 0%'] == '278.36'
    assert invoice_004['Total'] == '278.36'
    assert 'Parse_Warnings' not in invoice_004


def test_invoice_001(invoice_001):
    assert invoice_001['Invoice Number'] == '001'
    assert invoice_001['Invoice Date'] == '23/07/2026'
    assert invoice_001['VAT 0%'] == '394.64'
    assert invoice_001['Total'] == '394.64'
    assert 'Parse_Warnings' not in invoice_001


def test_a_taxed_invoice_is_flagged_for_review():
    """Meadow & Moss have never charged VAT. If they register, the rate cannot be inferred
    from the layout, so the invoice must land in review rather than import as zero rated."""
    text = (
        'INVOICE\n'
        'Meadow & Moss\n'
        'Invoice No: 006 Payment Terms: 14 Day\n'
        'Invoice Date: 30/09/2026 Payment Due: 14/10/2026\n'
        'Item Delivery Date Qty Unit Price Line Total\n'
        'Small Bouquet 10 September 2026 6 €9.16 €54.96\n'
        'Payment Methods Subtotal €54.96\n'
        'Account Name Meadow and Moss Tax €7.42\n'
        'IBAN IE00 XXXX 0000 0000 0000 00 Total €62.38\n'
    )
    data = meadow_moss.parse_invoice(text, 'synthetic.txt')
    warnings = data.get('Parse_Warnings', [])
    assert any('needs confirming by hand' in warning for warning in warnings)
    assert any('needs checking by hand' in warning for warning in warnings)


def test_a_negative_total_is_a_credit_note():
    text = (
        'INVOICE\n'
        'Meadow & Moss\n'
        'Invoice No: 007 Payment Terms: 14 Day\n'
        'Invoice Date: 30/09/2026 Payment Due: 14/10/2026\n'
        'Item Delivery Date Qty Unit Price Line Total\n'
        'Large Bouquet 10 September 2026 -2 €17.62 -€35.24\n'
        'Payment Methods Subtotal -€35.24\n'
        'Account Name Meadow and Moss Tax\n'
        'IBAN IE00 XXXX 0000 0000 0000 00 Total -€35.24\n'
    )
    data = meadow_moss.parse_invoice(text, 'synthetic.txt')
    assert data['Credit Note'] is True
    assert data['Total'] == '-35.24'
    assert 'Parse_Warnings' not in data


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_reconciles_net_plus_vat_to_total(fixture):
    data = parse(fixture)
    nets = sum(float(data[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + data['Total_VAT'], 2) == float(data['Total'])


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_parses_cleanly(fixture):
    data = parse(fixture)
    assert data['Supplier'] == 'Meadow & Moss'
    assert data['Invoice Number'] != 'Not found'
    assert data['Invoice Date'] != 'Not found'
    assert 'Parse_Warnings' not in data
