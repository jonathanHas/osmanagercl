#!/usr/bin/env python3
"""
PDF Splitter for Invoice Bulk Upload System
Handles page counting, thumbnail generation, and PDF splitting functionality.
"""

import argparse
import json
import logging
import os
import sys
import tempfile
from pathlib import Path

# Import required libraries
try:
    import pdfplumber
    from pdf2image import convert_from_path
    from PIL import Image
    import io
    import base64
except ImportError as e:
    logging.error(f"Required library not found: {e}")
    sys.exit(1)

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def count_pages(pdf_path):
    """
    Count the number of pages in a PDF file.
    """
    try:
        with pdfplumber.open(pdf_path) as pdf:
            page_count = len(pdf.pages)
            logging.info(f"PDF has {page_count} pages")
            return page_count
    except Exception as e:
        logging.error(f"Failed to count pages in {pdf_path}: {e}")
        return 0

def generate_thumbnails(pdf_path, max_size=(200, 280), quality=85):
    """
    Generate base64-encoded thumbnails for each page in the PDF.
    """
    try:
        # Convert PDF pages to images
        pages = convert_from_path(pdf_path, dpi=150)
        thumbnails = []
        
        for i, page in enumerate(pages):
            try:
                # Resize image to thumbnail size while maintaining aspect ratio
                page.thumbnail(max_size, Image.LANCZOS)
                
                # Convert to base64
                buffer = io.BytesIO()
                page.save(buffer, format='JPEG', quality=quality, optimize=True)
                img_str = base64.b64encode(buffer.getvalue()).decode()
                
                thumbnails.append({
                    'page': i + 1,
                    'data': f"data:image/jpeg;base64,{img_str}",
                    'width': page.width,
                    'height': page.height
                })
                
                logging.info(f"Generated thumbnail for page {i + 1}")
                
            except Exception as e:
                logging.error(f"Failed to generate thumbnail for page {i + 1}: {e}")
                continue
                
        return thumbnails
    except Exception as e:
        logging.error(f"Failed to generate thumbnails for {pdf_path}: {e}")
        return []

def parse_page_range(range_str):
    """
    Parse a page range string like "1", "2-4", etc.
    Returns tuple (start_page, end_page) where pages are 1-indexed.
    """
    if '-' in range_str:
        start, end = range_str.split('-', 1)
        return (int(start.strip()), int(end.strip()))
    else:
        page = int(range_str.strip())
        return (page, page)

def split_pdf(pdf_path, output_dir, page_ranges):
    """
    Split a PDF into multiple files based on page ranges.
    
    Args:
        pdf_path: Path to the source PDF file
        output_dir: Directory to save split PDF files
        page_ranges: List of page range strings (e.g., ['1', '2-3', '4'])
    
    Returns:
        List of paths to created split files
    """
    try:
        os.makedirs(output_dir, exist_ok=True)
        split_files = []
        
        with pdfplumber.open(pdf_path) as pdf:
            total_pages = len(pdf.pages)
            
            for i, range_str in enumerate(page_ranges):
                try:
                    start_page, end_page = parse_page_range(range_str)
                    
                    # Validate page range
                    if start_page < 1 or end_page > total_pages or start_page > end_page:
                        logging.error(f"Invalid page range: {range_str} (PDF has {total_pages} pages)")
                        continue
                    
                    # Create output filename
                    base_name = Path(pdf_path).stem
                    output_filename = f"{base_name}_pages_{range_str.replace('-', '_to_')}.pdf"
                    output_path = os.path.join(output_dir, output_filename)
                    
                    # Extract and save pages
                    with pdfplumber.open(pdf_path) as source_pdf:
                        # pdfplumber uses 0-based indexing
                        page_indices = list(range(start_page - 1, end_page))
                        
                        # Create new PDF with selected pages
                        # Note: pdfplumber doesn't have direct PDF writing capabilities
                        # We'll use PyPDF2 for this part
                        import PyPDF2
                        
                        with open(pdf_path, 'rb') as source_file:
                            pdf_reader = PyPDF2.PdfReader(source_file)
                            pdf_writer = PyPDF2.PdfWriter()
                            
                            # Add selected pages
                            for page_idx in page_indices:
                                if page_idx < len(pdf_reader.pages):
                                    pdf_writer.add_page(pdf_reader.pages[page_idx])
                            
                            # Write to output file
                            with open(output_path, 'wb') as output_file:
                                pdf_writer.write(output_file)
                    
                    split_files.append(output_path)
                    logging.info(f"Created split file: {output_path} (pages {range_str})")
                    
                except Exception as e:
                    logging.error(f"Failed to create split for range {range_str}: {e}")
                    continue
        
        return split_files
    
    except Exception as e:
        logging.error(f"Failed to split PDF {pdf_path}: {e}")
        return []

def main():
    parser = argparse.ArgumentParser(description='PDF Splitter for Invoice System')
    parser.add_argument('--action', choices=['count', 'thumbnails', 'split'], required=True,
                       help='Action to perform')
    parser.add_argument('--file', required=True, help='Path to PDF file')
    parser.add_argument('--output-dir', help='Output directory for split files (required for split action)')
    parser.add_argument('--ranges', help='Comma-separated page ranges (required for split action)')
    
    args = parser.parse_args()
    
    # Validate file exists
    if not os.path.exists(args.file):
        result = {
            'success': False,
            'error': f'File not found: {args.file}'
        }
        print(json.dumps(result))
        return 1
    
    try:
        if args.action == 'count':
            page_count = count_pages(args.file)
            result = {
                'success': True,
                'page_count': page_count
            }
            
        elif args.action == 'thumbnails':
            thumbnails = generate_thumbnails(args.file)
            result = {
                'success': True,
                'thumbnails': thumbnails,
                'total_pages': len(thumbnails)
            }
            
        elif args.action == 'split':
            if not args.output_dir or not args.ranges:
                result = {
                    'success': False,
                    'error': 'Output directory and page ranges are required for split action'
                }
            else:
                page_ranges = [r.strip() for r in args.ranges.split(',')]
                split_files = split_pdf(args.file, args.output_dir, page_ranges)
                result = {
                    'success': True,
                    'split_files': split_files,
                    'split_count': len(split_files)
                }
        
        else:
            result = {
                'success': False,
                'error': f'Unknown action: {args.action}'
            }
            
    except Exception as e:
        result = {
            'success': False,
            'error': str(e)
        }
        logging.error(f"Exception in main: {e}")
    
    print(json.dumps(result))
    return 0 if result.get('success', False) else 1

if __name__ == '__main__':
    sys.exit(main())