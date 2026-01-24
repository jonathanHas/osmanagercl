import os
import argparse
import logging
import pdfplumber # For PDF text extraction

# --- Logging Setup ---
# Using basicConfig to set the logging level and format.
# This helps in providing clear information about the script's operations.
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def display_pdf_text_content(pdf_path: str):
    """
    Opens a PDF file using pdfplumber and prints all extracted text from each page.

    Args:
        pdf_path: The full path to the PDF file.
    """
    pdf_filename = os.path.basename(pdf_path)
    logging.info(f"\n--- Processing PDF: {pdf_filename} ---")

    try:
        # Open the PDF file using pdfplumber
        with pdfplumber.open(pdf_path) as pdf:
            if not pdf.pages:
                logging.warning(f"No pages found in {pdf_filename}.")
                return

            logging.info(f"Found {len(pdf.pages)} page(s) in {pdf_filename}.")

            # Iterate through each page in the PDF
            for i, page in enumerate(pdf.pages):
                # Extract text from the current page.
                # x_tolerance and y_tolerance can be adjusted if text extraction is problematic,
                # but default values are often sufficient.
                text = page.extract_text(x_tolerance=2, y_tolerance=2)

                print(f"\n=== Page {i + 1} of {pdf_filename} ===")
                if text:
                    print(text)
                else:
                    print("(No text extracted from this page)")
            logging.info(f"--- Finished processing {pdf_filename} ---")

    except Exception as e:
        # Log any errors encountered during PDF processing.
        logging.error(f"❌ Failed to process {pdf_filename}: {e}", exc_info=True)

def process_all_pdfs_in_folder(pdf_folder: str):
    """
    Scans a folder for PDF files and calls display_pdf_text_content for each.

    Args:
        pdf_folder: The path to the folder containing PDF files.
    """
    logging.info(f"Scanning folder for PDF files: {os.path.abspath(pdf_folder)}")
    pdf_files_found = 0

    # Iterate over all files in the specified directory
    for fname in sorted(os.listdir(pdf_folder)): # sorted() for consistent order
        # Check if the file has a .pdf extension (case-insensitive)
        if fname.lower().endswith(".pdf"):
            pdf_files_found += 1
            full_path = os.path.join(pdf_folder, fname)
            display_pdf_text_content(full_path) # Process and display content

    if pdf_files_found == 0:
        logging.warning("No PDF files found in the specified folder.")
    else:
        logging.info(f"\nProcessed {pdf_files_found} PDF file(s).")

def main():
    """
    Main function to parse command-line arguments and initiate PDF processing.
    """
    parser = argparse.ArgumentParser(
        description="Extract and display raw text content from all PDF files in a specified folder."
    )
    parser.add_argument(
        "pdf_folder",
        help="Folder containing the PDF files to process. Defaults to current directory if not provided.",
        nargs="?",  # Makes the argument optional
        default=os.getcwd()  # Default to current working directory
    )
    parser.add_argument(
        "-v", "--verbose",
        action="store_const",
        dest="loglevel",
        const=logging.DEBUG, # Set log level to DEBUG if -v is used
        default=logging.INFO, # Default log level
        help="Increase output verbosity to show debug logs."
    )

    args = parser.parse_args()
    logging.getLogger().setLevel(args.loglevel) # Set the global logging level

    input_folder = args.pdf_folder

    # Validate if the input folder exists and is a directory
    if not os.path.isdir(input_folder):
        logging.error(f"❌ Input folder not found or is not a directory: {os.path.abspath(input_folder)}")
        return

    process_all_pdfs_in_folder(input_folder)

if __name__ == "__main__":
    main()
