"""
Tests for the deposit (Brl) code on UDEA accounting invoice lines.

On a UdeaFactuur a product line that carries a bottle/jar deposit has the code
after the country: "... 30322 DE 313 0,89 ...". The parser emits it as
line_item["barrel_code"]; lines without the column carry None. The lines are
real pdfplumber output from UdeaFactuur1148558.pdf (date merged with the
article code, as extracted).
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers.invoice_udea import InvoiceUdeaParser  # noqa: E402


def product_lines(*lines):
    return InvoiceUdeaParser()._extract_product_lines('\n'.join(lines))


def test_deposit_line_carries_barrel_code():
    lines = product_lines(
        '24.09.2694761 1 12 200millilitreApple-mango-juice, Luna e Terra Bio-Dynamis3c0h322 DE 313 0,89 1,79 1 46% 10,68'
    )

    assert len(lines) == 1
    assert lines[0]['article_code'] == '94761'
    assert lines[0]['barrel_code'] == '313'
    assert lines[0]['country'] == 'DE'


def test_deposit_line_with_unmangled_quality():
    lines = product_lines(
        '24.09.2633649 1 1 500millilitreYogurt Greek style, Drentse Aa Biologisch 30252 NL 315 2,04 3,49 1 36% 2,04'
    )

    assert len(lines) == 1
    assert lines[0]['article_code'] == '33649'
    assert lines[0]['barrel_code'] == '315'


def test_line_without_deposit_column_has_none():
    lines = product_lines(
        '15.01.2612047 1 12 125gram Blueberry, . Biologisch 30302 CL 2,07 3,59 1 37% 24,84'
    )

    assert len(lines) == 1
    assert lines[0]['article_code'] == '12047'
    assert 'barrel_code' in lines[0]
    assert lines[0]['barrel_code'] is None
