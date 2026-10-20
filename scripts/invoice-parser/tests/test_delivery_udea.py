"""
Tests for the per-line deposit (barrel) code on UDEA delivery notes.

A product line that carries a bottle/jar deposit ends its description with
"<Country> <Brl>", e.g. "... Bio-Dynamisch DE 313". The parser splits the code
off into item["barrel_code"] and reconciles the line units per code with the
"Barrels delivered" section. The descriptions below are real ones from
delivery 132 (pdfplumber garbles included).

Also covers the glued case size repair: pdfplumber sometimes drops the space
between the SKU (case size) and the content ("4012,50gram"), and the line
total identifies the real case size.

And the prices that run into the description: a purchase price glued to the
last word ("Jersey4,63 8,29 5 18,52") is split off in _preprocess_line(), and
a purchase price interleaved with the description ("F3a,1c7tory 6,39 5 46%
19,02") leaves an integer Sale; _repair_shifted_price() recovers it from the
line total when its digits are in the description. An unparsed line with the
"code ordered qty ... money" shape is always listed as unparsed.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from parsers.delivery_udea import DeliveryUdeaParser  # noqa: E402

split = DeliveryUdeaParser.split_barrel_code


@pytest.mark.parametrize('description, expected_desc, expected_code', [
    ('Fruit-juice pineapple, Your OrganBicio Nlogaitsucrhe DE 313',
     'Fruit-juice pineapple, Your OrganBicio Nlogaitsucrhe DE', '313'),
    ('Pasta tricolore, Wisselwaar Biologisch NL 315',
     'Pasta tricolore, Wisselwaar Biologisch NL', '315'),
    ('Red-beet-juice, Luna e Terra Bio-Dynamisch DE 313',
     'Red-beet-juice, Luna e Terra Bio-Dynamisch DE', '313'),
    ('Apple-mango-juice, Luna e TerraBio-Dynamisch DE 313',
     'Apple-mango-juice, Luna e TerraBio-Dynamisch DE', '313'),
    ('Tomato-juice with sea salt, Luna Bei oT-Deyrrnaamisch DE 313',
     'Tomato-juice with sea salt, Luna Bei oT-Deyrrnaamisch DE', '313'),
    ('Mixed-Nuts, Wisselwaar Biologisch NL 315',
     'Mixed-Nuts, Wisselwaar Biologisch NL', '315'),
    ('Mineral-water, Icelandic Glacial Niet-agrarisch NL 313',
     'Mineral-water, Icelandic Glacial Niet-agrarisch NL', '313'),
    ('Rhubarb-Spritzer, Fritz-spritz Biologisch NL 9936',
     'Rhubarb-Spritzer, Fritz-spritz Biologisch NL', '9936'),
    ('Applespritzer, Fritz-spritz Biologisch NL 9936',
     'Applespritzer, Fritz-spritz Biologisch NL', '9936'),
    # pdfplumber dropped the space before the code
    ('Water kefir passion fruit elderflowBeior,lo Lgoisucther NL10046',
     'Water kefir passion fruit elderflowBeior,lo Lgoisucther NL', '10046'),
])
def test_split_barrel_code_real_descriptions(description, expected_desc, expected_code):
    assert split(description) == (expected_desc, expected_code)


@pytest.mark.parametrize('description', [
    'Muesli crunchy, Rapunzel Biologisch NL',
    'Something I NL',
    'Something . NL',
    'Omega 3',
    'Vitamin D3 1000 IU',
])
def test_split_barrel_code_leaves_other_descriptions_alone(description):
    assert split(description) == (description, None)


# Real descriptions where pdfplumber interleaved the quality word with the
# country code, so the "<Country> <Brl>" anchor cannot match.
GARBLED = [
    ('Mineral-water slightly effervescenNt,ie Lt-aangrdapriascrkh Bio-queDllEe 313',
     'Mineral-water slightly effervescenNt,ie Lt-aangrdapriascrkh Bio-queDllEe'),   # 5018611
    ('Mineral-water carbonic acid, lemoBnio,l oLgaisncdhpark Bio-quNeLlle 313',
     'Mineral-water carbonic acid, lemoBnio,l oLgaisncdhpark Bio-quNeLlle'),        # 5018612
    ('Fruit-juice apple pineapple mangBoi,o Ylooguisrc hOrganic NatDurEe 313',
     'Fruit-juice apple pineapple mangBoi,o Ylooguisrc hOrganic NatDurEe'),         # 92491
    ('Fruit-juice pink grapefruit sweetieB, iYolooguirs cOhrganic NatuDreE 313',
     'Fruit-juice pink grapefruit sweetieB, iYolooguirs cOhrganic NatuDreE'),       # 97433
]


@pytest.mark.parametrize('description, expected_desc', GARBLED)
def test_garbled_country_code_splits_when_the_docket_lists_the_code(description, expected_desc):
    assert split(description, {'313', '315', '9936'}) == (expected_desc, '313')


@pytest.mark.parametrize('description, expected_desc', GARBLED)
def test_garbled_country_code_needs_known_codes(description, expected_desc):
    assert split(description) == (description, None)
    assert split(description, set()) == (description, None)
    assert split(description, {'315'}) == (description, None)


def test_country_form_wins_over_known_codes():
    assert split('Pasta tricolore, Wisselwaar Biologisch NL 315', {'313'}) == (
        'Pasta tricolore, Wisselwaar Biologisch NL', '315')


def test_pack_size_is_never_a_deposit_code():
    description = "315 gram Wheat-waffles, Billy's farm Biologisch NL"
    assert split(description, {'315'}) == (description, None)


def test_full_line_parse_then_split():
    parser = DeliveryUdeaParser()
    line = parser._preprocess_line(
        '92504 1 1 12 200millilitre Fruit-juice pineapple, Your OrganBicio Nlogaitsucrhe DE 313 1,18 2,39 5 46% 14,16'
    )
    parsed = parser._parse_line(line)
    assert parsed is not None
    description, code = split(parsed['Description'])
    assert code == '313'
    assert description.endswith('DE')


def test_reconcile_matches_section():
    items = [
        {'code': '1', 'barrel_code': '313', 'total_delivered_units': 12},
        {'code': '2', 'barrel_code': '313', 'total_delivered_units': 6},
        {'code': '3', 'barrel_code': '315', 'total_delivered_units': 6},
        {'code': '4', 'barrel_code': None, 'total_delivered_units': 24},
    ]
    barrels = {'items': [{'code': '313', 'qty': 18}, {'code': '315', 'qty': 6}, {'code': '69', 'qty': 2}], 'total': 0}

    line_units, warnings = DeliveryUdeaParser.reconcile_barrel_codes(items, barrels)

    assert line_units == {'313': 18, '315': 6}
    assert warnings == []


def test_reconcile_warns_on_mismatch_and_missing_code():
    items = [
        {'code': '1', 'barrel_code': '313', 'total_delivered_units': 54},
        {'code': '2', 'barrel_code': '9936', 'total_delivered_units': 6},
    ]
    barrels = {'items': [{'code': '313', 'qty': 60}], 'total': 0}

    line_units, warnings = DeliveryUdeaParser.reconcile_barrel_codes(items, barrels)

    assert line_units == {'313': 54, '9936': 6}
    assert warnings == [
        'Deposit code 313: product lines total 54 units, barrels section says 60',
        'Deposit code 9936: product lines total 6 units, not in the barrels section',
    ]


def _parse(parser, raw):
    parsed = parser._parse_line(parser._preprocess_line(raw))
    assert parsed is not None
    return parsed


def test_repair_glued_case_size_vivani():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, '5004102 1 1 4012,50gram Chocolate-bar milk, Vivani Biologisch DE 0,49 0,89 5 40% 19,60')
    assert parsed['SKU'] == '401'

    note = parser._repair_glued_case_size(parsed)

    assert note is not None
    assert '1×0.49×40=19.60' in note
    assert parsed['SKU'] == '40'
    assert parsed['Content'] == '12,50'


def test_repair_glued_case_size_gingerbeer():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, '5012204 1 1 2027,50centilitresGingerbeer, Naturfrisk Biologisch NL 1,32 2,39 5 40% 26,40')

    note = parser._repair_glued_case_size(parsed)

    assert note is not None
    assert parsed['SKU'] == '20'
    assert parsed['Content'].startswith('27,50')


def test_repair_leaves_a_genuine_discount_alone():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, "5007341 1 1 6 500millilitre All-purpose-cleaner-spray lavender, Marcel's NL 3,99 7,29 5 45% 12,00")

    assert parser._repair_glued_case_size(parsed) is None
    assert parsed['SKU'] == '6'


def test_repair_leaves_a_passing_line_unchanged():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, '92504 1 1 12 200millilitre Fruit-juice pineapple, Your OrganBicio Nlogaitsucrhe DE 313 1,18 2,39 5 46% 14,16')
    before = dict(parsed)

    assert parser._repair_glued_case_size(parsed) is None
    assert parsed == before


JERSEY = '6003103 1 1 4 900millilitre Icecream raspberry & carameliBzeiodlo gailsmchonds, HappDyE Mrs. Jersey4,63 8,29 5 18,52'
SOAP = "5007337 1 1 6 750millilitre All-purpose-cleaner sandalwood N&o cna-frodoadmom, MarceNl'Ls Green Soap2,00 3,99 4 39% 12,00"
WALNUT = '36488 1 1 6 500millilitre Vegan-ice cream banana walnuBti otloofgfiesceh swirl, cashNeLw, Ice Cream3, 1F7actory6,39 5 46% 19,02'
VANILLA = '36489 1 1 6 500millilitre Vegan-ice cream vanilla doughB ciohloogcisoclhate, cashewN,L Ice Cream F3a,1c7tory 6,39 5 46% 19,02'


def _passes(parser, parsed):
    return parser._validate_price(
        float(parsed['Qty']), float(parsed['Price']), float(parsed['SKU']), float(parsed['Total'])
    )[0]


def test_unglue_price_from_description_jersey():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, JERSEY)

    assert parsed['Price'] == '4.63'
    assert parsed['Sale'] == '8.29'
    assert parsed['SKU'] == '4'
    assert parsed['Content'] == '900 millilitre'
    assert _passes(parser, parsed)


def test_unglue_price_marcels_soap():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, SOAP)

    assert parsed['Price'] == '2.00'
    assert parsed['Sale'] == '3.99'
    assert _passes(parser, parsed)
    assert parser._repair_shifted_price(parsed) is None


def test_unglue_both_prices_then_repair_shift():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, WALNUT)
    assert not _passes(parser, parsed)

    note = parser._repair_shifted_price(parsed)

    assert note is not None
    assert parsed['Price'] == '3.17'
    assert parsed['Sale'] == '6.39'
    assert _passes(parser, parsed)


def test_repair_shifted_price_interleaved():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, VANILLA)

    note = parser._repair_shifted_price(parsed)

    assert note is not None
    assert '19.02÷(1×6)=3.17' in note
    assert parsed['Price'] == '3.17'
    assert parsed['Sale'] == '6.39'
    assert _passes(parser, parsed)


def test_repair_shifted_price_leaves_discount_alone():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, "5007341 1 1 6 500millilitre All-purpose-cleaner-spray lavender, Marcel's NL 3,99 7,29 5 45% 12,00")
    before = dict(parsed)

    assert parser._repair_shifted_price(parsed) is None
    assert parsed == before


def test_repair_shifted_price_needs_digits_in_description():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, '36489 1 1 6 500millilitre Vegan-ice cream vanilla, Ice Cream Factory 6,39 5 46% 19,02')
    before = dict(parsed)

    assert parser._repair_shifted_price(parsed) is None
    assert parsed == before


def test_repair_shifted_price_leaves_passing_line_alone():
    parser = DeliveryUdeaParser()
    parsed = _parse(parser, '92504 1 1 12 200millilitre Fruit-juice pineapple, Your OrganBicio Nlogaitsucrhe DE 313 1,18 2,39 5 46% 14,16')
    before = dict(parsed)

    assert parser._repair_shifted_price(parsed) is None
    assert parsed == before


def test_likely_product_line_guard():
    parser = DeliveryUdeaParser()
    # VAT token removed, so the glued price stays glued and every regex fails
    no_vat = parser._preprocess_line(
        '6003103 1 1 4 900millilitre Icecream raspberry & carameliBzeiodlo gailsmchonds, HappDyE Mrs. Jersey4,63 8,29 18,52'
    )
    assert parser._parse_line(no_vat) is None
    assert parser._is_likely_product_line(no_vat)

    unitless = parser._preprocess_line('6003103 1 1 4 Icecream Jersey 4,63 8,29 18,52')
    assert parser._parse_line(unitless) is None
    assert parser._is_likely_product_line(unitless)

    assert not parser._is_likely_product_line('6001624 8 1 330ml Water Kefir Jasmine & Peach, Kult')
    assert not parser._is_likely_product_line(
        '3703 1 8 450gram Green-beans, Natural Cool NV / 28-10-2026 / Niet geleverd leverancier, geen'
    )
