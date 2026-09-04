"""
Regression tests for the Independent Irish Health Foods parser.

Independent state net and VAT per rate in a summary table at the end of the invoice, and
they round VAT per line, so the stated Tax column does not equal round(net x rate, 2) on
the aggregate. The parser returns the stated figures as 'VAT Amounts' so the dispatcher
uses them verbatim. The fixtures are real extracted invoice text with the customer's phone
number and account number redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import independent  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'independent')

FIXTURES = [
    '2026-09-02_three_rates.txt',
    '2025-07-23_misprinted_rate.txt',
    '2026-05-13_single_rate.txt',
]


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return independent.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def three_rates():
    """IN482326, 2026-09-02. Six pages, 0% / 13.5% / 23%, VAT rounded per line."""
    return parse('2026-09-02_three_rates.txt')


@pytest.fixture(scope='module')
def misprinted_rate():
    """IN439078, 2025-07-23. Prints its standard row as 22.50% while charging 23%."""
    return parse('2025-07-23_misprinted_rate.txt')


@pytest.fixture(scope='module')
def single_rate():
    """IN470366, 2026-05-13. One 23% row, where the aggregate VAT happens to be exact."""
    return parse('2026-05-13_single_rate.txt')


def test_three_rates_header(three_rates):
    assert three_rates['Supplier'] == 'Independent'
    assert three_rates['Invoice Number'] == 'IN482326'
    assert three_rates['Invoice Date'] == '02/09/2026'
    assert three_rates['Credit Note'] is False


def test_three_rates_nets(three_rates):
    assert three_rates['VAT 0%'] == '1354.42'
    assert three_rates['VAT 13.5%'] == '234.17'
    assert three_rates['VAT 23%'] == '652.44'
    assert three_rates['VAT 9%'] == '0.00'


def test_uses_the_vat_printed_on_the_invoice(three_rates):
    # This is the whole point of the parser. Independent round VAT per line, so the
    # standard-rate VAT they state is 150.09 where round(652.44 * 0.23, 2) gives 150.06.
    # Recomputing from the aggregate would report 181.67 of VAT and a 2,422.70 total.
    assert round(652.44 * 0.23, 2) == 150.06
    assert three_rates['VAT Amounts']['23'] == 150.09
    assert three_rates['VAT Amounts']['13.5'] == 31.61
    assert three_rates['VAT Amounts']['0'] == 0.0
    assert three_rates['Total_VAT'] == 181.70


def test_three_rates_reconciles_to_the_printed_total(three_rates):
    assert three_rates['Total'] == '2422.73'
    nets = sum(float(three_rates[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert nets == 2241.03
    assert round(nets + three_rates['Total_VAT'], 2) == 2422.73
    assert 'Parse_Warnings' not in three_rates


def test_grand_total_is_not_confused_with_gross_total(three_rates):
    # The block prints "Gross Total: 2,241.03" above "Total: 2,422.73". A naive "Total:"
    # match picks up the net figure.
    assert three_rates['Total'] == '2422.73'


def test_product_lines_are_not_mistaken_for_summary_rows(three_rates):
    # The fixture is a full six-page invoice, ~280 lines of product detail above the
    # summary. Only the three real rate rows may be counted.
    assert float(three_rates['VAT 0%']) == 1354.42
    assert three_rates['Total'] == '2422.73'


def test_misprinted_rate_is_kept_and_flagged(misprinted_rate):
    # Independent printed 22.50% but charged 37.90 on 164.75, which is 23%. The old
    # whitelist dropped the row entirely, reporting a 201.93 total against 404.58.
    assert misprinted_rate['VAT 0%'] == '201.93'
    assert misprinted_rate['VAT 23%'] == '164.75'
    assert misprinted_rate['VAT Amounts']['23'] == 37.90
    assert misprinted_rate['Total'] == '404.58'
    warnings = misprinted_rate.get('Parse_Warnings', [])
    assert any('22.50%' in warning and '23%' in warning for warning in warnings)


def test_single_rate_invoice_parses_cleanly(single_rate):
    assert single_rate['VAT 23%'] == '26.96'
    assert single_rate['VAT 0%'] == '0.00'
    assert single_rate['VAT Amounts']['23'] == 6.20
    assert single_rate['Total'] == '33.16'
    assert 'Parse_Warnings' not in single_rate


def test_a_summary_that_does_not_reconcile_is_flagged():
    text = (
        'Invoice No: IN999999 Invoice Date: 04/09/2026\n'
        'Tax Code Rate Taxable Tax DRS Totals Gross Total: 999.00\n'
        '0 0.00 100.00 0.00 DRS 15c 0 0.00 Tax: 23.00\n'
        '1 23.00 100.00 23.00 DRS 25c 0 0.00\n'
        'Total: 223.00\n'
        'VAT Reg No. IE9678859H\n'
    )
    data = independent.parse_invoice(text, 'synthetic.txt')
    warnings = data.get('Parse_Warnings', [])
    assert any('the invoice states €999.00' in warning for warning in warnings)


def test_an_unrecognisable_rate_is_flagged_rather_than_dropped():
    """A rate that matches nothing must still contribute its money and raise a warning."""
    text = (
        'Invoice No: IN999998 Invoice Date: 04/09/2026\n'
        'Tax Code Rate Taxable Tax DRS Totals Gross Total: 100.00\n'
        '1 17.50 100.00 17.50 DRS 15c 0 0.00 Tax: 17.50\n'
        'Total: 117.50\n'
        'VAT Reg No. IE9678859H\n'
    )
    data = independent.parse_invoice(text, 'synthetic.txt')
    nets = sum(float(data[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert nets == 100.00
    assert data['Total_VAT'] == 17.50
    assert any('needs checking by hand' in w for w in data.get('Parse_Warnings', []))


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_reconciles_net_plus_vat_to_total(fixture):
    data = parse(fixture)
    nets = sum(float(data[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + data['Total_VAT'], 2) == float(data['Total'])


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_yields_a_number_and_date(fixture):
    data = parse(fixture)
    assert data['Supplier'] == 'Independent'
    assert data['Invoice Number'].startswith('IN')
    assert data['Invoice Date'] != 'Not found'
