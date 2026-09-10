# Order Generation System

This document covers the intelligent order generation system designed to streamline weekly supplier orders, particularly for Udea.

## Overview

The order generation system automates the calculation of required stock quantities based on sales history, current inventory levels, and configurable business rules. It's designed to reduce the time spent on weekly orders from hours to minutes while maintaining accuracy and reducing waste.

## Core Features

### 1. Automated Quantity Calculation
- **Sales-Based Forecasting**: Uses an imported weekly sales history window (default 8 weeks, configurable per order) backed by `sales_daily_summary`
- **Stock Integration**: Real-time current stock levels from STOCKCURRENT table
- **Safety Stock & Coverage**: Configurable safety stock levels (default: 1.5 weeks supply) merged with the user-selected coverage window (e.g. cover through a specific date)
- **Reorder Point Logic**: `Suggested Qty = (Weekly Avg × Target Coverage Weeks) - Current Stock` with target coverage = `max(user coverage, safety factor)`

### 2025-09 Enhancements
- **Flexible Coverage Controls**: The order creation form now captures *Delivery Date*, *Cover inventory until*, and *Weeks of sales to analyse*. These values are persisted on `order_sessions` (`coverage_days`, `coverage_ends_on`, `sales_history_weeks`) and drive the suggestion engine.
- **Sales Import Acceleration**: `OrderService` consults `sales_daily_summary` (populated via `/sales-import` or the CLI import commands) for both weekly breakdowns and aggregate stats. If summaries are missing we gracefully fall back to POS live data.
- **Automatic Sales Import Kickoff**: Submitting the order wizard now attempts to run the daily sales import for any missing days (up to yesterday) so that `sales_daily_summary` stays fresh without requiring the operator to visit `/sales-import` first. Failures are logged and surfaced as a warning, but they no longer block order generation.
- **Rich Review Layout**: The shared Blade partial `orders/partials/review-table` powers both the live `/orders/{order}` screen and the Vico mockup. It introduces:
  - Global chart scaling, percentage grid lines, and condensed captions directly under each trend line.
  - A default sort by recent sales volume, with alternate sort modes (name, priority, value).
  - A toggle to hide/show unordered items (`show_all` query parameter); when hidden only products with a positive ordered quantity are displayed.
  - Inline comparisons between suggested vs final quantities, ensuring the “After” stock bars reflect final user adjustments.

### 2025-10 Enhancements
- **Category-Specific Coverage Overrides**: Suppliers with curated groups (initially Udea’s cheese and refrigerated catalogues, now including Independent’s refrigerated range) expose additional “cover until” fields during order generation. The values are persisted to `order_sessions.coverage_overrides` and reapplied whenever the session is regenerated.
- **Interactive Coverage Editing**: Draft orders now surface the per-category windows on the review screen, allowing buyers to tweak short-dated categories and trigger an in-place recalculation without affecting longer-life products.
- **Scoped Regeneration**: Updating a category from the review header recalculates only the matching products, preserving quantities the buyer already adjusted in other groups while keeping totals in sync.
- **Snappier Adjustments**: Category edits reuse the existing order rows when possible and only fetch supplier products that belong to the affected groups, dramatically reducing regeneration time on large catalogues.
- **Unified Review Graph**: The order review chart now plots current stock and projected stock directly onto the sales trend, eliminating the old “tank” bars and ensuring all values share the same scale.

### 2025-11 Enhancements
- **Refined Product Panel Layout**: The review table trims non-essential legends from each chart cell and elevates the current/after stock figures with larger typography, keeping the focus on actionable quantities.
- **Supplier Code Utilities**: Each row now exposes the linked supplier code with a one-click copy affordance, reducing the friction of sharing codes with supplier portals or support teams.
- **Priority Shortcuts**: Inline priority selectors let buyers mark items as 🔴 review, 🟡 standard, or 🟢 safe. Choosing safe persists to `product_order_settings`, flips the row to auto-approved, and influences future order suggestions.
- **Smarter Case Formatting**: Suggested and ordered case quantities collapse cleanly to whole numbers (e.g. `1` instead of `1.000`) while still displaying fractional values when buyers adjust partial cases.

### 2025-12 Enhancements (Performance Optimization)
- **Bulk Pre-Fetching**: Eliminated N+1 query problem by pre-fetching all data before the product loop. Reduced database queries from 3,000-5,000+ (for large orders) down to ~10-20 queries.
- **Collection groupBy() Optimization**: Changed collection filtering from O(n×m) to O(1) hashmap lookups, reducing weekly sales processing from 70 seconds to 344ms (203x faster).
- **Pre-Aggregated Data**: Order generation now uses `sales_daily_summary` table instead of querying the massive STOCKDIARY table directly, yielding 1,316x faster last-sale-date lookups.
- **Christmas Comparison Fix**: Bulk Christmas window comparison now properly includes weekly breakdown data for chart visualization.
- **Large Order Support**: Orders with 1,400+ products now complete in ~22 seconds instead of timing out at 30 seconds or taking 149+ seconds.
- **New Bulk Repository Methods**: Added `getBulkProductSalesStatistics()`, `getBulkProductWeeklySales()`, `getBulkChristmasWindowComparison()`, `getBulkRecentPurchasePrices()`, `getBulkProductSalesHistory()`, `getBulkLastSaleDates()`, and `getBulkInternalCustomerWeeklySales()` to `SalesRepository`.

### 2025-12-09 Enhancements (Order Review UI)
- **Supplier Website Links**: "View →" links next to supplier codes for Udea and Independent Health Foods products, opening supplier's product search page in new tab.
- **Destock/Restock Toggle**: Quick stock management controls on order review pages. Red "Destock" button removes products from future orders, green "Restock" button adds them back. Includes confirmation dialog with clear messaging.
- **Sales Chart Modal (Christmas)**: The expandable sales history modal (click chart to view extended history) is now available on Christmas review pages, with date range controls (+/- 1 month, +/- 2 months) and statistics bar.

### 2025-01 Enhancements (Internal Customer Sales Tracking)
- **Coffee & Kitchen Department Tracking**: Order review charts now display internal customer transfers alongside regular sales. This helps buyers distinguish between retail sales and internal department usage (e.g., products sent to the coffee bar or kitchen).
- **Visual Distinction**:
  - Purple solid line (☕): Coffee department transfers
  - Orange dashed line (🍳): Kitchen department transfers
  - Blue line: Total sales (as before)
- **Optimized Performance**: Both Coffee and Kitchen data are fetched in a single database query using `getBulkInternalCustomerWeeklySales()`, ensuring no additional performance impact for large suppliers like Udea and Independent.
- **Interactive Tooltips**: Hovering over chart points shows contextual information with emoji indicators (☕ for coffee transfers, 🍳 for kitchen transfers).
- **Modal Chart Support**: The expanded sales history modal (click any chart) also displays Coffee and Kitchen lines when data exists.
- **Universal Availability**: Feature works for all suppliers, not just specific ones.

### 2. Product Classification System
Products are classified into three review priority levels:

#### 🟢 Safe to Order (Auto-Approve)
- Long shelf life case products (>30 days)
- Consistent sales patterns (low variance)
- No recent waste
- Stable supplier relationships

#### 🟡 Standard Review
- Default classification for most products
- Requires quick verification but minimal analysis
- Moderate shelf life and sales patterns

#### 🔴 Requires Review
- Short shelf life (<7 days)
- High-value items
- Volatile demand patterns
- New products without sales history
- Products with recent waste issues

Use the **Priority** dropdown on each order row to override a product’s classification in real time. Safe classifications automatically enable auto-approval and are remembered for subsequent orders via `product_order_settings`.

### 3. Smart Interface Design

#### Two-View System
1. **Cases View**: For bulk items with long shelf life
   - Quick review interface
   - Emphasis on case quantities
   - Minimal detail required

2. **Units View**: For fresh/perishable items
   - Detailed review interface
   - Shelf life considerations
   - Individual unit quantities

#### Priority-Based Workflow
1. Start with "Requires Review" items (critical attention)
2. Quick scan "Standard" items (verify suggestions)
3. Auto-approved items (pre-selected, glance only)

### 2026-09 Enhancements (Order Comparison & Difference Orders)

Two generated orders can now be compared side by side, and the gap between them turned into a new
draft order in one click — the workflow for trimming an oversized order and placing the remainder
separately.

- **Comparison** (`/orders/compare?a=X&b=Y`): tick two orders on `/orders`, then **Compare**. Splits
  the sessions into *Only in A*, *Only in B*, *quantity changed*, and a collapsed *identical* list.
- **Order the difference**: creates a draft holding, per product, what one order has over the other.
  Both directions offered. Case products round up to whole cases; item cost, case size, priority and
  the `context_data` sales snapshot are copied from the source item rather than recalculated.
- Throughout, **"ordered" means `final_quantity > 0`** — a session holds a candidate row for every
  supplier product, most at quantity zero (session #1: 1,504 rows, 353 actual orders). The predicate
  lives in one place, `OrderService::orderedItemsByProduct()`.

📖 **[Order Comparison & Difference Orders](./order-comparison.md)** — full documentation, including
the rounding rules, what the new order inherits, and the regeneration caveat.

### 2026-09-09 Enhancements (Udea Pallet Fill)

Udea orders show a **running pallet-space total** at the top of `/orders/{order}`, so an order can be
built to fill whole pallets rather than discovering the shortfall after it is placed.

- `−`/`+` steppers for **Europallets** and **blockpallets**, a fill bar and a live percentage that
  recalculates as quantities change. Mirrors Udea's own calculator, including
  `totalPalletVolume = numEuro × 250 + numBlock × 360` and its **per-line** rounding.
- Renders only for Udea suppliers (5, 44, 85) via `<x-udea-pallet-summary>`; other suppliers see
  nothing. `/orders/create` has no total — it is the generation form and has no line items yet.
- Both `review-table` and `review-table-christmas` emit `data-pallet-volume` on each quantity input;
  the component sums `value × data-pallet-volume`. The quantity used is the input value — **cases for
  case products, units otherwise** — matching what `OrderService::exportToCsv()` sends to Udea.
- Lines with a quantity but no pallet data are counted and flagged, so an understated total is always
  visible as such.
- **The A2 and Grid layouts have no pallet bar** — they carry their own quantity markup rather than
  sharing `review-table`.

📖 **[Udea Pallet Volumes](../udea-pallet-volumes.md)** — where the data comes from, the sync page,
and why it can only be read from the Udea basket.

### 4. Learning System
The system tracks manual adjustments to improve future suggestions:
- Records user modifications to suggested quantities
- Identifies patterns in adjustments
- Applies learned patterns to future orders
- Confidence scoring based on historical accuracy

## Database Schema

### order_sessions
```sql
- id (primary key)
- user_id (foreign key to users)
- supplier_id (foreign key to SUPPLIERS)
- order_date (target delivery date)
- coverage_days (int, derived from delivery → cover-until)
- coverage_ends_on (date, nullable)
- coverage_overrides (json, nullable; keyed by category group)
- status (draft, submitted, completed)
- total_items (count)
- total_value (decimal)
- sales_history_weeks (tinyint)
- created_at, updated_at
```

### order_items
```sql
- id (primary key)
- order_session_id (foreign key)
- product_id (foreign key to PRODUCTS)
- suggested_quantity (decimal)
- final_quantity (decimal)
- unit_cost (decimal)
- total_cost (decimal)
- review_priority (safe, standard, review)
- adjustment_reason (text, nullable)
- auto_approved (boolean)
- created_at, updated_at
```

### order_adjustments
```sql
- id (primary key)
- product_id (foreign key)
- user_id (foreign key)
- original_quantity (decimal)
- adjusted_quantity (decimal)
- adjustment_factor (decimal)
- context_data (JSON - stock levels, sales, etc.)
- order_date (date)
- created_at
```

### product_order_settings
```sql
- id (primary key)
- product_id (foreign key)
- review_priority (enum: safe, standard, review)
- auto_approve (boolean)
- safety_stock_factor (decimal, default 1.5)
- min_order_quantity (decimal, nullable)
- max_order_quantity (decimal, nullable)
- notes (text, nullable)
- last_updated (timestamp)
```

## Implementation Components

### 1. OrderService
Central service for order logic:
```php
class OrderService {
    public function generateOrderSuggestions($supplierId, $orderDate)
    public function calculateSuggestedQuantity($product)
    public function classifyProductPriority($product)
    public function applyLearningAdjustments($product, $baseQuantity)
    public function validateOrderConstraints($orderItems)
}
```

### 2. OrderController
Web interface and API endpoints:
```php
class OrderController {
    public function index()           // Order list/dashboard
    public function create()          // New order generation
    public function store()           // Save order session
    public function show($id)         // Review order details
    public function export($id)       // CSV export for supplier
    public function adjustQuantity()  // AJAX quantity updates
}
```

### 3. Order Generation Process

#### Step 1: Initialize Order Session
1. Select supplier (defaults to Udea)
2. Set target delivery date
3. Specify coverage end date and sales history window (defaults: cover three weeks, analyse eight weeks)
4. Create draft order session (coverage metadata persisted on the session)

#### Step 2: Calculate Suggestions
1. Query all products for selected supplier
2. Get sales history (4-week rolling average)
3. Get current stock levels
4. Apply reorder point formula
5. Apply product-specific settings
6. Apply learned adjustments
7. Classify review priority

#### Step 3: Present for Review
1. Group by review priority
2. Sort by importance within groups
3. Display in appropriate interface (cases/units)
4. Allow real-time adjustments

#### Step 4: Finalize and Export
1. Validate all quantities
2. Calculate totals
3. Export to supplier format (CSV)
4. Save adjustments for learning
5. Mark session as submitted

## User Interface Design

### Order Dashboard
- List of recent orders
- Quick actions (duplicate last order, generate new)
- Performance metrics (time saved, accuracy)

### Order Generation Interface
```html
<!-- Priority tabs -->
<div class="order-tabs">
    <button class="tab urgent" data-filter="review">
        🔴 Requires Review (12 items)
    </button>
    <button class="tab standard" data-filter="standard">
        🟡 Standard (84 items)
    </button>
    <button class="tab safe" data-filter="safe">
        🟢 Auto-Approved (156 items)
    </button>
</div>

<!-- View toggle -->
<div class="view-toggle">
    <button class="view-btn active" data-view="cases">Cases</button>
    <button class="view-btn" data-view="units">Units</button>
</div>

<!-- Order items table -->
<div class="order-items">
    <!-- Contextual information per item -->
    <!-- Quick adjustment controls -->
    <!-- Running totals -->
</div>
```

### Key UI Features
- **Real-time totals**: Order value updates as quantities change
- **Bulk actions**: "Approve all safe items", "Add 10% to dairy"
- **Quick filters**: Hide items with sufficient stock
- **Context indicators**: Stock days remaining, recent sales trend
- **Confidence scores**: Visual indication of suggestion reliability

## Export Formats

### Udea CSV Format
```csv
Code,Cases,Units,Content,Description,Price,Sale,Total
115,1.000,12.000,"Case of 12","Broccoli, Biologisch Klasse I NL",3.17,3.17,38.04
```

- Rows are sorted by case quantity (ascending) so buyers can identify the smallest case picks quickly.
- Case columns collapse to `0` for unit-only products while the Units column always reflects the final quantity.
- `SKU` and `Ordered` are no longer exported; suppliers rely on their own codes (`Code`) plus the content/description data.

### Custom Formats
- Excel export with formulas
- PDF order sheets for printing
- Email templates for supplier communication

## Configuration Options

### System Settings
- Default safety stock factor (1.5 weeks)
- Minimum order thresholds
- Auto-approve criteria
- Learning algorithm sensitivity

### Product-Specific Settings
- Individual safety stock factors
- Min/max order quantities
- Review priority overrides
- Supplier-specific rules

## Performance Considerations

### Current Performance (December 2025)
| Order Size | Previous Time | Current Time | Improvement |
|------------|---------------|--------------|-------------|
| Small (4 products) | ~400ms | ~350ms | 1.1x |
| Medium (100 products) | ~30 seconds | ~3 seconds | 10x |
| Large (1,400+ products) | 149 seconds (timeout) | **22 seconds** | **6.6x** |

### Optimization Strategies Implemented
- **Bulk Pre-Fetching**: All product data fetched upfront with `whereIn()` queries
- **Collection groupBy()**: Data grouped once, then accessed via O(1) hashmap lookups
- **Pre-Aggregated Tables**: Uses `sales_daily_summary` instead of raw STOCKDIARY queries
- **Efficient Stock Lookups**: Single query for all stock levels via STOCKCURRENT

### Key Performance Patterns
```php
// ✅ GOOD: Bulk fetch then lookup
$allData = Model::whereIn('product_id', $productIds)->get()->keyBy('product_id');
foreach ($products as $product) {
    $data = $allData[$product->ID] ?? null;  // O(1) lookup
}

// ❌ BAD: Query inside loop (N+1 problem)
foreach ($products as $product) {
    $data = Model::where('product_id', $product->ID)->first();  // N queries
}

// ✅ GOOD: Group then access
$grouped = $collection->groupBy('product_id');
$productData = $grouped->get($productId, collect());  // O(1)

// ❌ BAD: Filter repeatedly
$productData = $collection->where('product_id', $productId);  // O(n) each time
```

### Scalability
- Pagination for large product lists
- AJAX loading for product details
- Database query optimization
- Caching of learned patterns

## Integration Points

### Existing Systems
- **SalesRepository**: Historical sales data
- **StockCurrent**: Real-time inventory
- **SupplierService**: Supplier integration
- **Product Models**: Product information
- **Delivery System**: Actual vs ordered tracking

### Future Enhancements
- Weather-based adjustments
- Seasonal pattern recognition
- Supplier API integration
- Mobile app for order review
- Advanced ML algorithms

## Error Handling

### Data Validation
- Quantity limits and constraints
- Supplier product availability
- Budget constraints
- Minimum order requirements

### Fallback Strategies
- Default to previous order if sales data unavailable
- Conservative estimates for new products
- Manual override capabilities
- Audit trail for all changes

## Testing Strategy

### Unit Tests
- Order calculation logic
- Product classification algorithms
- Learning system accuracy
- Export format validation

### Integration Tests
- End-to-end order workflow
- Database consistency
- External system integration
- Performance benchmarks

### Manual Testing
- User workflow validation
- Interface usability
- Supplier format compatibility
- Edge case handling

## Metrics and Monitoring

### Key Performance Indicators
- Time spent on weekly orders
- Order accuracy (actual vs planned)
- Waste reduction
- Stock-out incidents
- User satisfaction scores

### Analytics
- Most frequently adjusted products
- Learning algorithm performance
- Seasonal pattern identification
- Cost savings analysis

## Future Roadmap

### Phase 2 Enhancements
- Advanced learning algorithms
- Weather API integration
- Seasonal pattern recognition
- Mobile app development
- Supplier API connections

### Phase 3 Features
- Multi-supplier order coordination
- Automated reordering for safe items
- Predictive analytics
- AI-powered demand forecasting
- Voice-controlled order review

## Operational Notes
- **Database Migration**: Run `php artisan migrate` to add `coverage_days`, `coverage_ends_on`, and `sales_history_weeks` columns to `order_sessions`.
- **Sales Cache**: Keep `sales_daily_summary` populated via `/sales-import` (UI) or CLI (`php artisan sales:import-daily`, `php artisan sales:import-monthly`) so order generation uses the fast summaries.
- **UI Parameters**: The order review table accepts `?sort=sales|name|priority|value` and `?show_all=1` to control sort order and whether unordered items are visible.

---

**Status**: ✅ Implementation Ready  
**Priority**: High  
**Estimated Development Time**: 2-3 weeks  
**Dependencies**: SalesRepository, StockCurrent model, SupplierService
