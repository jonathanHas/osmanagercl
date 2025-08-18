import pytesseract
from pdf2image import convert_from_path
import csv
import os
import pdfplumber
import xlrd
import logging # Import logging for better debug/info messages

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def extract_text(pdf_path, force_ocr=False):
    """
    Extracts text from a PDF, prioritizing pdfplumber for speed,
    and falling back to OCR if pdfplumber fails or force_ocr is True.
    """
    if not force_ocr:
        try:
            with pdfplumber.open(pdf_path) as pdf:
                # Concatenate text from all pages
                all_text = "\n".join(page.extract_text() or "" for page in pdf.pages)
                if all_text.strip(): # Check if any meaningful text was extracted
                    logging.info("Used pdfplumber for text extraction.")
                    return all_text, "pdfplumber"
        except Exception as e:
            logging.warning(f"pdfplumber failed for {pdf_path}: {e}. Falling back to OCR.")

    logging.info("Using OCR for extraction...")
    try:
        pages = convert_from_path(pdf_path, dpi=400)
        # Use '--psm 3' (default for a single block of text) or '--psm 6' (assume a single uniform block of text)
        # '--psm 6' is often good for invoices as they tend to be structured.
        text = "\n".join(pytesseract.image_to_string(page, config='--psm 6') for page in pages)
        if not text.strip():
            logging.warning(f"OCR extracted no text from {pdf_path}. Check PDF quality or Tesseract installation.")
        return text, "ocr"
    except Exception as e:
        logging.error(f"OCR extraction failed for {pdf_path}: {e}")
        return "", "ocr_failed" # Return empty text and a status indicating failure

def write_to_csv(csv_file, data_dict):
    """
    Appends a dictionary of invoice data to a CSV file.
    Automatically handles writing headers if the file is new or empty.
    The 'FilePath' field is now included.
    """
    # Define all expected fieldnames, including the new 'FilePath' and 'Total'
    fieldnames = [
        'Filename',
        'Supplier',
        'Invoice Date',
        'Tax Free',
        'Credit Note',
        'VAT 0%',
        'VAT 9%',
        'VAT 13.5%',
        'VAT 23%',
        'FilePath'
    ]

    file_exists = os.path.isfile(csv_file) and os.path.getsize(csv_file) > 0

    try:
        with open(csv_file, mode='a', newline='', encoding='utf-8') as csvfile:
            writer = csv.DictWriter(csvfile, fieldnames=fieldnames)

            if not file_exists:
                writer.writeheader() # Write header only if the file is new or empty

            # Write the data row
            writer.writerow(data_dict)
            logging.info(f"Data for '{data_dict.get('Filename', 'N/A')}' written to CSV.")
    except Exception as e:
        logging.error(f"Failed to write to CSV file {csv_file}: {e}")


def check_duplicate_csv(csv_file, filename):
    """
    Checks if a filename already exists in the CSV log.
    """
    logging.debug(f"Checking for duplicate: {filename} in {csv_file}")
    if not os.path.isfile(csv_file):
        logging.debug("CSV file does not exist yet. No duplicates.")
        return False

    try:
        with open(csv_file, mode='r', newline='', encoding='utf-8') as csvfile:
            reader = csv.DictReader(csvfile)
            for row in reader:
                logging.debug(f"Checking row: {row.get('Filename')}")
                if row.get('Filename') == filename:
                    logging.debug("Duplicate found!")
                    return True
        logging.debug("No duplicate found.")
        return False
    except Exception as e:
        logging.error(f"Error reading CSV for duplicate check: {e}")
        return False # Assume no duplicate if we can't read the file

def extract_data_from_xls(filename):
    """
    Extracts all text data from an XLS file sheet by sheet, row by row.
    """
    logging.info(f"Reading XLS: {filename}")
    try:
        workbook = xlrd.open_workbook(filename)
        all_text = ""

        for sheet in workbook.sheets():
            logging.info(f"--- Processing Sheet: {sheet.name} ---")
            for row_idx in range(sheet.nrows):
                row = sheet.row_values(row_idx)
                # Convert all cells in the row to string and join them with a tab
                text_line = "\t".join(str(cell).strip() for cell in row)
                all_text += text_line + "\n"
        return all_text
    except xlrd.biffh.XLRDError as e:
        logging.error(f"Error opening or reading XLS file {filename}: {e}. Is it a valid XLS file?")
        return ""
    except Exception as e:
        logging.error(f"An unexpected error occurred while processing XLS file {filename}: {e}")
        return ""
