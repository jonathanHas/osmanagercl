import pdfplumber
import sys

def extract_with_pdfplumber(pdf_path):
    print(f"Extracting with pdfplumber: {pdf_path}")
    try:
        with pdfplumber.open(pdf_path) as pdf:
            for i, page in enumerate(pdf.pages):
                print(f"\n=== Page {i + 1} ===")
                text = page.extract_text()
                if text:
                    print(text)
                else:
                    print("[No extractable text on this page]")
    except Exception as e:
        print(f"[ERROR] Failed to process {pdf_path}: {e}")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 plumber_test.py path_to_file.pdf")
    else:
        extract_with_pdfplumber(sys.argv[1])
