# Packaging Structure Documentation

## Overview

The system tracks wholesale case quantities using the `CaseUnits` field to manage inventory ordering and supplier relationships.

## Key Concepts

### CaseUnits
- **Definition**: The number of retail packages in each wholesale case ordered from suppliers
- **Example**: If we order diapers and receive 5 retail packs per wholesale case, CaseUnits = 5
- **Field**: `CaseUnits` in SupplierLink table

## CSV Import Format

The delivery CSV uses the following structure:
```csv
Code,Ordered,Qty,SKU,Content,Description,Price,Sale,Total
5015965,1,1,5,19 pc,"Diaper-Panties size 5",6.22,11.99,31.10
```

Where:
- **Content**: Description of the retail package contents (e.g., "19 pc" = 19 pieces per retail package)
- **SKU**: Number of retail packages per wholesale case (this becomes CaseUnits)
- **Qty**: Quantity received in this delivery
- **Ordered**: Number of wholesale cases ordered

## Database Structure

### SupplierLink Table
- `CaseUnits`: Number of retail packages per wholesale case

### DeliveryItem Table  
- `units_per_case`: Same as CaseUnits, populated from SKU field during CSV import

## Business Logic

### Import Process
1. Use SKU field as CaseUnits (number of retail packages per wholesale case)
2. Content field is descriptive only (e.g., "19 pc" describes the retail package)
3. Store CaseUnits in both SupplierLink and DeliveryItem tables

### UDEA Scraping
- Extracts "x 19" patterns which represent retail package size
- Used for price information only
- Does NOT affect CaseUnits value

### Product Creation
- Simple "Units per Case" field
- Represents wholesale case quantity
- Pre-filled from delivery item when creating from delivery

## Examples

### Example 1: Diapers
- CSV: `5015965,1,1,5,19 pc,"Diaper-Panties..."`
- CaseUnits: 5 (from SKU field)
- Meaning: Each wholesale case contains 5 retail packs
- Retail package: "19 pc" (descriptive only)

### Example 2: Individual Items  
- CSV: `123456,2,2,12,1 unit,"Water Bottle..."`
- CaseUnits: 12 (from SKU field)
- Meaning: Each wholesale case contains 12 individual bottles
- Retail package: "1 unit" (descriptive only)