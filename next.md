# VAT Rate Integration - Clarification Questions

Based on the uniCenta database diagram showing TAXES, TAXCATEGORIES, and PRODUCTS tables, we need to clarify the VAT integration requirements:

## Database Structure Available
- **TAXES table**: Contains VAT rates with ID, NAME, CATEGORY, RATE, etc.
- **TAXCATEGORIES table**: Contains tax category definitions
- **PRODUCTS table**: Has TAXCAT field linking to tax categories

## Questions for Implementation

### 1. VAT Display Requirements
- **Where do you want to show VAT information?**
  - [x] On product listings (alongside price)?
  - [ ] On supplier views?
  - [ ] As separate VAT rate management pages?
  - [x] In product detail views?
  - [ ] All of the above?

### 2. VAT Calculations
- **Do you want to:**
  - [x] Show prices inclusive of VAT?
  - [ ] Show prices exclusive of VAT?
  - [ ] Show both inclusive and exclusive prices?
  - [ ] Calculate VAT amounts automatically?
  - [x] Display effective VAT rates for each product?

### 3. Current Price Interpretation
- **The current PRICESELL field - are these prices:**
  - [ ] Inclusive of VAT (gross prices)?
  - [x] Exclusive of VAT (net prices)?
  - [ ] Need clarification from your POS system?

### 4. Tax Category Usage
- **Should we:**
  - [x] Show the tax category name for each product?
  - [ ] Allow filtering/searching products by VAT rate?
  - [ ] Group products by tax category?
  - [ ] Show tax category in product listings?

### 5. VAT Rate Management
- **Do you need to:**
  - [ ] View/manage the VAT rates themselves?
  - [x] Just display them with products (read-only)?
  - [x] Edit product tax assignments?
  - [ ] View VAT rate history/changes?

### 6. Price Display Preferences
- **How would you like prices displayed?**
  - [ ] "€12.50 (incl. 21% VAT)"
  - [ ] "€10.33 + €2.17 VAT = €12.50"
  - [ ] "€12.50 (€10.33 ex. VAT)"
  - [x] Simple price with VAT rate shown separately
  - [ ] Other format (please specify)

### 7. Business Use Cases
- **Primary use cases for VAT information:**
  - [x] Customer price transparency
  - [x] Accounting/bookkeeping integration
  - [ ] Compliance reporting
  - [x] Product cost analysis
  - [ ] Supplier cost comparison

### 8. Integration Priority
- **Which areas should we implement first?**
  - [x] Product listings with VAT rates
  - [ ] Supplier views with VAT calculations
  - [ ] VAT rate management interface
  - [x] Price calculation utilities

## Implementation Notes
Please mark your preferences above and add any additional requirements or specifications below:

### Additional Requirements:
- 
- 
- 

### Specific VAT Rates in Use:
select * from TAXES;
+-----+--------------------+----------+--------------+----------+-------+-------------+-----------+
| ID  | NAME               | CATEGORY | CUSTCATEGORY | PARENTID | RATE  | RATECASCADE | RATEORDER |
+-----+--------------------+----------+--------------+----------+-------+-------------+-----------+
| 000 | Tax Zero           | 000      | NULL         | NULL     |     0 |             |      NULL |
| 001 | Tax Reduced        | 001      | NULL         | NULL     | 0.135 |             |      NULL |
| 002 | Tax Standard       | 002      | NULL         | NULL     |  0.23 |             |      NULL |
| 003 | Tax Second Reduced | 003      | NULL         | NULL     |  0.09 |             |      NULL |
+-----+--------------------+----------+--------------+----------+-------+-------------+-----------+
4 rows in set (0.000 sec)


### Priority Level:
- [ ] High - Implement immediately
- [ ] Medium - Implement after current features
- [ ] Low - Future enhancement

---
*Please review and mark your preferences, then we can proceed with the implementation plan.*
