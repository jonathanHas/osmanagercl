"""
Regression tests for the Klee Paper parser.

Two faults these tests pin down. The parser used to match money as (\\d+\\.\\d{2}), which
cannot span a thousands separator, so "Nett 1,278.70" was read as the fragment 278.70 and a
€1,572.82 invoice imported as €342.80 with no warning. And Klee Paper round VAT per line,
so the VAT they state is not round(net x rate, 2) on the aggregate — 10 of the 23 invoices
in the archive differ by a cent or two.

The fixtures are real extracted invoice text with the IBAN and the customer's phone number
redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import kleepaper  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'kleepaper')

FIXTURES = [
    '2026-06-29_WS061108_thousands.txt',
    '2026-03-31_thousands_regression.txt',
    '2026-01-29_vat_rounding.txt',
    '2026-04-10_small.txt',
]


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return kleepaper.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def thousands():
    """WS061108, 2026-06-29. Nett 1,278.70 — the invoice that exposed the comma bug."""
    return parse('2026-06-29_WS061108_thousands.txt')


@pytest.fixture(scope='module')
def thousands_regression():
    """WS060038, 2026-03-31. Nett 1,007.05 — the archived invoice the bug corrupted."""
    return parse('2026-03-31_thousands_regression.txt')


@pytest.fixture(scope='module')
def vat_rounding():
    """WS059353, 2026-01-29. No comma in Nett, but the stated VAT is 2c above computed."""
    return parse('2026-01-29_vat_rounding.txt')


@pytest.fixture(scope='module')
def small():
    """WS060132, 2026-04-10. €44.28, the ordinary case, nothing unusual."""
    return parse('2026-04-10_small.txt')


def test_header(thousands):
    assert thousands['Supplier'] == 'Klee Paper'
    assert thousands['Invoice Number'] == 'WS061108'
    assert thousands['Invoice Date'] == '29/06/2026'
    assert thousands['Credit Note'] is False
    assert thousands['Tax Free'] is False


def test_thousands_separator_is_not_truncated(thousands):
    # "Nett 1,278.70" read with (\d+\.\d{2}) matched the fragment "278.70", which imported
    # this invoice as €342.80 instead of €1,572.82.
    assert thousands['VAT 23%'] == '1278.70'
    assert thousands['Total'] == '1572.82'
    assert float(thousands['Total']) > 1000


def test_uses_the_vat_printed_on_the_invoice(thousands):
    # Klee Paper round VAT per line: the invoice states 294.12 where the aggregate
    # calculation gives 294.10.
    assert round(1278.70 * 0.23, 2) == 294.10
    assert thousands['VAT Amounts']['23'] == 294.12
    assert thousands['Total_VAT'] == 294.12
    assert 'Parse_Warnings' not in thousands


def test_reconciles_to_the_printed_total(thousands):
    nets = sum(float(thousands[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + thousands['Total_VAT'], 2) == 1572.82


def test_the_archived_invoice_the_bug_corrupted(thousands_regression):
    # This one imported as a net of €7.05 and a total of €8.67 against €1,238.68.
    assert thousands_regression['Invoice Number'] == 'WS060038'
    assert thousands_regression['VAT 23%'] == '1007.05'
    assert thousands_regression['VAT Amounts']['23'] == 231.63
    assert thousands_regression['Total'] == '1238.68'
    assert 'Parse_Warnings' not in thousands_regression


def test_vat_rounding_without_a_comma(vat_rounding):
    assert vat_rounding['Invoice Number'] == 'WS059353'
    assert vat_rounding['VAT 23%'] == '854.05'
    # stated 196.45 against round(854.05 * 0.23, 2) == 196.43
    assert round(854.05 * 0.23, 2) == 196.43
    assert vat_rounding['VAT Amounts']['23'] == 196.45
    assert vat_rounding['Total'] == '1050.50'


def test_small_invoice_parses_cleanly(small):
    assert small['Invoice Number'] == 'WS060132'
    assert small['Invoice Date'] == '10/04/2026'
    assert small['VAT 23%'] == '36.00'
    assert small['VAT Amounts']['23'] == 8.28
    assert small['Total'] == '44.28'
    assert 'Parse_Warnings' not in small


def test_product_lines_are_not_mistaken_for_summary_rows(thousands):
    # The item table above the totals block also carries an "S23" tax code on every line.
    # Only the one real VAT summary row may be counted.
    assert thousands['VAT 23%'] == '1278.70'
    assert thousands['VAT 0%'] == '0.00'


def test_a_summary_that_does_not_reconcile_is_flagged():
    text = (
        'Invoice Date 2026-09-05\n'
        'ecoLand® Our Ref. WS099999\n'
        'Package Summary VAT Summary Order Summary\n'
        'Item Value Code Nett Rate Total Item Value\n'
        'Items 1.00 S23 100.00 23.00 23.00 Nett 999.00\n'
        'Weight Kgs 1.00 VAT 23.00\n'
        'Total 123.00\n'
        'Total value of this invoice: 123.00 Euro\n'
    )
    data = kleepaper.parse_invoice(text, 'synthetic.txt')
    assert any('order summary states €999.00' in w for w in data.get('Parse_Warnings', []))


def test_a_second_rate_row_is_not_dropped():
    """The rate used to be hardcoded as 23.00, so any other row vanished silently."""
    text = (
        'Invoice Date 2026-09-05\n'
        'ecoLand® Our Ref. WS099998\n'
        'Package Summary VAT Summary Order Summary\n'
        'Item Value Code Nett Rate Total Item Value\n'
        'Items 2.00 S23 100.00 23.00 23.00 Nett 200.00\n'
        'S13 100.00 13.50 13.50 VAT 36.50\n'
        'Total 236.50\n'
        'Total value of this invoice: 236.50 Euro\n'
    )
    data = kleepaper.parse_invoice(text, 'synthetic.txt')
    assert data['VAT 23%'] == '100.00'
    assert data['VAT 13.5%'] == '100.00'
    assert data['Total_VAT'] == 36.50
    assert data['Total'] == '236.50'
    assert 'Parse_Warnings' not in data


def test_an_unrecognisable_rate_still_contributes_and_warns():
    text = (
        'Invoice Date 2026-09-05\n'
        'ecoLand® Our Ref. WS099997\n'
        'Package Summary VAT Summary Order Summary\n'
        'Item Value Code Nett Rate Total Item Value\n'
        'Items 1.00 S17 100.00 17.50 17.50 Nett 100.00\n'
        'Weight Kgs 1.00 VAT 17.50\n'
        'Total value of this invoice: 117.50 Euro\n'
    )
    data = kleepaper.parse_invoice(text, 'synthetic.txt')
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
def test_every_fixture_yields_a_reference_and_a_date(fixture):
    data = parse(fixture)
    assert data['Supplier'] == 'Klee Paper'
    assert data['Invoice Number'].startswith('WS')
    assert data['Invoice Date'] != 'Not found'
    assert 'Parse_Warnings' not in data
