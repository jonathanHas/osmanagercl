"""
Regression tests for the Dynamis parser.

Dynamis (Groupe Dynamis SAS, Rungis) changed invoice layout between 2026-08-03 and
2026-08-19, from a French-language one to an English-labelled one. The change also flips
the decimal separator from a period to a comma, so the arithmetic silently goes wrong if a
number from one layout is read with the other's rule.

Dynamis supply Ireland from France under the intra-community exemption, so every invoice in
the archive carries no VAT and the whole amount belongs in the 0% bucket. The fixtures are
real extracted invoice text with the IBAN redacted.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import dynamis  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'dynamis')

FIXTURES = [
    '2026-08-19_new_layout.txt',
    '2026-08-03_legacy_produce.txt',
    '2026-08-03_legacy_grocery.txt',
    '2026-05-19_legacy_credit_note.txt',
]


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return dynamis.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def new_layout():
    """FC10000076, 19/08/2026. Three pages, comma decimals, €270 shipping inside the total."""
    return parse('2026-08-19_new_layout.txt')


@pytest.fixture(scope='module')
def legacy_produce():
    """702771, 03/08/2026. Legacy layout, €1 908.62 — period decimals, space thousands."""
    return parse('2026-08-03_legacy_produce.txt')


@pytest.fixture(scope='module')
def legacy_grocery():
    """702772, 03/08/2026. Legacy layout, €137.64 — no thousands separator at all."""
    return parse('2026-08-03_legacy_grocery.txt')


@pytest.fixture(scope='module')
def legacy_credit_note():
    """692317, 19/05/2026. An AVOIR, headed differently from an invoice, total -€64.73."""
    return parse('2026-05-19_legacy_credit_note.txt')


def test_new_layout_header(new_layout):
    assert new_layout['Supplier'] == 'Dynamis'
    assert new_layout['Invoice Number'] == 'FC10000076'
    assert new_layout['Invoice Date'] == '19/08/2026'
    assert new_layout['Credit Note'] is False


def test_new_layout_reads_comma_decimals(new_layout):
    # "Total Amount Incl. 1 844,32 €" — space is the thousands separator and the comma is
    # the decimal point. Read with the legacy layout's rule this would come out as 184432.
    assert new_layout['Total'] == '1844.32'
    assert new_layout['VAT 0%'] == '1844.32'


def test_new_layout_is_tax_free(new_layout):
    # The exemption is worded "« Exonération TVA, art. 262 ter-I ... »" here against
    # "EXONERATION DE TVA, ARTICLE 262 TER I DU CGI." on the legacy layout — accented, and
    # with no "DE". A literal match on the old wording silently returns False.
    assert new_layout['Tax Free'] is True
    assert new_layout['Total_VAT'] == 0.0
    assert new_layout['VAT 23%'] == '0.00'
    assert 'Parse_Warnings' not in new_layout


def test_legacy_reads_period_decimals(legacy_produce):
    # "NET A PAYER EUR 1 908.62" — same space thousands separator, but a period decimal
    assert legacy_produce['Invoice Number'] == '702771'
    assert legacy_produce['Invoice Date'] == '03/08/2026'
    assert legacy_produce['Total'] == '1908.62'
    assert legacy_produce['VAT 0%'] == '1908.62'
    assert legacy_produce['Tax Free'] is True
    assert 'Parse_Warnings' not in legacy_produce


def test_legacy_without_a_thousands_separator(legacy_grocery):
    assert legacy_grocery['Invoice Number'] == '702772'
    assert legacy_grocery['Total'] == '137.64'
    assert 'Parse_Warnings' not in legacy_grocery


def test_legacy_multipage_totals_are_read_from_the_last_page(legacy_produce):
    # The produce invoice repeats "TOTAL HT" / "TOTAL TVA" / "NET A PAYER" on every page
    # with no figures beside them; only the final page carries the amounts.
    assert legacy_produce['Total'] == '1908.62'


def test_credit_note_header_is_recognised(legacy_credit_note):
    # An AVOIR heads with "AVOIR N° 692317 DU 19/05/2026" rather than "FACTURE N° ...".
    # Matching only FACTURE left both the number and the date unparsed.
    assert legacy_credit_note['Invoice Number'] == '692317'
    assert legacy_credit_note['Invoice Date'] == '19/05/2026'
    assert legacy_credit_note['Credit Note'] is True
    assert legacy_credit_note['Total'] == '-64.73'


def test_credit_note_total_is_not_double_negated(legacy_credit_note):
    # NET A PAYER already reads -64.73, so flipping the sign again would make it positive
    assert float(legacy_credit_note['Total']) == -64.73
    assert legacy_credit_note['VAT 0%'] == '-64.73'


def test_a_vat_bearing_invoice_is_flagged_for_review():
    """No Dynamis invoice has ever charged VAT, so there is no sample showing how the rate
    would be laid out. Such an invoice must land in review, not import as zero rated."""
    text = (
        'INVOICE F C 1 0 0 0 0 0 9 9 dated 02/09/2026\n'
        'Total Amount Excl. 1 000,00 €\n'
        'Total VAT Amount 230,00 € Amount Due 1 230,00 €\n'
        'Total Amount Incl. 1 230,00 €\n'
        'Client : M242 - Facture : FC10000099 - Date : 02/09/2026 - Montant : 1230,00 €\n'
    )
    data = dynamis.parse_invoice(text, 'synthetic.txt')
    assert data['Invoice Number'] == 'FC10000099'
    assert any('needs confirming by hand' in w for w in data.get('Parse_Warnings', []))


def test_letter_spaced_header_is_the_fallback_reference():
    """The header prints the reference letter-spaced. If the payment slip at the foot is
    ever missing, the number must still be recoverable."""
    text = (
        'INVOICE F C 1 0 0 0 0 0 7 6 dated 19/08/2026\n'
        'Total Amount Excl. 1 844,32 €\n'
        'Total VAT Amount 0,00 € Amount Due 1 844,32 €\n'
        'Total Amount Incl. 1 844,32 €\n'
        '« Exonération TVA, art. 262 ter-I du code général des impôts »\n'
    )
    data = dynamis.parse_invoice(text, 'synthetic.txt')
    assert data['Invoice Number'] == 'FC10000076'
    assert data['Invoice Date'] == '19/08/2026'
    assert data['Total'] == '1844.32'
    assert data['Tax Free'] is True


def test_a_new_layout_total_that_does_not_reconcile_is_flagged():
    text = (
        'INVOICE F C 1 0 0 0 0 0 9 8 dated 02/09/2026\n'
        'Total Amount Excl. 1 000,00 €\n'
        'Total VAT Amount 0,00 € Amount Due 1 844,32 €\n'
        'Total Amount Incl. 1 000,00 €\n'
    )
    data = dynamis.parse_invoice(text, 'synthetic.txt')
    assert any('amount due states' in w for w in data.get('Parse_Warnings', []))


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_reconciles_net_plus_vat_to_total(fixture):
    data = parse(fixture)
    nets = sum(float(data[k]) for k in ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%'))
    assert round(nets + data['Total_VAT'], 2) == float(data['Total'])


@pytest.mark.parametrize('fixture', FIXTURES)
def test_every_fixture_yields_a_reference_a_date_and_exemption(fixture):
    data = parse(fixture)
    assert data['Supplier'] == 'Dynamis'
    assert data['Invoice Number'] != 'Not found'
    assert data['Invoice Date'] != 'Not found'
    assert data['Tax Free'] is True
