# Card Transaction Reconciliation System

## Overview

The Card Transaction Reconciliation System enables comparison of myPOS card terminal transaction exports against POS payment records. It automatically identifies discrepancies including declined transactions, amount mismatches, and orphan transactions that exist in one system but not the other.

**Location**: `/management/card-reconciliation`

**Access**: Requires authenticated user with management access

## Features

### 1. myPOS XLS Import

Upload card transaction exports from myPOS terminals for reconciliation.

**Supported Formats**:
- XLS (Excel 97-2003)
- XLSX (Excel 2007+)

**File Structure** (myPOS format):
- Headers on row 3
- Data starts from row 5
- Columns: Date/Time, TID, Terminal Name, Transaction Type, Reference, Status, Card, Amount, etc.

**Upload Process**:
1. Navigate to Card Transaction Reconciliation page
2. Drag and drop or click to select file
3. System parses and imports transactions
4. Automatic reconciliation runs against POS records
5. Results displayed with statistics

### 2. Intelligent Matching Algorithm

The system uses a confidence-based matching algorithm to pair card transactions with POS payments.

**Confidence Scoring** (out of 100):

| Factor | Points | Criteria |
|--------|--------|----------|
| Amount Match | 0-50 | Exact: 50, Within 1%: 30, Within 5%: 15, Within 10%: 5 |
| Time Match | 0-40 | Within 30s: 40, 1min: 30, 3min: 20, 5min: 15, 10min: 5 |
| Card Type Match | 0-10 | Visa-Visa or MC-MC: 10 |

**Match Thresholds**:
- Confidence >= 80%: Auto-match
- Confidence < 80%: Manual review required

### 3. Transaction Status Categories

| Status | Description | Color |
|--------|-------------|-------|
| **Matched** | Successfully paired with POS payment, amounts match | Green |
| **Mismatch** | Paired with POS payment but amounts differ | Yellow |
| **Declined** | Card transaction was declined (no POS match expected) | Red |
| **Orphan** | Approved card transaction with no POS match found | Gray |
| **Pending** | Not yet processed | Blue |

### 4. Auto-Match Orphans

Batch processing feature to match orphan transactions using extended search criteria.

**Configurable Options**:

| Option | Default | Description |
|--------|---------|-------------|
| Time Window | 30 min | Search window around transaction time (15 min - 2 hours) |
| Min Confidence | 80% | Minimum confidence required to match (70-90%) |
| Exact Amount Only | No | Only match if amounts match exactly (no variance) |
| Card Payments Only | Yes | Only match POS card payments (exclude cash) |

**Usage**:
1. Click "Auto-match" on batch with orphan transactions
2. Configure matching criteria in modal
3. Click "Preview Matches" to see proposed matches
4. Review preview table showing:
   - Card transaction reference and amount
   - POS payment amount
   - Payment method (Card/Cash)
   - Confidence score
   - Variance (if any)
5. Click "Match X Transactions" to confirm

### 5. Manual Matching

For orphan transactions that require manual review:

1. View transactions filtered by "Orphan" status
2. Click "Find Match" on any orphan transaction
3. Modal shows nearby POS payments with:
   - Amount
   - Date/time
   - Confidence score
   - Time difference
4. Click to select and match

### 6. Settings

Configure default matching behavior:

- **Time Window**: Default search window for initial reconciliation (3-15 minutes)
- **Auto-Match Threshold**: Minimum confidence for automatic matching

## Database Schema

### card_transactions

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| upload_batch_id | string | Groups uploaded files |
| source_filename | string | Original file name |
| transaction_datetime | datetime | Card transaction time |
| terminal_id | string | Terminal ID (TID) |
| terminal_name | string | Terminal name |
| transaction_type | string | Payment, Refund |
| transaction_reference | string | Unique transaction reference |
| transaction_status | string | Approved, Declined |
| payment_status | string | Settled, Pending |
| card_masked | string | Masked card number (*5275) |
| amount | decimal(10,2) | Transaction amount |
| currency | string | Currency code (default EUR) |
| settlement_datetime | datetime | Settlement date/time |
| settlement_amount | decimal(10,2) | Settlement amount |
| fee | decimal(10,2) | Transaction fee |
| processor | string | Mastercard, Visa |
| card_type | string | Debit World, Visa Classic |
| auth_code | string | Authorization code |
| reconciliation_status | enum | pending, matched, mismatch, declined, orphan |
| pos_payment_id | string | FK to POS PAYMENTS.ID |
| confidence_score | int | Match confidence (0-100) |
| variance_amount | decimal(10,2) | Amount difference |
| notes | text | User notes |

### card_reconciliation_settings

| Column | Type | Description |
|--------|------|-------------|
| id | int | Primary key |
| user_id | int | FK to users |
| time_window_minutes | int | Default 5 |
| auto_match_threshold | int | Default 80 |

## API Endpoints

### Routes

| Method | Route | Controller Action | Description |
|--------|-------|-------------------|-------------|
| GET | `/management/card-reconciliation` | index | Main page |
| POST | `/management/card-reconciliation` | store | Upload file |
| GET | `/management/card-reconciliation/status/{batchId}` | status | Processing status |
| GET | `/management/card-reconciliation/transactions` | transactions | List transactions |
| POST | `/management/card-reconciliation/match` | match | Manual match |
| POST | `/management/card-reconciliation/unmatch` | unmatch | Remove match |
| GET | `/management/card-reconciliation/nearby-payments` | nearbyPayments | Find nearby POS payments |
| GET | `/management/card-reconciliation/export` | export | Export CSV |
| GET/POST | `/management/card-reconciliation/settings` | settings | Manage settings |
| DELETE | `/management/card-reconciliation/delete` | deleteBatch | Delete batch |
| POST | `/management/card-reconciliation/reprocess` | reprocess | Reprocess batch |
| GET | `/management/card-reconciliation/preview-auto-match` | previewAutoMatch | Preview auto-match |
| POST | `/management/card-reconciliation/auto-match` | autoMatchBatch | Execute auto-match |

## Files

### Service Classes

- `app/Services/MyPosXlsParserService.php` - Parses myPOS XLS format
- `app/Services/CardReconciliationService.php` - Matching logic and reconciliation

### Models

- `app/Models/CardTransaction.php` - Card transaction model with scopes
- `app/Models/CardReconciliationSetting.php` - User settings model

### Controller

- `app/Http/Controllers/Financials/CardReconciliationController.php`

### Views

- `resources/views/financials/card-reconciliation/index.blade.php` - Main page with upload
- `resources/views/financials/card-reconciliation/transactions.blade.php` - Transaction list

### Job

- `app/Jobs/ProcessCardTransactions.php` - Processes uploaded files

## Troubleshooting

### Common Issues

**"Batch not found" after upload**
- The system uses synchronous processing (`dispatchSync`)
- Check Laravel logs for parsing errors
- Verify file format matches myPOS export

**No matches found**
- Increase time window in settings or auto-match options
- Check that card transactions and POS payments are in the same date range
- Verify POS has card payments (`PAYMENT = 'magcard'`)

**Low confidence scores**
- Time differences between card terminal and POS clock
- Amount discrepancies (tips, partial payments)
- Different card type naming conventions

### Debugging

Check logs at `storage/logs/laravel.log` for:
- XLS parsing errors
- Database query issues
- Matching algorithm details

## Best Practices

1. **Regular Reconciliation**: Upload card transaction exports daily or weekly
2. **Review Orphans**: Investigate orphan transactions promptly - may indicate till issues
3. **Monitor Declined**: Track declined transaction patterns for potential card reader issues
4. **Export Records**: Keep CSV exports for accounting records
5. **Adjust Settings**: Fine-tune time window based on your card terminal/POS sync accuracy

## Terminal-Till Mappings

Configure which card terminal should match with which POS till for strict reconciliation.

**Location**: `/management/card-reconciliation/terminal-mappings`

### Why Use Terminal Mappings?

1. **Strict Matching**: Card transactions will ONLY match payments from the mapped till
2. **Orphan Detection**: If a terminal is used at a different till, transactions become orphans for manual review
3. **Operational Clarity**: Track which terminal belongs to which till

### Database Schema

| Column | Type | Description |
|--------|------|-------------|
| id | int | Primary key |
| terminal_id | string | Card terminal TID (e.g., "90282976") - unique |
| terminal_name | string | Friendly name (e.g., "Shop Terminal") |
| pos_host | string | POS till HOST (e.g., "Till 1") |
| is_active | boolean | Active mapping flag |
| timestamps | datetime | Created/updated timestamps |

### Behavior

| Scenario | Behavior |
|----------|----------|
| Terminal has mapping, payment on correct till | Normal match |
| Terminal has mapping, payment on wrong till | Orphan (manual review) |
| Terminal has no mapping | Match any till (existing behavior) |
| Manual "Find Match" | Default to mapped till, option to show all |

### API

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/management/card-reconciliation/terminal-mappings` | View mappings |
| POST | `/management/card-reconciliation/terminal-mappings` | Add/update mapping |
| DELETE | `/management/card-reconciliation/terminal-mappings/{id}` | Remove mapping |

## Related Documentation

- [Bank Reconciliation System](./bank-reconciliation-system.md)
- [Cash Reconciliation System](./cash-reconciliation.md)
- [POS Integration](./pos-integration.md)
