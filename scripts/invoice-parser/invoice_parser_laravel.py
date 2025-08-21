#!/usr/bin/env python3
"""
Invoice Parser for Laravel Integration
Processes single invoice files and returns JSON response
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

# Import utilities and parsers
from utils import extract_text, extract_data_from_xls
from parse_doc_file import parse_doc_file
from parsers import (
    dynamis, three, digitalocean, imbibe, openai, linode, jetbrains, independent,
    mossfield, slievebloom, garryhinch, oxigen, kellys, udea, breadelicious,
    kleepaper, ardu, vico, loughboora, coolnagrower, merrymill, flogas, 
    oldyard_organics, amazon, default_parser
)

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def detect_supplier(text):
    """Detect supplier from invoice text"""
    upper_text = text.upper()
    
    if "UDEA B.V." in upper_text or "WWW.UDEA.NL" in upper_text:
        return udea, "Udea"
    elif "AMAZON EU" in upper_text or "VAT DECLARED BY AMAZON" in upper_text:
        return amazon, "Amazon"
    elif "DYNAMIS" in upper_text:
        return dynamis, "Dynamis"
    elif "THREE IRELAND" in upper_text:
        return three, "Three"
    elif "DIGITALOCEAN" in upper_text:
        return digitalocean, "DigitalOcean"
    elif "IMBIBE COFFEE ROASTERS" in upper_text:
        return imbibe, "Imbibe"
    elif "OPENAI" in upper_text:
        return openai, "OpenAI"
    elif "AKAMAI" in upper_text or "LINODE" in upper_text:
        return linode, "Linode"
    elif "JETBRAINS" in upper_text:
        return jetbrains, "JetBrains"
    elif "INDEPENDENT IRISH HEALTH FOODS" in upper_text:
        return independent, "Independent"
    elif "SLIEVE BLOOM ORGANICS" in upper_text:
        return slievebloom, "Slieve Bloom"
    elif "GARRYHINCH WOOD EXOTICS" in upper_text:
        return garryhinch, "Garryhinch"
    elif "OXIGEN COMMERCIAL" in upper_text:
        return oxigen, "Oxigen"
    elif "KELLYS CENTRAL EDUCATIONAL" in upper_text:
        return kellys, "Kellys"
    elif "BREADELICIOUS" in upper_text or "BREAD" in upper_text:
        return breadelicious, "Breadelicious"
    elif "KLEE PAPER" in upper_text or "ECOLAND" in upper_text:
        return kleepaper, "Klee Paper"
    elif "ARDÚ ARTISAN BAKERY" in upper_text or "ARDU ARTISAN BAKERY" in upper_text:
        return ardu, "Ardu"
    elif "VICODEODORANTLIMITED" in upper_text or "VICODEODORANT" in upper_text:
        return vico, "Vico"
    elif "COOLNAGROWER" in upper_text:
        return coolnagrower, "Coolnagrower"
    elif "THE MERRY MILL" in upper_text or "MERRYMOUNT ORGANIC" in upper_text:
        return merrymill, "Merry Mill"
    elif "FLOGAS" in upper_text or "WWW.FLOGAS.IE" in upper_text:
        return flogas, "Flogas"
    elif ("MOSSFIELD ORGANIC FARM" in upper_text or 
          ("MOSSFIELD" in upper_text and "ORGANIC FARM" in upper_text and 
           "ORGANIC STORE" not in upper_text)):
        return mossfield, "Mossfield"
    elif "OLDYARD ORGANICS" in upper_text:
        return oldyard_organics, "Oldyard Organics"
    else:
        return default_parser, "Unknown"

def detect_anomalies(parsed_data, filename):
    """
    Detect anomalous values in parsed invoice data
    Returns: (has_anomalies: bool, warnings: list)
    """
    warnings = []
    
    # Check if all VAT base amounts are 0.00
    vat_base_amounts = [
        float(parsed_data.get('VAT 0%', '0.00')),
        float(parsed_data.get('VAT 9%', '0.00')),
        float(parsed_data.get('VAT 13.5%', '0.00')),
        float(parsed_data.get('VAT 23%', '0.00'))
    ]
    total_vat_base = sum(vat_base_amounts)
    
    if total_vat_base == 0.0:
        warnings.append("All VAT base amounts are 0.00 (possible parsing failure)")
    
    # Check for extreme VAT base amounts
    for rate, amount_str in [('0%', parsed_data.get('VAT 0%', '0.00')), 
                            ('9%', parsed_data.get('VAT 9%', '0.00')), 
                            ('13.5%', parsed_data.get('VAT 13.5%', '0.00')), 
                            ('23%', parsed_data.get('VAT 23%', '0.00'))]:
        amount = float(amount_str)
        if amount > 50000.0:
            warnings.append(f"VAT {rate} base amount unusually high: €{amount:.2f}")
        elif amount < 0.0:
            warnings.append(f"VAT {rate} base amount is negative: €{amount:.2f}")
    
    # Check for missing or invalid invoice date
    invoice_date = parsed_data.get('Invoice Date', '')
    if invoice_date in ['Not found', '', None]:
        warnings.append("Invoice date not found or invalid")
    
    # Check for very low VAT base amounts
    if 0 < total_vat_base < 0.01:
        warnings.append(f"Suspiciously low total VAT base amount: €{total_vat_base:.2f}")
    
    return len(warnings) > 0, warnings

def format_invoice_date(date_str):
    """Convert date string to ISO format for database storage"""
    if not date_str or date_str == 'Not found':
        return None
    
    # Try to parse DD/MM/YYYY format
    try:
        dt = datetime.strptime(date_str, "%d/%m/%Y")
        return dt.strftime("%Y-%m-%d")
    except:
        pass
    
    # Try other formats if needed
    try:
        dt = datetime.strptime(date_str, "%d/%m/%y")
        return dt.strftime("%Y-%m-%d")
    except:
        pass
    
    # Return original if can't parse
    return date_str

def process_invoice(file_path):
    """
    Process a single invoice file and return structured data
    """
    filename = os.path.basename(file_path)
    response = {
        'success': False,
        'confidence': 0.0,
        'data': None,
        'errors': [],
        'warnings': [],
        'metadata': {
            'filename': filename,
            'parsing_method': 'unknown',
            'ocr_used': False,
            'supplier_detected': 'Unknown',
            'processing_time': 0.0
        }
    }
    
    try:
        # Start timing
        start_time = datetime.now()
        
        # Extract text based on file type
        text = ""
        extraction_method = "unknown"
        
        if file_path.lower().endswith('.pdf'):
            logging.info(f"Processing PDF: {filename}")
            text, extraction_method = extract_text(file_path)
            response['metadata']['parsing_method'] = extraction_method
            response['metadata']['ocr_used'] = (extraction_method == 'ocr')
            
        elif file_path.lower().endswith('.doc') or file_path.lower().endswith('.docx'):
            logging.info(f"Processing DOC: {filename}")
            text = parse_doc_file(file_path)
            response['metadata']['parsing_method'] = 'docx'
            
        elif file_path.lower().endswith('.xls'):
            logging.info(f"Processing XLS: {filename}")
            text = extract_data_from_xls(file_path)
            response['metadata']['parsing_method'] = 'xls'
            
        else:
            response['errors'].append({
                'code': 'UNSUPPORTED_FILE_TYPE',
                'message': f'File type not supported: {filename}'
            })
            return response
        
        if not text or not text.strip():
            response['errors'].append({
                'code': 'NO_TEXT_EXTRACTED',
                'message': f'No text could be extracted from {filename}'
            })
            return response
        
        # Detect supplier and parse
        parser, supplier_name = detect_supplier(text)
        response['metadata']['supplier_detected'] = supplier_name
        
        # Special handling for XLS files (Loughboora)
        if file_path.lower().endswith('.xls') and supplier_name != "Loughboora":
            # For XLS files, use loughboora parser
            parsed_data = loughboora.parse_xls(text, filename)
        else:
            parsed_data = parser.parse_invoice(text, filename)
        
        # Ensure it's a list
        if not isinstance(parsed_data, list):
            parsed_data = [parsed_data]
        
        # Process each parsed invoice (usually just one)
        for data in parsed_data:
            # Check for anomalies
            has_anomalies, warnings = detect_anomalies(data, filename)
            if warnings:
                response['warnings'].extend(warnings)
            
            # Format the data for Laravel
            formatted_data = {
                'invoice_number': None,  # Parser doesn't extract this yet
                'invoice_date': format_invoice_date(data.get('Invoice Date')),
                'supplier_name': data.get('Supplier', 'Unknown'),
                'is_tax_free': data.get('Tax Free', False),
                'is_credit_note': data.get('Credit Note', False),
                'vat_breakdown': {
                    'vat_0': {
                        'net': float(data.get('VAT 0%', '0.00')),
                        'vat': 0.00  # 0% VAT rate
                    },
                    'vat_9': {
                        'net': float(data.get('VAT 9%', '0.00')),
                        'vat': float(data.get('VAT 9%', '0.00')) * 0.09 if float(data.get('VAT 9%', '0.00')) > 0 else 0.00
                    },
                    'vat_13_5': {
                        'net': float(data.get('VAT 13.5%', '0.00')),
                        'vat': float(data.get('VAT 13.5%', '0.00')) * 0.135 if float(data.get('VAT 13.5%', '0.00')) > 0 else 0.00
                    },
                    'vat_23': {
                        'net': float(data.get('VAT 23%', '0.00')),
                        'vat': float(data.get('VAT 23%', '0.00')) * 0.23 if float(data.get('VAT 23%', '0.00')) > 0 else 0.00
                    }
                },
                'total_amount': sum([
                    float(data.get('VAT 0%', '0.00')),  # Net amount for 0% VAT
                    float(data.get('VAT 9%', '0.00')) * 1.09,  # Net + 9% VAT
                    float(data.get('VAT 13.5%', '0.00')) * 1.135,  # Net + 13.5% VAT
                    float(data.get('VAT 23%', '0.00')) * 1.23  # Net + 23% VAT
                ])
            }
            
            # Preserve all original parser data by merging with formatted data
            # This ensures custom fields from individual parsers (like Amazon's EUR_VAT_Found) are preserved
            for key, value in data.items():
                # Don't overwrite the formatted Laravel fields
                if key not in ['Invoice Date', 'Supplier', 'Tax Free', 'Credit Note', 'VAT 0%', 'VAT 9%', 'VAT 13.5%', 'VAT 23%']:
                    formatted_data[key] = value
            
            # Specifically handle GBP amounts for Amazon invoices
            if formatted_data.get('Supplier') == 'Amazon':
                # Override total_amount with GBP total if available
                if data.get('GBP_Total') is not None:
                    formatted_data['total_amount'] = float(data['GBP_Total'])
                    formatted_data['currency_displayed'] = 'GBP'
                else:
                    formatted_data['currency_displayed'] = 'EUR'
            
            response['data'] = formatted_data
            response['success'] = True
            response['confidence'] = 0.85 if not has_anomalies else 0.50
        
        # Calculate processing time
        end_time = datetime.now()
        response['metadata']['processing_time'] = (end_time - start_time).total_seconds()
        
    except Exception as e:
        logging.error(f"Error processing {filename}: {str(e)}")
        logging.error(traceback.format_exc())
        response['errors'].append({
            'code': 'PARSE_ERROR',
            'message': str(e),
            'traceback': traceback.format_exc()
        })
    
    return response

def main():
    """Main entry point for Laravel integration"""
    parser = argparse.ArgumentParser(description='Parse invoice files for Laravel')
    parser.add_argument('--file', required=True, help='Path to invoice file')
    parser.add_argument('--output', default='json', choices=['json', 'text'],
                       help='Output format (default: json)')
    parser.add_argument('--debug', action='store_true', help='Enable debug output')
    
    args = parser.parse_args()
    
    if args.debug:
        logging.getLogger().setLevel(logging.DEBUG)
    else:
        # Suppress info messages for production
        logging.getLogger().setLevel(logging.ERROR)
    
    # Check if file exists
    if not os.path.exists(args.file):
        error_response = {
            'success': False,
            'confidence': 0.0,
            'data': None,
            'errors': [{
                'code': 'FILE_NOT_FOUND',
                'message': f'File not found: {args.file}'
            }],
            'warnings': []
        }
        print(json.dumps(error_response, indent=2))
        sys.exit(1)
    
    # Process the invoice
    result = process_invoice(args.file)
    
    # Output result
    if args.output == 'json':
        print(json.dumps(result, indent=2, default=str))
    else:
        print(result)
    
    # Exit with appropriate code
    sys.exit(0 if result['success'] else 1)

if __name__ == '__main__':
    main()