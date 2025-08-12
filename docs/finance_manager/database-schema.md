# Database Schema Reference

## Main Database Tables

### cash_reconciliations
Primary table for daily cash reconciliation records.

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| closed_cash_id | string | Link to POS CLOSEDCASH |
| date | date | Reconciliation date |
| till_name | string | Terminal name |
| till_id | string | Terminal identifier |
| cash_50 | integer | Count of €50 notes |
| cash_20 | integer | Count of €20 notes |
| cash_10 | integer | Count of €10 notes |
| cash_5 | integer | Count of €5 notes |
| cash_2 | integer | Count of €2 coins |
| cash_1 | integer | Count of €1 coins |
| cash_50c | integer | Count of 50c coins |
| cash_20c | integer | Count of 20c coins |
| cash_10c | integer | Count of 10c coins |
| note_float | decimal(10,2) | Note float amount |
| coin_float | decimal(10,2) | Coin float amount |
| card | decimal(10,2) | Card total |
| cash_back | decimal(10,2) | Cash back given |
| cheque | decimal(10,2) | Cheque total |
| debt | decimal(10,2) | Account/debt sales |
| debt_paid_cash | decimal(10,2) | Debt paid by cash |
| debt_paid_cheque | decimal(10,2) | Debt paid by cheque |
| debt_paid_card | decimal(10,2) | Debt paid by card |
| free | decimal(10,2) | Free/comp items |
| voucher_used | decimal(10,2) | Vouchers redeemed |
| money_added | decimal(10,2) | Cash added to till |
| total_cash_counted | decimal(10,2) | Physical count total |
| pos_cash_total | decimal(10,2) | POS reported cash |
| pos_card_total | decimal(10,2) | POS reported card |
| variance | decimal(10,2) | Calculated variance |
| created_by | bigint | User ID who created |
| updated_by | bigint | User ID who updated |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Update timestamp |

**Indexes:**
- Primary: id
- Index: date
- Index: till_id
- Index: created_by

### cash_reconciliation_payments
Supplier payments made from till.

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| cash_reconciliation_id | UUID | Foreign key to reconciliation |
| sequence | integer | Order of payment |
| supplier_name | string | Supplier name |
| amount | decimal(10,2) | Payment amount |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Update timestamp |

**Relationships:**
- Belongs to: cash_reconciliations

### cash_reconciliation_notes
Notes and comments for reconciliations.

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| cash_reconciliation_id | UUID | Foreign key to reconciliation |
| note | text | Note content |
| created_by | bigint | User who created note |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Update timestamp |

**Relationships:**
- Belongs to: cash_reconciliations
- Belongs to: users (created_by)

### invoices
Supplier invoices (when implemented).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| invoice_number | string | Unique invoice number |
| supplier_id | bigint | Foreign key to suppliers |
| invoice_date | date | Invoice date |
| due_date | date | Payment due date |
| subtotal | decimal(10,2) | Amount before VAT |
| vat_amount | decimal(10,2) | VAT amount |
| total | decimal(10,2) | Total amount |
| status | enum | paid/unpaid/partial |
| paid_amount | decimal(10,2) | Amount paid |
| payment_date | date | Date paid |
| payment_method | string | How it was paid |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Update timestamp |

### suppliers
Supplier records (accounting).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Supplier name |
| code | string | Supplier code |
| vat_number | string | VAT registration |
| email | string | Contact email |
| phone | string | Contact phone |
| address | text | Full address |
| payment_terms | integer | Days for payment |
| is_active | boolean | Active status |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Update timestamp |

## POS Database Tables (Read-Only)

### RECEIPTS
Transaction headers from POS.

| Column | Type | Description |
|--------|------|-------------|
| ID | string | Receipt ID (primary key) |
| MONEY | string | Till session ID |
| DATENEW | datetime | Transaction datetime |
| ATTRIBUTES | bytea | Additional attributes |
| PERSON | string | Cashier ID |

**Key Points:**
- Links to PAYMENTS via ID
- Links to TICKETS via ID
- Links to CLOSEDCASH via MONEY

### PAYMENTS
Payment details for each receipt.

| Column | Type | Description |
|--------|------|-------------|
| ID | string | Payment ID |
| RECEIPT | string | Foreign key to RECEIPTS.ID |
| PAYMENT | string | Payment type |
| TOTAL | decimal | Payment amount |
| TRANSID | string | Transaction ID |
| RETURNMSG | bytea | Return message |
| TENDERED | decimal | Amount tendered |
| CARDNAME | string | Card type if applicable |
| VOUCHER | string | Voucher ID if used |

**Payment Types:**
- `cash` - Cash payment
- `cashrefund` - Cash refund
- `magcard` - Card payment
- `magcardrefund` - Card refund
- `debt` - Account/credit
- `debtpaid` - Account payment
- `free` - Complimentary
- `voucher` - Voucher payment

### TICKETS
Line items for receipts.

| Column | Type | Description |
|--------|------|-------------|
| ID | string | Ticket ID (matches RECEIPT.ID) |
| TICKETTYPE | integer | Type of ticket |
| TICKETID | integer | Ticket number |
| PERSON | string | Cashier ID |
| CUSTOMER | string | Customer ID |
| STATUS | integer | Ticket status |

### TICKETLINES
Individual product lines.

| Column | Type | Description |
|--------|------|-------------|
| TICKET | string | Foreign key to TICKETS.ID |
| LINE | integer | Line number |
| PRODUCT | string | Product ID |
| ATTRIBUTESETINSTANCE_ID | string | Attributes |
| UNITS | decimal | Quantity |
| PRICE | decimal | Unit price |
| TAXID | string | Tax ID |
| ATTRIBUTES | bytea | Line attributes |

### CLOSEDCASH
Till session records.

| Column | Type | Description |
|--------|------|-------------|
| MONEY | string | Session ID (primary key) |
| HOST | string | Terminal ID |
| HOSTSEQUENCE | integer | Sequence number |
| DATESTART | datetime | Session start |
| DATEEND | datetime | Session end |
| NOSALES | integer | Number of sales |

### PRODUCTS
Product master data.

| Column | Type | Description |
|--------|------|-------------|
| ID | string | Product ID |
| REFERENCE | string | Product code |
| CODE | string | Barcode |
| CODETYPE | string | Barcode type |
| NAME | string | Product name |
| PRICEBUY | decimal | Cost price |
| PRICESELL | decimal | Sell price |
| CATEGORY | string | Category ID |
| TAXCAT | string | Tax category |
| ATTRIBUTESET_ID | string | Attribute set |
| STOCKCOST | decimal | Stock cost |
| STOCKVOLUME | decimal | Stock volume |
| ISCOM | boolean | Is commission |
| ISSCALE | boolean | Is scale item |
| ISCONSTANT | boolean | Is constant |
| PRINTKB | boolean | Print to kitchen |
| SENDSTATUS | boolean | Send status |
| ISSERVICE | boolean | Is service |
| ATTRIBUTES | bytea | Product attributes |
| DISPLAY | string | Display name |
| ISVPRICE | boolean | Variable price |
| ISVERPATRIB | boolean | Verify attributes |
| TEXTTIP | string | Tooltip text |
| WARRANTY | boolean | Has warranty |
| IMAGE | bytea | Product image |
| STOCKUNITS | decimal | Stock units |
| PRINTTO | string | Printer destination |
| SUPPLIER | string | Supplier ID |
| UOM | string | Unit of measure |

## Query Examples

### Daily Sales Summary
```sql
SELECT 
    COUNT(DISTINCT r.ID) as transaction_count,
    SUM(CASE WHEN p.TOTAL >= 0 THEN p.TOTAL ELSE 0 END) as total_sales,
    SUM(CASE WHEN p.PAYMENT = 'cash' THEN p.TOTAL ELSE 0 END) as cash_sales,
    SUM(CASE WHEN p.PAYMENT = 'magcard' THEN p.TOTAL ELSE 0 END) as card_sales
FROM RECEIPTS r
JOIN PAYMENTS p ON r.ID = p.RECEIPT
WHERE DATE(r.DATENEW) = '2025-08-12'
```

### Cash Reconciliation with Variance
```sql
SELECT 
    date,
    total_cash_counted,
    pos_cash_total,
    variance,
    (note_float + coin_float) as total_float
FROM cash_reconciliations
WHERE date = '2025-08-12'
    AND till_id = 'TILL001'
```

### Supplier Payments for Date
```sql
SELECT 
    crp.supplier_name,
    crp.amount,
    cr.date
FROM cash_reconciliation_payments crp
JOIN cash_reconciliations cr ON crp.cash_reconciliation_id = cr.id
WHERE cr.date = '2025-08-12'
ORDER BY crp.sequence
```

## Relationships Diagram

```
cash_reconciliations
    ├── has_many → cash_reconciliation_payments
    ├── has_many → cash_reconciliation_notes
    ├── belongs_to → users (created_by, updated_by)
    └── belongs_to → CLOSEDCASH (via closed_cash_id)

RECEIPTS (POS)
    ├── has_many → PAYMENTS
    ├── has_one → TICKETS
    └── belongs_to → CLOSEDCASH (via MONEY)

TICKETS (POS)
    ├── has_many → TICKETLINES
    └── belongs_to → RECEIPTS

TICKETLINES (POS)
    ├── belongs_to → PRODUCTS
    └── belongs_to → TAXES
```

## Migration Commands

### Create New Financial Table
```bash
php artisan make:migration create_financial_reports_table
```

### Run Migrations
```bash
php artisan migrate
```

### Rollback if Needed
```bash
php artisan migrate:rollback
```

## Performance Indexes

### Recommended Indexes
```sql
-- For cash reconciliations
CREATE INDEX idx_cash_recon_date ON cash_reconciliations(date);
CREATE INDEX idx_cash_recon_till ON cash_reconciliations(till_id);

-- For POS queries
CREATE INDEX idx_receipts_date ON RECEIPTS(DATENEW);
CREATE INDEX idx_payments_receipt ON PAYMENTS(RECEIPT);
CREATE INDEX idx_payments_type ON PAYMENTS(PAYMENT);

-- Composite indexes for common queries
CREATE INDEX idx_receipts_date_money ON RECEIPTS(DATENEW, MONEY);
CREATE INDEX idx_payments_receipt_type ON PAYMENTS(RECEIPT, PAYMENT);
```