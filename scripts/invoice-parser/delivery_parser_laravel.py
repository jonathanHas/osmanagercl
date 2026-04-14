#!/usr/bin/env python3
"""
Delivery Parser for Laravel Integration

Processes delivery invoice PDFs and returns JSON with product line items.
This is used for importing deliveries directly from supplier invoices.
"""

import os
import sys
import json
import argparse
import logging
import traceback
from datetime import datetime

# Add current directory to path for imports
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

# Import utilities
from utils import extract_text

# Import delivery parsers
from parsers.delivery_independent import parse_delivery_pdf as parse_independent
from parsers.delivery_udea import parse_delivery_pdf as parse_udea
from parsers.delivery_natural_medicine import parse_delivery_pdf as parse_natural_medicine

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')


def detect_supplier(text: str) -> tuple:
    """
    Detect supplier from delivery invoice text.

    Returns:
        Tuple of (parser_function, supplier_name)
    """
    upper_text = text.upper()

    if "INDEPENDENT IRISH HEALTH FOODS" in upper_text or "IIHF" in upper_text:
        return parse_independent, "Independent"

    if "UDEA B.V." in upper_text or "WWW.UDEA.NL" in upper_text or "UDEA" in upper_text:
        return parse_udea, "Udea"

    if "THE NATURAL MEDICINE COMPANY" in upper_text or "NATURALMEDICINE.IE" in upper_text:
        return parse_natural_medicine, "Natural Medicine"

    # Default to None - unsupported supplier
    return None, "Unknown"


def process_delivery_pdf(file_path: str, supplier_hint: str = None, verbose: bool = False) -> dict:
    """
    Process a delivery invoice PDF and return structured product data.

    Args:
        file_path: Path to the PDF file
        supplier_hint: Optional supplier name to skip detection
        verbose: Enable verbose logging

    Returns:
        Dictionary with parsed delivery data
    """
    response = {
        'success': False,
        'confidence': 0.0,
        'data': None,
        'errors': [],
        'warnings': [],
        'metadata': {
            'filename': os.path.basename(file_path),
            'parsing_method': 'unknown',
            'supplier_detected': 'Unknown',
            'processing_time': 0.0
        }
    }

    try:
        start_time = datetime.now()

        # Check file exists
        if not os.path.exists(file_path):
            response['errors'].append({
                'code': 'FILE_NOT_FOUND',
                'message': f'File not found: {file_path}'
            })
            return response

        # Validate file extension
        if not file_path.lower().endswith('.pdf'):
            response['errors'].append({
                'code': 'INVALID_FILE_TYPE',
                'message': f'Only PDF files are supported. Got: {file_path}'
            })
            return response

        # Extract text for supplier detection
        text, extraction_method = extract_text(file_path)
        response['metadata']['parsing_method'] = extraction_method

        if not text or not text.strip():
            response['errors'].append({
                'code': 'NO_TEXT_EXTRACTED',
                'message': f'No text could be extracted from {file_path}'
            })
            return response

        # Detect or use supplier hint
        if supplier_hint:
            supplier_name = supplier_hint
            # Map supplier hint to parser
            supplier_parsers = {
                'independent': (parse_independent, 'Independent'),
                'udea': (parse_udea, 'Udea'),
                'natural_medicine': (parse_natural_medicine, 'Natural Medicine'),
            }
            parser_info = supplier_parsers.get(supplier_hint.lower())
            if parser_info:
                parser_func, supplier_name = parser_info
            else:
                parser_func = None
                supplier_name = supplier_hint
        else:
            parser_func, supplier_name = detect_supplier(text)

        response['metadata']['supplier_detected'] = supplier_name

        if parser_func is None:
            response['errors'].append({
                'code': 'UNSUPPORTED_SUPPLIER',
                'message': f'No delivery parser available for supplier: {supplier_name}'
            })
            return response

        # Parse the delivery
        if verbose:
            logging.info(f"Parsing delivery from {supplier_name} using {extraction_method}")

        result = parser_func(file_path, verbose=verbose)

        if result['success']:
            response['success'] = True
            response['data'] = {
                'supplier': result['supplier'],
                'items': result['items'],
                'totals': result['totals'],
                'barrels': result.get('barrels', {'items': [], 'total': 0}),
                'costs': result.get('costs', {'items': [], 'total': 0})
            }

            # Calculate confidence based on validation results
            stats = result.get('metadata', {}).get('stats', {})
            parsed = stats.get('parsed_lines', 0)
            valid = stats.get('price_validations_passed', 0)

            if parsed > 0:
                validation_rate = valid / parsed
                response['confidence'] = round(validation_rate * 100, 1)
            else:
                response['confidence'] = 0.0

            response['warnings'] = result.get('warnings', [])

            # Pass through unmatched lines for user review
            unmatched_lines = result.get('metadata', {}).get('unmatched_lines', [])
            if unmatched_lines:
                response['metadata']['unmatched_lines'] = unmatched_lines

            # Pass through order number from parser
            order_number = result.get('metadata', {}).get('order_number')
            if order_number:
                response['metadata']['order_number'] = order_number

        else:
            response['errors'] = result.get('errors', [])
            response['warnings'] = result.get('warnings', [])

        # Calculate processing time
        end_time = datetime.now()
        response['metadata']['processing_time'] = (end_time - start_time).total_seconds()

    except Exception as e:
        logging.error(f"Error processing {file_path}: {str(e)}")
        logging.error(traceback.format_exc())
        response['errors'].append({
            'code': 'PARSE_ERROR',
            'message': str(e),
            'traceback': traceback.format_exc()
        })

    return response


def main():
    """Main entry point for Laravel integration"""
    parser = argparse.ArgumentParser(description='Parse delivery invoice PDFs for Laravel')
    parser.add_argument('--file', required=True, help='Path to delivery PDF file')
    parser.add_argument('--supplier', help='Supplier name hint (independent, udea, natural_medicine)')
    parser.add_argument('--output', default='json', choices=['json', 'text'],
                       help='Output format (default: json)')
    parser.add_argument('-v', '--verbose', action='store_true', help='Enable verbose output')
    parser.add_argument('--debug', action='store_true', help='Enable debug output')

    args = parser.parse_args()

    if args.debug:
        logging.getLogger().setLevel(logging.DEBUG)
    elif not args.verbose:
        # Suppress info messages for production
        logging.getLogger().setLevel(logging.ERROR)

    # Process the delivery PDF
    result = process_delivery_pdf(args.file, supplier_hint=args.supplier, verbose=args.verbose)

    # Output result
    if args.output == 'json':
        print(json.dumps(result, indent=2, default=str))
    else:
        print(result)

    # Exit with appropriate code
    sys.exit(0 if result['success'] else 1)


if __name__ == '__main__':
    main()
