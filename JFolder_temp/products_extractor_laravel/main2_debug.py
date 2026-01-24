import os
import pdfplumber

def debug_pdf_text_lines(pdf_path):
    with pdfplumber.open(pdf_path) as pdf:
        for page_number, page in enumerate(pdf.pages, start=1):
            print(f"\n=== Page {page_number} ===")
            text = page.extract_text()
            if not text:
                print("No text found.")
                continue

            lines = text.split("\n")
            for i, line in enumerate(lines):
                print(f"Line {i}: {repr(line)}")

if __name__ == "__main__":
    current_dir = os.path.dirname(os.path.abspath(__file__))
    pdf_files = [f for f in os.listdir(current_dir) if f.lower().endswith('.pdf')]

    if not pdf_files:
        print("❌ No PDF files found in this folder.")
    else:
        pdf_path = os.path.join(current_dir, pdf_files[0])
        print(f"📄 Analyzing PDF lines: {pdf_path}")
        debug_pdf_text_lines(pdf_path)
