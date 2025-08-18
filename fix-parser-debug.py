#!/usr/bin/env python3
"""
Fix debug output in parser modules by redirecting print statements to stderr
"""

import os
import re
import sys

def fix_parser_file(filepath):
    """Fix a single parser file by redirecting print statements to stderr"""
    
    with open(filepath, 'r') as f:
        content = f.read()
    
    # Check if file already has sys import
    has_sys_import = 'import sys' in content or 'from sys import' in content
    
    # Add sys import if not present and there are print statements
    if 'print(' in content and not has_sys_import:
        # Find the import section and add sys import
        import_pattern = r'(import\s+\w+.*\n)'
        if re.search(import_pattern, content):
            content = re.sub(import_pattern, r'\1import sys\n', content, count=1)
        else:
            # If no imports, add at the top
            content = 'import sys\n' + content
    
    # Fix print statements - redirect to stderr except those already fixed
    # Pattern to match print statements that don't already have file=sys.stderr
    print_pattern = r'(\s*)print\(([^)]*)\)(?!\s*,\s*file=sys\.stderr)'
    
    def replace_print(match):
        indent = match.group(1)
        args = match.group(2).strip()
        # Only redirect debug/error prints, not regular output
        if '[DEBUG]' in args or '[ERROR]' in args or 'DEBUG' in args or 'ERROR' in args:
            return f'{indent}print({args}, file=sys.stderr)'
        else:
            return match.group(0)  # Leave as-is
    
    content = re.sub(print_pattern, replace_print, content)
    
    # Write back to file
    with open(filepath, 'w') as f:
        f.write(content)
    
    return True

def main():
    parser_dir = '/var/www/html/osmanagercl/scripts/invoice-parser/parsers'
    
    # Get all Python files in parsers directory
    python_files = []
    for root, dirs, files in os.walk(parser_dir):
        for file in files:
            if file.endswith('.py') and file != '__init__.py':
                python_files.append(os.path.join(root, file))
    
    print(f"Found {len(python_files)} parser files to fix:")
    
    fixed_count = 0
    for filepath in python_files:
        try:
            filename = os.path.basename(filepath)
            print(f"  Fixing {filename}...", end=' ')
            
            fix_parser_file(filepath)
            print("✓")
            fixed_count += 1
            
        except Exception as e:
            print(f"✗ Error: {e}")
    
    print(f"\nFixed {fixed_count}/{len(python_files)} parser files")
    print("All parser debug output has been redirected to stderr")

if __name__ == '__main__':
    main()