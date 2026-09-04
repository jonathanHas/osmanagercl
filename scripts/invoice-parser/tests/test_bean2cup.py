"""
Regression tests for the Bean2Cup parser.

bean2cup tech support limited invoices coffee machine parts at 23% and call-out/labour at
13.5%, so most invoices span two rates. The layout has been stable across the whole archive
(2025-09-18 to 2026-09-01) and carries an explicit VAT summary table giving net and VAT per
rate, which is what the parser reads. The fixtures are real extracted invoice text with bank
details redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import bean2cup  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'bean2cup')

FIXTURES = [
    '2026-09-01_discount_two_rates.txt',
    '2026-07-14_single_rate.txt',
    '2026-03-03_thousands_separator.txt',
    '2025-09-18_two_rates.txt',
]


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return bean2cup.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def discounted():
    """INV-14996, 2026-09-01. Carries a Discount column and a fully discounted call-out."""
    return parse('2026-09-01_discount_two_rates.txt')


@pytest.fixture(scope='module')
def single_rate():
    """INV-14869, 2026-07-14. Cleaning products, everything at 23%."""
    return parse('2026-07-14_single_rate.txt')


@pytest.fixture(scope='module')
def thousands():
    """INV-14524, 2026-03-03. €1,076.00 net — the only invoice with a thousands separator."""
    return parse('2026-03-03_thousands_separator.txt')


@pytest.fixture(scope='module')
def two_rates():
    """INV-14144, 2025-09-18. Eight lines split across 13.5% labour and 23% parts."""
    return parse('2025-09-18_two_rates.txt')


def test_discounted_header(discounted):
    assert discounted['Supplier'] == 'Bean2Cup'
    assert discounted['Invoice Number'] == 'INV-14996'
    assert discounted['Invoice Date'] == '01/09/2026'
    assert discounted['Credit Note'] is False
    assert discounted['Tax Free'] is False


def test_discounted_excludes_the_fully_discounted_call_out(discounted):
    # "CALL OUT free of charge" prices at 90.00 with a 90.00 discount, so it nets to 0.00
    # and only LABOUR reaches the 13.5% bucket.
    assert discounted['VAT 13.5%'] == '70.00'
    assert discounted['VAT 23%'] == '96.00'
    assert discounted['VAT 0%'] == '0.00'
    assert discounted['VAT 9%'] == '0.00'


def test_discounted_totals(discounted):
    assert discounted['VAT Amounts']['13.5'] == 9.45
    assert discounted['VAT Amounts']['23'] == 22.08
    assert discounted['Total_VAT'] == 31.53
    assert discounted['Total'] == '197.53'
    assert 'Parse_Warnings' not in discounted


def test_single_rate_invoice(single_rate):
    assert single_rate['Invoice Number'] == 'INV-14869'
    assert single_rate['Invoice Date'] == '14/07/2026'
    assert single_rate['VAT 23%'] == '206.70'
    assert single_rate['VAT 13.5%'] == '0.00'
    assert single_rate['Total_VAT'] == 47.54
    assert single_rate['Total'] == '254.24'
    assert 'Parse_Warnings' not in single_rate


def test_thousands_separator_is_stripped(thousands):
    # "€ 1,076.00" must not truncate to 1.00 or 76.00
    assert thousands['Invoice Number'] == 'INV-14524'
    assert thousands['Invoice Date'] == '03/03/2026'
    assert thousands['VAT 23%'] == '1076.00'
    assert thousands['Total'] == '1323.48'
    assert 'Parse_Warnings' not in thousands


def test_two_rate_invoice(two_rates):
    assert two_rates['Invoice Number'] == 'INV-14144'
    assert two_rates['Invoice Date'] == '18/09/2025'
    assert two_rates['VAT 13.5%'] == '140.00'
    assert two_rates['VAT 23%'] == '377.32'
    assert two_rates['VAT Amounts']['13.5'] == 18.90
    assert two_rates['VAT Amounts']['23'] == 86.78
    assert two_rates['Total_VAT'] == 105.68
    assert two_rates['Total'] == '623.00'
    assert 'Parse_Warnings' not in two_rates


def test_invoice_date_is_taken_not_the_due_date():
    """Invoice Date and Due Date are printed as an adjacent pair and are often identical;
    when they differ the invoice date must win."""
    text = (
        'SALES INVOICE\n'
        'Invoice To:\n'
        'Invoice Date 04/09/2026\n'
        'The Organic Store Due Date 30/09/2026\n'
        'Offaly Invoice Number INV-15000\n'
        'Code Description Qty/Hrs Price/Rate VAT % Net\n'
        'LABOUR LABOUR 1.00 70.00 13.50 70.00\n'
        'VAT Rate Net VAT Total Net 70.00\n'
        'Reduced Rate 13.50% (13.50%) € 70.00 € 9.45 Total VAT 9.45\n'
        'TOTAL € 79.45\n'
    )
    data = bean2cup.parse_invoice(text, 'synthetic.txt')
    assert data['Invoice Date'] == '04/09/2026'
    assert data['Total'] == '79.45'
    assert 'Parse_Warnings' not in data


def test_missing_summary_table_falls_back_to_line_items_and_warns():
    """Without the summary table the parser must still produce figures, but flag itself
    for review rather than import silently."""
    text = (
        'SALES INVOICE\n'
        'Invoice Date 04/09/2026\n'
        'Offaly Invoice Number INV-15001\n'
        'Code Description Qty/Hrs Price/Rate Discount VAT % Net\n'
        'CALL CALL OUT free of charge 1.00 90.00 90.00 13.50 0.00\n'
        'LABOUR LABOUR 1.00 70.00 13.50 70.00\n'
        'Steam Steam valve repair kit Sanremo CR 1.00 96.00 23.00 96.00\n'
        'Thank you for your business.\n'
    )
    data = bean2cup.parse_invoice(text, 'synthetic.txt')
    assert data['VAT 13.5%'] == '70.00'
    assert data['VAT 23%'] == '96.00'
    assert data['Total'] == '197.53'
    warnings = data.get('Parse_Warnings', [])
    assert any('VAT summary table not found' in warning for warning in warnings)


def test_a_summary_that_does_not_reconcile_is_flagged():
    text = (
        'SALES INVOICE\n'
        'Invoice Date 04/09/2026\n'
        'Offaly Invoice Number INV-15002\n'
        'VAT Rate Net VAT Total Net 999.00\n'
        'Standard 23.00% (23.00%) € 100.00 € 23.00 Total VAT 23.00\n'
        'TOTAL € 123.00\n'
    )
    data = bean2cup.parse_invoice(text, 'synthetic.txt')
    warnings = data.get('Parse_Warnings', [])
    assert any('the invoice states €999.00' in warning for warning in warnings)


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_reconciles_net_plus_vat_to_total(fixture):
    data = parse(fixture)
    nets = sum(float(data[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + data['Total_VAT'], 2) == float(data['Total'])


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_parses_cleanly(fixture):
    data = parse(fixture)
    assert data['Supplier'] == 'Bean2Cup'
    assert data['Invoice Number'].startswith('INV-')
    assert data['Invoice Date'] != 'Not found'
    assert 'Parse_Warnings' not in data
