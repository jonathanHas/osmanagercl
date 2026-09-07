"""
Regression tests for the Berlin Packaging parser.

Berlin Packaging Ireland Ltd (trading as The Packstock) price their line totals
VAT-inclusive, stating the rate and the tax per line but never the net. Two traps in the
layout: "SUBTOTAL" is the gross figure rather than the net, and the issue date is written
AP-style ("Sept. 5, 2026"), which datetime's %b cannot parse.

Only one invoice exists so far — there is no archive to cross-check against — so the
synthetic cases below carry more of the weight than usual. The fixture is real extracted
invoice text with the customer's phone number redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import berlin_packaging  # noqa: E402

FIXTURE_DIR = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'berlin_packaging'
)


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return berlin_packaging.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def invoice():
    """NR#2361, 2026-09-05. Three item lines plus a shipping line, all at 23%."""
    return parse('2026-09-05_NR2361.txt')


def test_header(invoice):
    assert invoice['Supplier'] == 'Berlin Packaging Ltd'
    assert invoice['Invoice Number'] == 'NR#2361'
    assert invoice['Invoice Date'] == '05/09/2026'
    assert invoice['Credit Note'] is False
    assert invoice['Tax Free'] is False


def test_supplier_name_matches_the_accounting_record_not_the_letterhead(invoice):
    # The invoice heads "Berlin Packaging Ireland Ltd" but the supplier is recorded as
    # "Berlin Packaging Ltd". Returning the letterhead name creates a duplicate supplier.
    assert invoice['Supplier'] == 'Berlin Packaging Ltd'


def test_ap_style_month_is_parsed(invoice):
    # "Issue Date Sept. 5, 2026" — a four-letter abbreviation with a trailing period, which
    # %b rejects outright.
    assert invoice['Invoice Date'] == '05/09/2026'


def test_line_totals_are_vat_inclusive_so_net_is_gross_less_tax(invoice):
    # 37.32-6.98 + 23.10-4.32 + 40.28-7.53 + 19.19-3.59 = 97.47
    assert invoice['VAT 23%'] == '97.47'
    assert invoice['VAT 0%'] == '0.00'
    assert invoice['VAT 9%'] == '0.00'
    assert invoice['VAT 13.5%'] == '0.00'


def test_quantity_times_unit_price_is_not_the_net(invoice):
    """The unit price is rounded for display: 70 x 0.27 is 18.90, but the line's real net
    is 18.78. Deriving the net from the printed quantity and unit price would be wrong."""
    assert round(70 * 0.27, 2) == 18.90
    assert invoice['VAT 23%'] == '97.47'


def test_uses_the_vat_printed_per_line(invoice):
    assert invoice['VAT Amounts']['23'] == 22.42
    assert invoice['Total_VAT'] == 22.42


def test_shipping_line_is_included(invoice):
    # "Shipping Ireland 15.60 23% €3.59 €19.19" carries no quantity column at all
    assert float(invoice['VAT 23%']) == 97.47  # includes the 15.60 shipping net


def test_reconciles_to_the_printed_total(invoice):
    assert invoice['Total'] == '119.89'
    nets = sum(float(invoice[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + invoice['Total_VAT'], 2) == 119.89
    assert 'Parse_Warnings' not in invoice


def test_subtotal_is_gross_and_is_not_mistaken_for_the_net(invoice):
    """SUBTOTAL €119.89 equals TOTAL INCL. VAT on this layout; the net is
    TOTAL EXCL. VAT €97.47. Reading SUBTOTAL as the net would overstate it by the VAT."""
    assert invoice['VAT 23%'] == '97.47'
    assert invoice['Total'] == '119.89'


def test_a_mixed_rate_invoice_splits_by_line():
    text = (
        'INVOICE Berlin Packaging Ireland Ltd\n'
        'Issue Date Oct. 1, 2026\n'
        'Order Number NR#2400\n'
        'ITEM DESCRIPTION QUANTITY UNIT PRICE VAT TAX AMOUNT TOTAL\n'
        'Glass Jar - SKU: X 2 50.00 23% €23.00 €123.00\n'
        'Printed Leaflet - SKU: Y 1 100.00 0% €0.00 €100.00\n'
        'TOTAL EXCL. VAT €200.00\n'
        'VAT (IE VAT) 23% €23.00\n'
        'TOTAL INCL. VAT €223.00\n'
    )
    data = berlin_packaging.parse_invoice(text, 'synthetic.txt')
    assert data['VAT 23%'] == '100.00'
    assert data['VAT 0%'] == '100.00'
    assert data['Total_VAT'] == 23.00
    assert data['Total'] == '223.00'
    assert 'Parse_Warnings' not in data


def test_a_thousands_separator_is_not_truncated():
    """No sample exceeds €1,000 yet. A money pattern that cannot span the comma is how the
    Klee Paper parser silently turned €1,572.82 into €342.80."""
    text = (
        'INVOICE Berlin Packaging Ireland Ltd\n'
        'Issue Date Oct. 1, 2026\n'
        'Order Number NR#2401\n'
        'Pallet of Jars - SKU: Z 100 12.00 23% €280.13 €1,497.83\n'
        'TOTAL EXCL. VAT €1,217.70\n'
        'VAT (IE VAT) 23% €280.13\n'
        'TOTAL INCL. VAT €1,497.83\n'
    )
    data = berlin_packaging.parse_invoice(text, 'synthetic.txt')
    assert data['VAT 23%'] == '1217.70'
    assert data['Total'] == '1497.83'
    assert 'Parse_Warnings' not in data


def test_lines_that_do_not_reconcile_with_the_summary_are_flagged():
    text = (
        'INVOICE Berlin Packaging Ireland Ltd\n'
        'Issue Date Oct. 1, 2026\n'
        'Order Number NR#2402\n'
        'Glass Jar - SKU: X 2 50.00 23% €23.00 €123.00\n'
        'TOTAL EXCL. VAT €999.00\n'
        'VAT (IE VAT) 23% €23.00\n'
        'TOTAL INCL. VAT €1,022.00\n'
    )
    data = berlin_packaging.parse_invoice(text, 'synthetic.txt')
    assert any('states €999.00 excluding VAT' in w for w in data.get('Parse_Warnings', []))


def test_an_unrecognisable_rate_still_contributes_and_warns():
    text = (
        'INVOICE Berlin Packaging Ireland Ltd\n'
        'Issue Date Oct. 1, 2026\n'
        'Order Number NR#2403\n'
        'Odd Item - SKU: Q 1 100.00 17.5% €17.50 €117.50\n'
        'TOTAL EXCL. VAT €100.00\n'
        'TOTAL INCL. VAT €117.50\n'
    )
    data = berlin_packaging.parse_invoice(text, 'synthetic.txt')
    nets = sum(float(data[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert nets == 100.00
    assert data['Total_VAT'] == 17.50
    assert any('needs checking by hand' in w for w in data.get('Parse_Warnings', []))
