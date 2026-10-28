"""
Regression tests for the BreaDelicious parser.

BreaDelicious switched invoicing software between 2026-06-28 and 2026-07-06.
Both layouts remain in circulation across the historic invoice archive, and the
Xero one prices every line VAT-inclusive, so the arithmetic differs completely
between them. The fixtures are real extracted invoice text with bank details
redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import breadelicious  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'breadelicious')


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return breadelicious.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def xero():
    """INV-0180, 2026-08-09. Six zero-rated bread lines plus two at 13.5%."""
    return parse('2026-08-09_xero.txt')


@pytest.fixture(scope='module')
def xero_credit_line():
    """INV-0056, 2026-07-12. Contains a credit line shown as (4.80)."""
    return parse('2026-07-12_xero_credit_line.txt')


@pytest.fixture(scope='module')
def xero_zero_qty():
    """INV-0149, 2026-08-03. Contains a 0.00 quantity line with no tax column."""
    return parse('2026-08-03_xero_zero_qty.txt')


@pytest.fixture(scope='module')
def xero_new_template():
    """INV-0467, 2026-10-11. Xero's newer template: bare quantities, "0%" tax labels,
    "Includes tax 13.5% of", and an icon-font glyph in place of the invoice-number hyphen."""
    return parse('2026-10-11_xero_new_template.txt')


@pytest.fixture(scope='module')
def xero_minus_glyph():
    """INV-0498, 2026-10-18. New template with two 0% credit lines whose minus sign
    pdfplumber returns as the icon-font glyph: "1 \\ue0884.80 0% \\ue0884.80"."""
    return parse('2026-10-18_xero_minus_glyph.txt')


@pytest.fixture(scope='module')
def fakturownia():
    """2025/1760, 2025-12-28. Legacy layout with an explicit VAT summary table."""
    return parse('2025-12-28_fakturownia.txt')


def test_xero_header(xero):
    assert xero['Supplier'] == 'BreaDelicious'
    assert xero['Invoice Number'] == 'INV-0180'
    assert xero['Invoice Date'] == '09/08/2026'
    assert xero['Credit Note'] is False


def test_xero_splits_zero_rated_from_reduced(xero):
    # 6.00+2.00+1.00+3.00+2.00+1.00 loaves at 4.80 = 72.00, all zero rated
    assert xero['VAT 0%'] == '72.00'
    # Brioche 15.60 + 8.40 = 24.00 gross, less the 2.86 VAT the invoice states
    assert xero['VAT 13.5%'] == '21.14'
    assert xero['VAT 23%'] == '0.00'
    assert xero['VAT 9%'] == '0.00'


def test_xero_uses_the_vat_printed_on_the_invoice(xero):
    # Xero rounds VAT per line: 1.86 + 1.00 = 2.86, where round(21.14 * 0.135, 2)
    # on the aggregate would give 2.85. The invoice figure must win.
    assert xero['VAT Amounts']['13.5'] == 2.86
    assert xero['Total_VAT'] == 2.86


def test_xero_reconciles_to_the_printed_total(xero):
    assert xero['Total'] == '96.00'
    nets = sum(float(xero[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + xero['Total_VAT'], 2) == 96.00
    assert 'Parse_Warnings' not in xero


def test_xero_handles_parenthesised_credit_lines(xero_credit_line):
    # A returned loaf shows as (4.80) and must reduce the zero-rated total
    assert xero_credit_line['VAT 0%'] == '121.40'
    assert xero_credit_line['VAT 13.5%'] == '51.45'
    assert xero_credit_line['Total'] == '179.80'
    assert xero_credit_line['Total_VAT'] == 6.95
    assert 'Parse_Warnings' not in xero_credit_line


def test_xero_handles_zero_quantity_lines_with_no_tax_column(xero_zero_qty):
    # The 0.00 quantity line carries no tax label at all and must not unbalance
    # the invoice or trigger the unlabelled-amount warning
    assert xero_zero_qty['VAT 0%'] == '115.20'
    assert xero_zero_qty['VAT 13.5%'] == '46.87'
    assert xero_zero_qty['Total'] == '168.40'
    assert xero_zero_qty['Total_VAT'] == 6.33
    assert 'Parse_Warnings' not in xero_zero_qty


def test_xero_new_template_header(xero_new_template):
    # pdfplumber returns "INV\ue0880467"; the glyph must be read as the hyphen
    assert xero_new_template['Invoice Number'] == 'INV-0467'
    # Header row reads due date 18 Oct 2026 then issue date 11 Oct 2026
    assert xero_new_template['Invoice Date'] == '11/10/2026'
    assert xero_new_template['Credit Note'] is False


def test_xero_new_template_buckets(xero_new_template):
    # Seven 0% lines with bare integer quantities, including the 11.00 keto loaf
    assert xero_new_template['VAT 0%'] == '121.40'
    # Brioche 41.60 + 8.40 = 50.00 gross, less the stated 5.95
    assert xero_new_template['VAT 13.5%'] == '44.05'
    assert xero_new_template['VAT Amounts']['13.5'] == 5.95
    assert xero_new_template['Total_VAT'] == 5.95
    # "Total 171.40", not "Subtotal 171.40" or "Amount due"
    assert xero_new_template['Total'] == '171.40'
    assert 'Parse_Warnings' not in xero_new_template


def test_xero_new_template_reads_icon_minus_as_negative(xero_minus_glyph):
    assert xero_minus_glyph['Invoice Number'] == 'INV-0498'
    assert xero_minus_glyph['Invoice Date'] == '18/10/2026'
    assert xero_minus_glyph['Credit Note'] is False
    # 72.00 + 24.00 + 9.60 - 4.80 + 4.80 + 9.60 - 11.00
    assert xero_minus_glyph['VAT 0%'] == '104.20'
    # Brioche 26.00 + 11.20 = 37.20 gross, less the stated 4.42
    assert xero_minus_glyph['VAT 13.5%'] == '32.78'
    assert xero_minus_glyph['Total_VAT'] == 4.42
    assert xero_minus_glyph['Total'] == '141.40'
    # Without the glyph fix the credit lines are dropped and the 0% bucket is only
    # rescued by the "assigning the difference" fallback, which warns
    assert 'Parse_Warnings' not in xero_minus_glyph


def test_fakturownia_layout_still_parses(fakturownia):
    assert fakturownia['Invoice Number'] == '2025/1760'
    assert fakturownia['Invoice Date'] == '28/12/2025'
    assert fakturownia['VAT 0%'] == '110.00'
    assert fakturownia['VAT 13.5%'] == '10.09'
    assert fakturownia['VAT 23%'] == '0.00'
    assert fakturownia['Total_VAT'] == 1.36
    assert fakturownia['Total'] == '121.45'
    assert 'Parse_Warnings' not in fakturownia


@pytest.mark.parametrize('fixture', [
    '2026-08-09_xero.txt',
    '2026-07-12_xero_credit_line.txt',
    '2026-08-03_xero_zero_qty.txt',
    '2026-10-11_xero_new_template.txt',
    '2026-10-18_xero_minus_glyph.txt',
    '2025-12-28_fakturownia.txt',
])
def test_every_fixture_reconciles_net_plus_vat_to_total(fixture):
    data = parse(fixture)
    nets = sum(float(data[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + data['Total_VAT'], 2) == float(data['Total'])
