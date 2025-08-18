#!/usr/bin/env python3
"""
Fix debug output in parser modules - version 2
"""

import os
import re

def fix_parser_file(filepath):
    """Fix duplicate file=sys.stderr and malformed f-strings"""
    
    with open(filepath, 'r') as f:
        content = f.read()
    
    # Fix duplicate file=sys.stderr arguments
    content = re.sub(r', file=sys\.stderr, file=sys\.stderr', ', file=sys.stderr', content)
    
    # Fix malformed f-strings like: {line.strip(, file=sys.stderr)
    # This happens when the regex incorrectly replaced inside f-strings
    content = re.sub(r'\{([^}]*), file=sys\.stderr\)', r'{\1}', content)
    
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
    
    print(f"Fixing {len(python_files)} parser files:")
    
    fixed_count = 0
    for filepath in python_files:
        try:
            filename = os.path.basename(filepath)
            print(f"  Fixing {filename}...", end=' ')
            
            fix_parser_file(filepath)
            
            # Test syntax
            with open(filepath, 'r') as f:
                try:
                    compile(f.read(), filepath, 'exec')
                    print("✓")
                    fixed_count += 1
                except SyntaxError as e:
                    print(f"✗ Syntax error: {e}")
                
        except Exception as e:
            print(f"✗ Error: {e}")
    
    print(f"\nFixed {fixed_count}/{len(python_files)} parser files")

if __name__ == '__main__':
    main()