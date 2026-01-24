# IIH Invoice Parsing Report (v10)
Generated: 2025-11-27 10:17:48

## Summary Statistics
- Total lines processed: 414
- Successfully parsed: 199
- Lines with missing RSP field: 1
- **Price validation warnings: 1**
- Potential missed products: 40
  - High confidence: 0
  - Medium confidence: 10
  - Low confidence: 30
- Success rate: 48.1%

## Files Processed
- Invoice(41).pdf: 5 products
- Invoice(42).pdf: 27 products
- Invoice(43).pdf: 164 products
- Invoice(44).pdf: 3 products

## Common Failure Reasons
- No decimal prices found; Only 5 fields (expected 8); Contains special characters: 10 occurrences
- No decimal prices found; No quantity pattern found; Only 1 fields (expected 8): 10 occurrences
- No decimal prices found: 10 occurrences
- Contains special characters: 5 occurrences
- Only 4 fields (expected 8): 4 occurrences