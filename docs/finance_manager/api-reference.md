# Financial API Reference

## Endpoints

### Financial Dashboard

#### GET /management/financial/dashboard
Get comprehensive financial metrics for a specific date.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| date | string | No | Date in YYYY-MM-DD format (defaults to today) |

**Response:**
```json
{
  "date": "2025-08-12",
  "todayMetrics": {
    "sales": 1234.56,
    "refunds": 50.00,
    "net_sales": 1184.56,
    "transactions": 145,
    "avg_transaction": 8.17,
    "cash_sales": 650.00,
    "card_sales": 475.00,
    "debt_sales": 125.00,
    "free_sales": 0,
    "supplier_payments": 150.00,
    "net_cash": 500.00,
    "variance": 12.50,
    "reconciled": true
  },
  "weekMetrics": {
    "net_sales": 8765.43,
    "days_traded": 7,
    "daily_average": 1252.20,
    "supplier_payments": 450.00
  },
  "monthMetrics": {
    "net_sales": 34567.89,
    "last_month_sales": 31852.10,
    "growth": 8.5
  },
  "cashPosition": {
    "current_float": 500.00,
    "last_counted": "2025-08-10",
    "expected_today": 2345.67,
    "days_since_count": 2
  },
  "alerts": [
    {
      "type": "warning",
      "message": "Cash not reconciled for 2 days",
      "action": "/cash-reconciliation"
    }
  ]
}
```

### Cash Reconciliation

#### GET /cash-reconciliation
List all cash reconciliations.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| date | string | No | Filter by date |
| till_id | string | No | Filter by till |
| page | integer | No | Page number |
| per_page | integer | No | Items per page (default 15) |

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "date": "2025-08-12",
      "till_name": "Till 1",
      "total_cash_counted": 2500.00,
      "pos_cash_total": 2487.50,
      "variance": 12.50,
      "created_by": "John Doe",
      "created_at": "2025-08-12T20:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 5,
    "total_items": 75
  }
}
```

#### POST /cash-reconciliation/store
Create a new cash reconciliation.

**Request Body:**
```json
{
  "date": "2025-08-12",
  "till_id": "TILL001",
  "cash_50": 10,
  "cash_20": 15,
  "cash_10": 20,
  "cash_5": 10,
  "cash_2": 25,
  "cash_1": 30,
  "cash_50c": 20,
  "cash_20c": 15,
  "cash_10c": 40,
  "note_float": 200.00,
  "coin_float": 50.00,
  "payments": [
    {
      "supplier_name": "Supplier A",
      "amount": 150.00
    }
  ],
  "note": "Till balanced correctly"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "variance": 12.50,
    "total_counted": 2500.00
  }
}
```

#### GET /cash-reconciliation/previous-float
Get the float from the previous reconciliation.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| date | string | Yes | Current date |
| till_id | string | No | Till ID |

**Response:**
```json
{
  "note_float": 200.00,
  "coin_float": 50.00,
  "total_float": 250.00,
  "last_date": "2025-08-11"
}
```

### Till Review / Receipts

#### GET /till-review
Display till review dashboard.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| date | string | No | Date to review |

#### GET /till-review/summary
Get transaction summary for a date.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| date | string | Yes | Date in YYYY-MM-DD format |

**Response:**
```json
{
  "date": "2025-08-12",
  "summary": {
    "total_sales": 1234.56,
    "total_transactions": 145,
    "cash_total": 650.00,
    "card_total": 475.00,
    "other_total": 59.56,
    "free_total": 0,
    "debt_total": 50.00,
    "drawer_opens": 3,
    "voided_items_count": 2
  }
}
```

#### GET /till-review/transactions
Get detailed transactions.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| date | string | Yes | Transaction date |
| type | string | No | receipt/drawer_opened/line_removed |
| terminal | string | No | Terminal ID |
| cashier | string | No | Cashier name |
| payment_type | string | No | cash/card/debt/free |
| time_from | string | No | Start time (HH:MM) |
| time_to | string | No | End time (HH:MM) |
| page | integer | No | Page number |

**Response:**
```json
{
  "data": [
    {
      "transaction_time": "2025-08-12T10:30:45",
      "transaction_type": "receipt",
      "receipt_number": "RCP001",
      "terminal": "Till 1",
      "cashier": "John",
      "total_amount": 25.50,
      "payment_type": "cash",
      "items": [
        {
          "product": "Coffee",
          "units": 2,
          "price": 3.50,
          "total": 7.00
        }
      ]
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 10
  }
}
```

#### GET /till-review/export
Export transactions to CSV.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| date | string | Yes | Export date |
| type | string | No | Filter by type |

**Response:**
CSV file download with transaction data.

### Invoice Management

#### GET /invoices
List all invoices.

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| status | string | No | paid/unpaid/partial |
| supplier_id | integer | No | Filter by supplier |
| from_date | string | No | Start date |
| to_date | string | No | End date |

#### POST /invoices
Create a new invoice.

**Request Body:**
```json
{
  "invoice_number": "INV-2025-001",
  "supplier_id": 1,
  "invoice_date": "2025-08-12",
  "due_date": "2025-08-26",
  "lines": [
    {
      "description": "Product A",
      "quantity": 10,
      "unit_price": 5.00,
      "vat_rate": 23.0
    }
  ]
}
```

#### POST /invoices/{id}/mark-paid
Mark an invoice as paid.

**Request Body:**
```json
{
  "payment_date": "2025-08-12",
  "payment_method": "bank_transfer",
  "amount": 615.00
}
```

### Supplier Management

#### GET /suppliers
List all suppliers.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Supplier A",
      "code": "SUP001",
      "vat_number": "IE1234567V",
      "total_invoices": 25,
      "total_spend": 12500.00,
      "outstanding_balance": 500.00
    }
  ]
}
```

#### POST /suppliers/{id}/refresh-analytics
Refresh supplier analytics data.

## Authentication

All endpoints require authentication via Laravel session.

**Required Roles:**
- Admin: Full access
- Manager: Access to all financial features
- Employee: Limited based on permissions

**Required Permissions:**
- `financial.dashboard.view` - View dashboard
- `cash_reconciliation.view` - View reconciliations
- `cash_reconciliation.create` - Create reconciliations
- `cash_reconciliation.export` - Export data
- `invoices.view` - View invoices
- `invoices.create` - Create invoices
- `suppliers.view` - View suppliers

## Error Responses

### 401 Unauthorized
```json
{
  "error": "Unauthenticated",
  "message": "Please login to access this resource"
}
```

### 403 Forbidden
```json
{
  "error": "Forbidden",
  "message": "You do not have permission to access this resource"
}
```

### 422 Validation Error
```json
{
  "error": "Validation failed",
  "errors": {
    "date": ["The date field is required"],
    "cash_50": ["The cash 50 field must be an integer"]
  }
}
```

### 500 Server Error
```json
{
  "error": "Server error",
  "message": "An unexpected error occurred"
}
```

## Rate Limiting

API endpoints are rate limited to:
- 60 requests per minute for authenticated users
- 10 requests per minute for specific write operations

## Webhooks (Future)

### Available Events
- `reconciliation.created` - New reconciliation completed
- `reconciliation.variance` - Large variance detected
- `invoice.created` - New invoice added
- `invoice.paid` - Invoice marked as paid
- `daily.summary` - Daily summary available

### Webhook Payload
```json
{
  "event": "reconciliation.variance",
  "timestamp": "2025-08-12T20:30:00Z",
  "data": {
    "reconciliation_id": "uuid",
    "variance": 125.50,
    "threshold": 50.00
  }
}
```

## SDK Examples

### JavaScript/Axios
```javascript
// Get dashboard data
const response = await axios.get('/management/financial/dashboard', {
  params: { date: '2025-08-12' }
});

// Create reconciliation
const reconciliation = await axios.post('/cash-reconciliation/store', {
  date: '2025-08-12',
  cash_50: 10,
  // ... other denominations
});
```

### PHP/Guzzle
```php
$client = new \GuzzleHttp\Client(['base_uri' => 'https://osmanager.local']);

// Get dashboard
$response = $client->get('/management/financial/dashboard', [
    'query' => ['date' => '2025-08-12']
]);

$data = json_decode($response->getBody(), true);
```

### cURL
```bash
# Get dashboard
curl -X GET "https://osmanager.local/management/financial/dashboard?date=2025-08-12" \
  -H "Accept: application/json" \
  -H "Cookie: laravel_session=..."

# Create reconciliation
curl -X POST "https://osmanager.local/cash-reconciliation/store" \
  -H "Content-Type: application/json" \
  -H "Cookie: laravel_session=..." \
  -d '{"date":"2025-08-12","cash_50":10}'
```

## Testing

### Test Endpoints
Test environment endpoints are available at:
`https://test.osmanager.local/api/v1/`

### Test Credentials
```
Username: test_admin
Password: test123
API Key: test_key_123 (future)
```

### Postman Collection
Import the Postman collection from:
`/docs/finance_manager/postman_collection.json`