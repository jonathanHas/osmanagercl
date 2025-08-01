# Enhanced Waste Tracking System - Future Implementation

This document outlines an advanced waste tracking system that extends uniCenta's basic Stock Diary waste recording with detailed analytics and intelligent ordering integration.

## Current State vs Future Vision

### Current uniCenta Waste Tracking
- Basic stock movement recording in STOCKDIARY table
- Simple reason codes (Breakage = -3)
- No detailed waste analysis
- Limited reporting capabilities
- No integration with ordering decisions

### Enhanced Waste Tracking Vision
- Detailed waste categorization and root cause analysis
- Pattern recognition for frequently wasted items
- Integration with order generation to prevent over-ordering
- Cost impact analysis and reporting
- Predictive alerts for items approaching expiry

## Enhanced Database Schema

### waste_details
```sql
CREATE TABLE waste_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    stock_diary_id VARCHAR(255) NOT NULL, -- Links to STOCKDIARY entry
    product_id VARCHAR(255) NOT NULL,
    waste_type ENUM('expired', 'damaged', 'quality', 'temperature', 'contaminated', 'recalled', 'other') NOT NULL,
    waste_category ENUM('perishable', 'breakage', 'quality_control', 'handling', 'storage') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
    
    -- Expiry tracking
    expiry_date DATE,
    days_before_expiry INT, -- Negative if already expired
    batch_code VARCHAR(50),
    supplier_batch VARCHAR(50),
    
    -- Environmental factors
    temperature_issue BOOLEAN DEFAULT FALSE,
    humidity_issue BOOLEAN DEFAULT FALSE,
    storage_location VARCHAR(100),
    
    -- Operational context
    recorded_by INT NOT NULL, -- User ID
    discovered_during ENUM('stock_check', 'delivery', 'customer_complaint', 'routine_inspection', 'sale_attempt'),
    shift ENUM('morning', 'afternoon', 'evening'),
    
    -- Additional data
    photos TEXT, -- JSON array of photo URLs
    notes TEXT,
    customer_complaint BOOLEAN DEFAULT FALSE,
    supplier_notified BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES PRODUCTS(ID),
    INDEX idx_product_date (product_id, created_at),
    INDEX idx_waste_type (waste_type),
    INDEX idx_expiry_tracking (expiry_date, days_before_expiry)
);
```

### waste_patterns
```sql
CREATE TABLE waste_patterns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id VARCHAR(255) NOT NULL,
    
    -- Pattern analysis (last 6 months)
    total_waste_units DECIMAL(10,3) DEFAULT 0,
    total_waste_cost DECIMAL(10,2) DEFAULT 0,
    waste_frequency_per_month DECIMAL(5,2) DEFAULT 0,
    
    -- Expiry patterns
    avg_days_before_expiry DECIMAL(5,2),
    most_common_waste_type VARCHAR(50),
    
    -- Seasonal patterns
    peak_waste_month INT, -- 1-12
    peak_waste_day_of_week INT, -- 1-7
    
    -- Performance indicators
    waste_percentage_of_sales DECIMAL(5,2), -- Waste as % of total sales
    trend ENUM('improving', 'stable', 'worsening'),
    
    -- Recommendations
    suggested_order_reduction DECIMAL(5,2), -- Percentage reduction
    suggested_safety_stock_adjustment DECIMAL(5,2),
    review_priority ENUM('low', 'medium', 'high', 'critical'),
    
    last_calculated DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES PRODUCTS(ID),
    UNIQUE KEY unique_product (product_id)
);
```

### waste_alerts
```sql
CREATE TABLE waste_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id VARCHAR(255) NOT NULL,
    alert_type ENUM('high_waste_rate', 'early_expiry', 'pattern_detected', 'cost_threshold', 'supplier_issue'),
    severity ENUM('info', 'warning', 'critical'),
    message TEXT NOT NULL,
    threshold_value DECIMAL(10,2),
    current_value DECIMAL(10,2),
    
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at TIMESTAMP NULL,
    acknowledged_by INT NULL,
    resolved_at TIMESTAMP NULL,
    
    -- Action taken
    action_taken TEXT,
    order_adjustment_made BOOLEAN DEFAULT FALSE,
    supplier_contacted BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (product_id) REFERENCES PRODUCTS(ID),
    INDEX idx_unresolved (resolved_at, severity)
);
```

## Implementation Components

### 1. Enhanced Waste Recording Service

```php
class EnhancedWasteService
{
    public function recordWaste($wasteData)
    {
        DB::transaction(function () use ($wasteData) {
            // 1. Create standard STOCKDIARY entry
            $stockDiaryId = $this->createStockDiaryEntry(
                $wasteData['product_id'],
                -$wasteData['quantity'],
                -3 // Breakage reason code
            );
            
            // 2. Create detailed waste record
            $wasteDetail = WasteDetail::create([
                'stock_diary_id' => $stockDiaryId,
                'product_id' => $wasteData['product_id'],
                'waste_type' => $wasteData['waste_type'],
                'waste_category' => $this->categorizeWaste($wasteData['waste_type']),
                'quantity' => $wasteData['quantity'],
                'unit_cost' => $wasteData['unit_cost'],
                'expiry_date' => $wasteData['expiry_date'],
                'days_before_expiry' => $this->calculateDaysBeforeExpiry($wasteData['expiry_date']),
                'batch_code' => $wasteData['batch_code'],
                'recorded_by' => auth()->id(),
                'discovered_during' => $wasteData['discovered_during'],
                'notes' => $wasteData['notes']
            ]);
            
            // 3. Update pattern analysis
            $this->updateWastePatterns($wasteData['product_id']);
            
            // 4. Check for alerts
            $this->checkWasteAlerts($wasteData['product_id']);
            
            // 5. Photo handling
            if (!empty($wasteData['photos'])) {
                $this->attachPhotos($wasteDetail->id, $wasteData['photos']);
            }
        });
    }
    
    public function updateWastePatterns($productId)
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6);
        
        $wasteData = WasteDetail::where('product_id', $productId)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get();
        
        if ($wasteData->isEmpty()) {
            return;
        }
        
        $pattern = WastePattern::updateOrCreate(
            ['product_id' => $productId],
            [
                'total_waste_units' => $wasteData->sum('quantity'),
                'total_waste_cost' => $wasteData->sum('total_cost'),
                'waste_frequency_per_month' => $wasteData->count() / 6,
                'avg_days_before_expiry' => $wasteData->whereNotNull('days_before_expiry')
                    ->avg('days_before_expiry'),
                'most_common_waste_type' => $wasteData->groupBy('waste_type')
                    ->sortByDesc('count')
                    ->keys()
                    ->first(),
                'last_calculated' => Carbon::now()
            ]
        );
        
        // Calculate recommendations
        $this->calculateRecommendations($pattern);
    }
    
    private function calculateRecommendations(WastePattern $pattern)
    {
        // If wasting items with significant shelf life remaining
        if ($pattern->avg_days_before_expiry > 2) {
            $reductionPercentage = min(50, $pattern->waste_frequency_per_month * 5);
            $pattern->update([
                'suggested_order_reduction' => $reductionPercentage,
                'review_priority' => $reductionPercentage > 20 ? 'high' : 'medium'
            ]);
        }
        
        // If waste percentage is high
        $salesData = $this->getSalesData($pattern->product_id, 6);
        if ($salesData > 0) {
            $wastePercentage = ($pattern->total_waste_units / $salesData) * 100;
            $pattern->update(['waste_percentage_of_sales' => $wastePercentage]);
            
            if ($wastePercentage > 10) {
                $this->createAlert($pattern->product_id, 'high_waste_rate', 
                    "Product waste rate is {$wastePercentage}% of sales");
            }
        }
    }
}
```

### 2. Smart Waste Recording Interface

```html
<!-- Mobile-first waste recording interface -->
<div class="waste-recording-app" x-data="wasteRecorder()">
    
    <!-- Quick product search/scan -->
    <div class="product-scanner">
        <input type="text" 
               x-model="barcode" 
               @keyup.enter="lookupProduct()"
               placeholder="Scan or type barcode"
               class="barcode-input"
               autofocus>
        
        <div x-show="product" class="product-info">
            <img :src="product.image_url" class="product-image" loading="lazy">
            <div class="product-details">
                <h3 x-text="product.name"></h3>
                <p class="current-stock">Stock: <span x-text="product.current_stock"></span></p>
                <p class="last-delivery">Last delivery: <span x-text="product.last_delivery_date"></span></p>
            </div>
        </div>
    </div>
    
    <!-- Waste type selection -->
    <div class="waste-type-grid">
        <button @click="selectWasteType('expired')" 
                :class="wasteType === 'expired' ? 'selected' : ''"
                class="waste-type-btn expired">
            📅<br>Expired
        </button>
        
        <button @click="selectWasteType('damaged')" 
                :class="wasteType === 'damaged' ? 'selected' : ''"
                class="waste-type-btn damaged">
            💔<br>Damaged
        </button>
        
        <button @click="selectWasteType('quality')" 
                :class="wasteType === 'quality' ? 'selected' : ''"
                class="waste-type-btn quality">
            ⚠️<br>Quality
        </button>
        
        <button @click="selectWasteType('temperature')" 
                :class="wasteType === 'temperature' ? 'selected' : ''"
                class="waste-type-btn temperature">
            🌡️<br>Temperature
        </button>
    </div>
    
    <!-- Contextual details -->
    <div x-show="wasteType === 'expired'" class="expiry-details">
        <label>Original expiry date:</label>
        <input type="date" x-model="expiryDate" class="form-input">
        
        <label>Condition:</label>
        <select x-model="expiryCondition" class="form-select">
            <option value="on_date">On expiry date</option>
            <option value="1_day">1 day before expiry</option>
            <option value="2_days">2 days before expiry</option>
            <option value="3_plus_days">3+ days before expiry</option>
        </select>
    </div>
    
    <!-- Quantity and cost -->
    <div class="quantity-cost-row">
        <div class="quantity-input">
            <label>Quantity:</label>
            <input type="number" 
                   x-model="quantity" 
                   step="0.1" 
                   class="form-input">
        </div>
        
        <div class="cost-display">
            <label>Cost impact:</label>
            <span class="cost-amount">€<span x-text="calculateCost()"></span></span>
        </div>
    </div>
    
    <!-- Photo capture -->
    <div class="photo-section">
        <button @click="capturePhoto()" class="photo-btn">
            📷 Add Photo
        </button>
        <div x-show="photos.length" class="photo-preview">
            <template x-for="photo in photos" :key="photo.id">
                <img :src="photo.url" class="photo-thumb" @click="viewPhoto(photo)">
            </template>
        </div>
    </div>
    
    <!-- Notes -->
    <textarea x-model="notes" 
              placeholder="Additional notes (optional)"
              class="notes-input"></textarea>
    
    <!-- Submit -->
    <button @click="submitWaste()" 
            :disabled="!canSubmit()"
            class="submit-btn">
        Record Waste
    </button>
    
    <!-- Recent entries -->
    <div class="recent-waste" x-show="recentEntries.length">
        <h3>Today's Waste</h3>
        <template x-for="entry in recentEntries" :key="entry.id">
            <div class="waste-entry">
                <span x-text="entry.product_name"></span>
                <span x-text="entry.quantity + ' units'"></span>
                <span x-text="'€' + entry.total_cost"></span>
                <span x-text="entry.waste_type" class="waste-type-badge"></span>
            </div>
        </template>
        
        <div class="daily-total">
            Total waste today: €<span x-text="dailyWasteTotal"></span>
        </div>
    </div>
</div>
```

### 3. Waste Analytics Dashboard

```php
class WasteAnalyticsService
{
    public function generateWasteDashboard($period = '30_days')
    {
        $startDate = $this->getStartDate($period);
        
        return [
            'summary' => $this->getWasteSummary($startDate),
            'top_wasted_products' => $this->getTopWastedProducts($startDate, 10),
            'waste_by_type' => $this->getWasteByType($startDate),
            'daily_trends' => $this->getDailyWasteTrends($startDate),
            'cost_impact' => $this->getCostImpactAnalysis($startDate),
            'recommendations' => $this->getWasteRecommendations(),
            'alerts' => $this->getActiveAlerts()
        ];
    }
    
    public function getWasteRecommendations()
    {
        return WastePattern::where('review_priority', '!=', 'low')
            ->where('suggested_order_reduction', '>', 0)
            ->with('product')
            ->orderBy('total_waste_cost', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($pattern) {
                return [
                    'product_name' => $pattern->product->NAME,
                    'current_waste_cost' => $pattern->total_waste_cost,
                    'recommended_action' => "Reduce orders by {$pattern->suggested_order_reduction}%",
                    'potential_savings' => $pattern->total_waste_cost * ($pattern->suggested_order_reduction / 100),
                    'confidence' => $this->calculateRecommendationConfidence($pattern)
                ];
            });
    }
}
```

### 4. Integration with Order Generation

```php
class OrderService
{
    public function generateOrderSuggestions($supplierId, $orderDate)
    {
        $products = $this->getSupplierProducts($supplierId);
        $suggestions = [];
        
        foreach ($products as $product) {
            $baseQuantity = $this->calculateBaseQuantity($product);
            
            // Apply waste-based adjustments
            $wasteAdjustment = $this->getWasteAdjustment($product);
            $adjustedQuantity = $baseQuantity * $wasteAdjustment['factor'];
            
            $suggestions[] = [
                'product_id' => $product->ID,
                'base_quantity' => $baseQuantity,
                'suggested_quantity' => $adjustedQuantity,
                'waste_adjustment' => $wasteAdjustment,
                'review_priority' => $this->determineReviewPriority($product, $wasteAdjustment)
            ];
        }
        
        return $suggestions;
    }
    
    private function getWasteAdjustment($product)
    {
        $pattern = WastePattern::where('product_id', $product->ID)->first();
        
        if (!$pattern) {
            return ['factor' => 1.0, 'reason' => 'No waste history'];
        }
        
        $adjustment = [
            'factor' => 1.0,
            'reason' => 'No adjustment needed',
            'confidence' => 'medium'
        ];
        
        // High waste rate - reduce orders
        if ($pattern->waste_percentage_of_sales > 10) {
            $reduction = min(0.5, $pattern->suggested_order_reduction / 100);
            $adjustment = [
                'factor' => 1 - $reduction,
                'reason' => "Reduce by {$pattern->suggested_order_reduction}% due to high waste rate",
                'confidence' => 'high',
                'waste_cost' => $pattern->total_waste_cost
            ];
        }
        
        // Frequent early expiry - significant reduction
        if ($pattern->avg_days_before_expiry > 3) {
            $adjustment = [
                'factor' => 0.7,
                'reason' => "Items typically waste {$pattern->avg_days_before_expiry} days before expiry",
                'confidence' => 'high',
                'suggested_action' => 'Consider different supplier or smaller pack sizes'
            ];
        }
        
        return $adjustment;
    }
}
```

## Advanced Features

### 1. Predictive Waste Alerts
- Monitor items approaching expiry dates
- Temperature-sensitive product alerts
- Batch tracking for recalls
- Pattern-based early warnings

### 2. Supplier Integration
- Automatically notify suppliers of quality issues
- Track supplier-specific waste patterns
- Request shorter lead times for problematic items
- Negotiate shelf life guarantees

### 3. Cost Recovery
- Track insurance claims for damaged goods
- Supplier charge-backs for quality issues
- Customer compensation tracking
- Tax write-off documentation

### 4. Environmental Impact
- Carbon footprint of wasted products
- Disposal cost tracking
- Recycling and composting metrics
- Sustainability reporting

## Implementation Timeline

### Phase 1 (4-6 weeks)
- Enhanced waste recording interface
- Basic pattern analysis
- Integration with existing Stock Diary

### Phase 2 (6-8 weeks)
- Advanced analytics dashboard
- Order generation integration
- Alert system implementation

### Phase 3 (8-10 weeks)
- Mobile app development
- Supplier integration features
- Advanced ML pattern recognition

### Phase 4 (10-12 weeks)
- Predictive analytics
- Environmental impact tracking
- Cost recovery systems

## ROI Expectations

### Direct Cost Savings
- 20-30% reduction in product waste
- 15-25% improvement in order accuracy
- 10-15% reduction in emergency orders

### Operational Efficiency
- 50% reduction in waste recording time
- Automated pattern recognition
- Proactive inventory management

### Business Intelligence
- Data-driven ordering decisions
- Supplier performance insights
- Customer satisfaction improvements

---

**Status**: 📋 Future Planning  
**Priority**: Medium  
**Dependencies**: Order generation system, enhanced mobile interface  
**Estimated ROI**: 15-25% reduction in waste-related costs