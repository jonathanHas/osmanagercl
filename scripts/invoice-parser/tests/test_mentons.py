"""
Regression tests for the Menton's Organic Farm parser.

Two faults these tests pin down. The parser used to divide the total by 1.23 and book the
result as standard-rated whenever the text did not literally say "tax free", inventing
input VAT on a supplier whose 577 archived invoices are all zero-rated. And it took the
first digit run anywhere in the document as the total, which on OCR'd handwriting is noise:
three archived invoices read as €7.00, €7.00 and €17.00 against stated totals of €232.00,
€232.00 and €58.00.

Menton's photograph handwritten invoices, so the fixtures are the real Tesseract output for
those three — genuinely illegible, which is the point. A total that cannot be read is now
reported as a warning so the file goes to review instead of being created from a number
found by accident.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers import mentons  # noqa: E402

FIXTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fixtures', 'mentons')

RATE_KEYS = ('VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%')


def parse(fixture):
    with open(os.path.join(FIXTURE_DIR, fixture), encoding='utf-8') as fh:
        return mentons.parse_invoice(fh.read(), fixture)


@pytest.fixture(scope='module')
def noise_read_as_7():
    """Invoice 9394, 2026-05-25. Stated €232.00; the old parser read €7.00 from the noise."""
    return parse('2026-05-25_ocr_noise_read_as_7.txt')


@pytest.fixture(scope='module')
def noise_read_as_7_again():
    """Invoice 9445, 2026-06-07. Stated €232.00, same failure."""
    return parse('2026-06-07_ocr_noise_read_as_7.txt')


@pytest.fixture(scope='module')
def noise_read_as_17():
    """Invoice 9069, 2025-04-17. Stated €58.00; the old parser read €17.00.

    Tesseract reads "€5/2" out of this one's handwriting, so even an amount carrying a euro
    sign is not trustworthy here.
    """
    return parse('2025-04-17_ocr_noise_read_as_17.txt')


@pytest.mark.parametrize('fixture', [
    '2026-05-25_ocr_noise_read_as_7.txt',
    '2026-06-07_ocr_noise_read_as_7.txt',
    '2025-04-17_ocr_noise_read_as_17.txt',
])
def test_illegible_invoices_report_nothing_rather_than_noise(fixture):
    parsed = parse(fixture)
    assert parsed['VAT 0%'] == '0.00'
    assert parsed['Total'] == '0.00'
    # The warning is what sends the file to review instead of letting it auto-create
    assert any('No total could be read' in w for w in parsed['Parse_Warnings'])


def test_the_archived_invoice_read_as_seven_euro(noise_read_as_7):
    assert float(noise_read_as_7['Total']) == 0.0


def test_a_euro_sign_alone_is_not_enough(noise_read_as_17):
    # "€5/2" appears in this fixture. The old parser's first-number-anywhere rule gave
    # €17.00; accepting any euro-signed amount would give €5.00. Both are wrong.
    assert '€' in open(
        os.path.join(FIXTURE_DIR, '2025-04-17_ocr_noise_read_as_17.txt'), encoding='utf-8'
    ).read()
    assert noise_read_as_17['Total'] == '0.00'


@pytest.mark.parametrize('fixture', [
    '2026-05-25_ocr_noise_read_as_7.txt',
    '2026-06-07_ocr_noise_read_as_7.txt',
    '2025-04-17_ocr_noise_read_as_17.txt',
])
def test_no_vat_is_ever_invented(fixture):
    parsed = parse(fixture)
    assert parsed['VAT 23%'] == '0.00'
    assert parsed['VAT 9%'] == '0.00'
    assert parsed['VAT 13.5%'] == '0.00'
    assert parsed['Total_VAT'] == 0.0
    assert parsed['VAT Amounts'] == {'0': 0.0, '9': 0.0, '13.5': 0.0, '23': 0.0}


def test_a_legible_calculation_line_is_zero_rated():
    # The old parser booked 200.10 / 1.23 = 162.68 as standard-rated, claiming €37.42 of
    # input VAT that Menton's never charged.
    parsed = mentons.parse_invoice('12/03/2026\n34.5 ells @ 5.80 = 200.10\n', 'legible.jpg')
    assert parsed['VAT 0%'] == '200.10'
    assert parsed['VAT 23%'] == '0.00'
    assert parsed['Tax Free'] is True
    assert parsed['Invoice Date'] == '12/03/2026'
    assert 'Parse_Warnings' not in parsed


def test_a_labelled_total_is_accepted():
    parsed = mentons.parse_invoice('12/03/2026\nTotal: 232.00\n', 'legible.jpg')
    assert parsed['VAT 0%'] == '232.00'
    assert 'Parse_Warnings' not in parsed


def test_a_labelled_total_spans_a_thousands_separator():
    parsed = mentons.parse_invoice('12/03/2026\nTotal: 1,232.50\n', 'legible.jpg')
    assert parsed['VAT 0%'] == '1232.50'
    assert float(parsed['Total']) > 1000


def test_everything_lands_in_the_zero_rate_bucket():
    parsed = mentons.parse_invoice('12/03/2026\nTotal: 232.00\n', 'legible.jpg')
    assert sum(float(parsed[k]) for k in RATE_KEYS) == 232.00
    assert parsed['VAT 0%'] == '232.00'


def test_an_invoice_mentioning_vat_is_flagged_for_checking():
    parsed = mentons.parse_invoice('12/03/2026\nTotal: 232.00\nVAT 23%\n', 'legible.jpg')
    assert any('mentions VAT' in w for w in parsed['Parse_Warnings'])
