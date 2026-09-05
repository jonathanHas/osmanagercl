"""
Regression tests for the Laravel dispatcher's handling of multi-invoice files.

Coolnagrower send a statement page and several invoices in a single PDF, and their parser
returns one record per page. The dispatcher's loop used to overwrite response['data'] and
response['confidence'] on every pass, so only the last record survived — and because
has_anomalies was rebuilt per record, a clean last record reset the confidence to 0.85 even
though an earlier one had warned.

The archived case is invoice 8777 (2025-09-30): four records totalling €591.00 imported as
their last page alone, €187.00, at full confidence and so auto-created. A file can only
become one invoice downstream, so a multi-record file now keeps every record, reports the
combined total and drops to 0.50 for review.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import invoice_parser_laravel as ipl  # noqa: E402

AUTO_CREATE_THRESHOLD = 0.80  # config('invoices.parsing.auto_create_threshold', 80.0)


def record(total, date='30/09/2025', supplier='Coolnagrower'):
    """A zero-rated parser record worth `total`."""
    return {
        'Supplier': supplier,
        'Invoice Date': date,
        'Tax Free': True,
        'Credit Note': False,
        'VAT 0%': f'{total:.2f}',
        'VAT 9%': '0.00',
        'VAT 13.5%': '0.00',
        'VAT 23%': '0.00',
    }


@pytest.fixture
def run(monkeypatch):
    """Drive process_invoice over a stub parser without touching the filesystem."""
    def _run(records):
        class StubParser:
            @staticmethod
            def parse_invoice(text, filename):
                return records

        monkeypatch.setattr(ipl, 'extract_text', lambda path: ('stub invoice text', 'pdfplumber'))
        monkeypatch.setattr(ipl, 'detect_supplier', lambda text: (StubParser, 'Coolnagrower'))
        return ipl.process_invoice('/nonexistent/statement.pdf')

    return _run


def test_single_record_is_unchanged(run):
    response = run(record(187.00))
    assert response['success'] is True
    assert response['confidence'] == 0.85
    assert response['data']['total_amount'] == 187.00
    assert 'additional_invoices' not in response['data']
    assert response['confidence'] >= AUTO_CREATE_THRESHOLD


def test_a_single_record_in_a_list_is_also_unchanged(run):
    response = run([record(187.00)])
    assert response['confidence'] == 0.85
    assert response['data']['total_amount'] == 187.00
    assert 'additional_invoices' not in response['data']


def test_multi_record_file_keeps_every_record(run):
    # The shape of invoice 8777: a junk page plus three real invoices.
    response = run([record(0.00, 'Page 1'), record(181.00, '09/09/2025'),
                    record(223.00, '16/09/2025'), record(187.00, '30/09/2025')])

    kept = [response['data']] + response['data']['additional_invoices']
    assert len(kept) == 4
    assert sum(r['total_amount'] for r in kept) == 591.00


def test_multi_record_file_does_not_auto_create(run):
    response = run([record(0.00, 'Page 1'), record(181.00, '09/09/2025'),
                    record(223.00, '16/09/2025'), record(187.00, '30/09/2025')])

    assert response['confidence'] == 0.50
    assert response['confidence'] < AUTO_CREATE_THRESHOLD


def test_multi_record_file_reports_the_combined_total(run):
    response = run([record(0.00, 'Page 1'), record(181.00, '09/09/2025'),
                    record(223.00, '16/09/2025'), record(187.00, '30/09/2025')])

    assert any('4 invoices totalling €591.00' in w for w in response['warnings'])


def test_a_clean_last_record_cannot_hide_an_earlier_anomaly(run):
    # This is the exact mechanism that gave invoice 8777 a confidence of 0.85: the first
    # record trips the all-zero-VAT anomaly, the last one is clean.
    response = run([record(0.00), record(187.00)])

    assert any('All VAT base amounts are 0.00' in w for w in response['warnings'])
    assert response['confidence'] == 0.50


def test_warnings_from_every_record_are_surfaced(run):
    response = run([record(0.00, 'Page 1'), record(0.00, 'Page 2')])
    zero_warnings = [w for w in response['warnings'] if 'All VAT base amounts are 0.00' in w]
    assert len(zero_warnings) == 2


def test_parser_warnings_still_demote_a_single_record(run):
    flagged = record(187.00)
    flagged['Parse_Warnings'] = ['Totals block not found']
    response = run(flagged)

    assert response['confidence'] == 0.50
    assert 'Totals block not found' in response['warnings']


def test_stated_vat_is_still_honoured_per_record(run):
    # The dispatcher must keep using the VAT a parser states rather than recomputing it,
    # for each record independently.
    stated = record(0.00)
    stated['VAT 23%'] = '1278.70'
    stated['VAT Amounts'] = {'23': 294.12}
    stated['Total'] = '1572.82'
    response = run(stated)

    assert response['data']['vat_breakdown']['vat_23']['vat'] == 294.12
    assert response['data']['total_amount'] == 1572.82
