"""
Tests for the per-line deposit (barrel) code on UDEA delivery notes.

A product line that carries a bottle/jar deposit ends its description with
"<Country> <Brl>", e.g. "... Bio-Dynamisch DE 313". The parser splits the code
off into item["barrel_code"] and reconciles the line units per code with the
"Barrels delivered" section. The descriptions below are real ones from
delivery 132 (pdfplumber garbles included).
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
