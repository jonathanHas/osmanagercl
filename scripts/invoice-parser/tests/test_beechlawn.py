"""
Regression tests for the Beechlawn parser.

Beechlawn changed Xero invoice template between 2026-07-27 and 2026-08-31. The legacy
layout carries an explicit ZeroRated tax column and a 'TOTALEUR' total; the current one
drops the tax column entirely and totals as 'Subtotal' / 'Total' / 'Amount due'. Both
remain in the archive, so both must keep parsing. The fixtures are real extracted invoice
text with bank details redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import beechlawn  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'beechlawn')

FIXTURES = [
    '2026-08-31_xero_new.txt',
    '2026-07-27_xero_legacy.txt',
    '2026-04-20_xero_legacy_short.txt',
    '2026-01-26_xero_legacy.txt',
]


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return beechlawn.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def current():
    """INV-32108, 2026-08-31. Seven produce lines, no tax column, decimal quantities."""
    return parse('2026-08-31_xero_new.txt')


@pytest.fixture(scope='module')
def legacy():
    """INV-31546, 2026-07-27. Six ZeroRated lines on the pre-changeover template."""
    return parse('2026-07-27_xero_legacy.txt')


@pytest.fixture(scope='module')
def legacy_short():
    """INV-30024, 2026-04-20. Two lines — the shortest invoice in the archive."""
    return parse('2026-04-20_xero_legacy_short.txt')


@pytest.fixture(scope='module')
def legacy_january():
    """INV-28632, 2026-01-26. Five lines including 2.10 kg quantities."""
    return parse('2026-01-26_xero_legacy.txt')


def test_current_header(current):
    assert current['Supplier'] == 'Beechlawn'
    assert current['Invoice Number'] == 'INV-32108'
    assert current['Invoice Date'] == '31/08/2026'
    assert current['Credit Note'] is False
    assert current['Tax Free'] is False


def test_current_takes_the_issue_date_not_the_due_date(current):
    # Issue date 31 Aug 2026, due date 20 Sept 2026. "Sept" is a four-letter abbreviation
    # that datetime's %b cannot parse, so it must not leak through as the invoice date.
    assert current['Invoice Date'] == '31/08/2026'


def test_current_puts_everything_in_the_zero_rated_bucket(current):
    # 16.00 + 10.80 + 18.90 + 14.85 + 15.20 + 64.00 + 40.00 = 179.75, all organic produce
    assert current['VAT 0%'] == '179.75'
    assert current['VAT 9%'] == '0.00'
    assert current['VAT 13.5%'] == '0.00'
    assert current['VAT 23%'] == '0.00'


def test_current_reads_the_total_not_the_subtotal_or_amount_due(current):
    assert current['Total'] == '179.75'
    assert current['Total_VAT'] == 0.0
    assert 'Parse_Warnings' not in current


def test_current_reads_descriptions_that_contain_digits(current):
    # "KALE, CURLY (1kg) - B146" at 2.7 x 7.00 and "SALAD LEAF (1 kg) - B204" at 2 x 20.00
    # only bucket correctly if the line regex anchors on the trailing pair of 2dp numbers
    # rather than the first number it meets.
    assert current['VAT 0%'] == '179.75'


def test_legacy_layout_still_parses(legacy):
    assert legacy['Invoice Number'] == 'INV-31546'
    assert legacy['Invoice Date'] == '27/07/2026'
    assert legacy['VAT 0%'] == '87.80'
    assert legacy['Total'] == '87.80'
    assert 'Parse_Warnings' not in legacy


def test_legacy_short_invoice(legacy_short):
    assert legacy_short['Invoice Number'] == 'INV-30024'
    assert legacy_short['Invoice Date'] == '20/04/2026'
    assert legacy_short['VAT 0%'] == '32.00'
    assert 'Parse_Warnings' not in legacy_short


def test_legacy_january_invoice(legacy_january):
    assert legacy_january['Invoice Number'] == 'INV-28632'
    assert legacy_january['Invoice Date'] == '26/01/2026'
    assert legacy_january['VAT 0%'] == '82.50'
    assert 'Parse_Warnings' not in legacy_january


def test_a_vat_bearing_invoice_is_flagged_for_review():
    """
    Beechlawn has never issued a VAT-bearing line, so we cannot know whether Xero would
    print the amounts net or gross. Such an invoice must land in review rather than import
    on a guess — Subtotal diverging from Total is the signal.
    """
    text = (
        'INVOICE\n'
        'Amount due Due date Issue date Invoice number\n'
        '€113.50 20 Sept 2026\n'
        '31 Aug 2026 INV-99999\n'
        'Description Quantity Price Amount\n'
        'POTATO, WHITE (1kg) 20 3.20 64.00\n'
        'Hamper Basket 1 36.50 36.50\n'
        'Subtotal 100.50\n'
        'Total VAT 13.00\n'
        'Total 113.50\n'
    )
    data = beechlawn.parse_invoice(text, 'synthetic.txt')
    warnings = data.get('Parse_Warnings', [])
    assert warnings, 'a VAT-bearing invoice must warn'
    assert any('needs checking by hand' in warning for warning in warnings)


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_reconciles_net_plus_vat_to_total(fixture):
    data = parse(fixture)
    nets = sum(float(data[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + data['Total_VAT'], 2) == float(data['Total'])


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_parses_cleanly(fixture):
    data = parse(fixture)
    assert data['Supplier'] == 'Beechlawn'
    assert data['Invoice Number'] != 'Not found'
    assert data['Invoice Date'] != 'Not found'
    assert 'Parse_Warnings' not in data
