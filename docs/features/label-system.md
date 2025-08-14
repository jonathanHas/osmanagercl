# Label System

Comprehensive label printing system for product management with barcode generation and template support.

## Overview

The label system provides automated label generation and printing capabilities with:
- Dynamic product label creation with barcodes
- Template-based label layouts
- Print queue management with re-queuing functionality
- Event-based tracking of label requirements
- A4 sheet optimization for bulk printing

## Features

### 🏷️ Label Generation
- **Dynamic barcode generation** using Code128 format
- **Product information display** with name, price (VAT-inclusive), and barcode
- **Template-based layouts** with configurable dimensions
- **Print preview** for single labels and A4 sheets

### 📋 Products Needing Labels
- **Intelligent detection** of products requiring new labels based on:
  - New product creation events
  - Price update events  
  - **Delivery-based price updates** (automatic integration)
  - Manual re-queue requests
- **Smart filtering** excludes recently printed products (7-day window)
- **Real-time updates** when products are added back to the queue
- **Delivery Integration**: Price updates during delivery verification automatically trigger label requirements

### 🖨️ Printing System
- **Single label printing** for individual products
- **Bulk A4 printing** with optimized layout based on template
- **Dynamic print forms** that collect current product states
- **Print event logging** to track label generation history

### 🎯 4x9 Grid Layout (Enhanced)
- **Three-row structure** for optimal space utilization:
  - **Top row**: Product name with responsive font sizing
  - **Middle row**: Barcode visual and price side-by-side (48% each)
  - **Bottom row**: Barcode number centered for better legibility
- **Smart text sizing** with 5 categories:
  - Extra-short (≤12 chars): 22pt for maximum impact
  - Short (≤20 chars): 18pt
  - Medium (≤30 chars): 14pt
  - Long (≤45 chars): 11pt
  - Extra-long (>45 chars): 9pt with 5-line clamp
- **Typography improvements**:
  - Manual hyphenation to prevent awkward breaks
  - Letter-spacing adjustments for long text
  - UTF-8 aware character counting
- **Enhanced readability**:
  - Larger barcode visual (18px height)
  - Bigger barcode numbers (7pt from 5.5pt)
  - No € symbol clipping with visible overflow

### 📱 Scan to Label Feature
- **Barcode Scanner Integration**: Quick barcode scanning modal for instant product addition to label queue
- **Scanner-Optimized Interface**: 
  - Prominent "Scan to Label" button in page header (always accessible without scrolling)
  - Auto-focus input field with virtual keyboard suppression (`inputmode="none"`)
  - Real-time product lookup and visual feedback
  - Queue counter showing current items in labels queue
- **Streamlined Workflow**:
  1. Click "Scan to Label" → Modal opens with auto-focused input
  2. Scan barcode → Product details display instantly
  3. Press Enter or "Add to Labels" → Product queued automatically
  4. Clear input and focus returns for next scan (no manual interaction needed)
  5. Continue scanning multiple products in sequence
- **Visual Feedback**:
  - Live queue counter with prominent circular badge design
  - Success/error messages for each scan
  - Session tracking showing products added during current session
  - Product preview showing name, code, and price before adding

### 🔄 Re-queue Functionality
- **"Add Back to Products Needing Labels"** buttons on:
  - Recent label prints table
  - Individual product detail pages
- **Event-based re-queuing** creates requeue_label events
- **Timestamp-aware logic** ensures proper workflow:
  1. Product gets re-queued → appears in needs labels list
  2. Product gets printed → disappears from needs labels list
  3. Re-queue events older than print events don't cause reappearance

## Architecture

### Models

#### LabelLog
Central event tracking for all label-related activities.

```php
// Event Types
const EVENT_NEW_PRODUCT = 'new_product';
const EVENT_PRICE_UPDATE = 'price_update'; 
const EVENT_LABEL_PRINT = 'label_print';
const EVENT_REQUEUE_LABEL = 'requeue_label';

// Key Methods
LabelLog::logLabelPrint($barcode);
LabelLog::logPriceUpdate($barcode);  // New: Automatic from delivery price updates
LabelLog::logRequeueLabel($barcode);
LabelLog::logNewProduct($barcode);
```

#### LabelTemplate
Template definitions for different label sizes and layouts.

```php
// Template Properties
- name: Template display name
- description: Template description
- width_mm: Label width in millimeters
- height_mm: Label height in millimeters  
- labels_per_a4: Number of labels per A4 sheet
- css_dimensions: Pre-calculated CSS values
- is_default: Default template flag
- is_active: Template availability
```

### Controllers

#### LabelAreaController
Main controller handling all label operations:

- `index()` - Label dashboard with products needing labels
- `printA4()` - Generate A4 sheet with multiple labels
- `previewA4()` - Preview A4 layout before printing
- `previewLabel()` - Single label preview
- `requeueProduct()` - Add product back to needs labels queue
- `lookupBarcode()` - Lookup product details by barcode for scanner modal
- `processBarcodeScan()` - Process scanned barcode and add product to labels queue

### Services

#### LabelService
Core label generation functionality:

- **Barcode generation** using Picqer\Barcode library
- **HTML label rendering** with template-based layouts
- **CSS styling** for print optimization
- **Layout calculations** for A4 sheet positioning

### Business Logic

#### Products Needing Labels Algorithm
The system determines which products need labels using event-based logic:

1. **Collect candidates**: Products with new_product, price_update, or requeue_label events (30 days)
2. **For each product**: Compare most recent event vs most recent print
3. **Include if**: Event exists AND (no recent print OR event newer than print)
4. **Time windows**: Events (30 days), Prints (7 days)

This ensures:
- New products appear until printed
- Price updates trigger label needs until printed  
- Re-queued products appear until printed again
- Recently printed products don't reappear unless specifically re-queued

## User Interface

### Label Area Dashboard (`/labels`)
- **Scanner-Optimized Layout**: Prominent "Scan to Label" button in page header for immediate access
- **Compact stats**: Streamlined display showing products needing labels, recent prints, A4 sheets needed
- **Template selector**: Choose label layout and dimensions (simplified display)
- **Products table**: Current products needing labels with preview/print actions
- **Recent prints**: Previously printed labels with re-queue options
- **Scan to Label Modal**: Overlay interface with barcode input, product preview, and queue counter

### Dynamic Forms
- **Print All**: Collects current visible products dynamically (not cached)
- **Preview All**: Uses live product list for accurate previews
- **Real-time updates**: No need to navigate away and back after re-queuing

## Delivery System Integration

### Automatic Label Queue Management
**New Feature**: Seamless integration between delivery price management and label printing workflow.

**Key Capabilities**:
- **Automatic Price Update Logging**: When prices are updated during delivery verification, products are automatically added to the label queue
- **Quick Edit Integration**: The delivery price edit modal automatically triggers `LabelLog::logPriceUpdate()` 
- **Bulk Update Support**: Mass price updates during delivery processing add all affected products to label queue
- **Real-time Feedback**: Users receive confirmation that updated products have been "added to label queue"
- **Immediate Availability**: Updated products appear in label dashboard without manual intervention

**Technical Implementation**:
```php
// Delivery price update automatically triggers label requirement
public function updateItemPrice(Request $request, Delivery $delivery, DeliveryItem $item)
{
    // Update product price...
    $item->product->update(['PRICESELL' => $netPrice]);
    
    // Automatic label queue integration
    LabelLog::logPriceUpdate($item->product->CODE);
    
    return response()->json([
        'message' => 'Price updated successfully and added to label queue',
        'added_to_labels' => true,
    ]);
}
```

**User Experience Benefits**:
- **Streamlined Workflow**: Price updates during delivery processing automatically queue shelf price updates
- **No Manual Steps**: Eliminates need to remember to manually add products to label queue
- **Immediate Action**: Users can process labels immediately after delivery completion
- **Visual Confirmation**: Clear feedback confirming label queue addition in delivery interface

### Integration Points
1. **Delivery Price Edit Modal**: Automatic logging on individual price updates
2. **Bulk Cost Update System**: Mass logging for threshold-based cost updates  
3. **Product Creation from Deliveries**: New products automatically queued for initial labels
4. **Label Dashboard**: Enhanced to show delivery-triggered updates with context

This integration ensures that pricing changes made during delivery verification immediately translate to actionable shelf price update tasks, maintaining pricing accuracy across the entire retail operation.

### Responsive Design
- **Mobile-optimized** template selection
- **Print-friendly** CSS for actual label printing
- **Dark mode support** throughout the interface

## API Endpoints

### Label Operations
- `GET /labels` - Label dashboard
- `POST /labels/print-a4` - Print A4 sheet with multiple labels
- `GET /labels/preview-a4` - Preview A4 layout
- `GET /labels/preview/{productId}` - Single label preview
- `POST /labels/requeue` - Add product back to needs labels queue
- `POST /labels/lookup-barcode` - Lookup product details by barcode for scanner interface
- `POST /labels/scan` - Process scanned barcode and add product to labels queue

## Configuration

### Environment Variables
```env
# No specific environment variables required
# Uses default Laravel database and session configuration
```

### Templates
Templates are stored in the database and can be managed through:
- Database seeders for initial setup
- Admin interface (future enhancement)
- Direct database manipulation for advanced customization

## Troubleshooting

### Common Issues

#### Products Don't Appear in Needs Labels List
- Check LabelLog entries for the product's barcode
- Verify event timestamps vs print timestamps  
- Ensure product exists in the Product model

#### Products Don't Disappear After Printing
- Verify LabelLog::logLabelPrint() is called during printing
- Check that print events have correct timestamps
- Review getProductsNeedingLabels() logic for timestamp comparison

#### JavaScript Errors on Empty Product List
- Ensure proper null checks in template selection code
- Verify conditional event listener initialization
- Check for missing DOM elements when no products exist

#### Print Layout Issues  
- Verify template dimensions match label sheets
- Check CSS print styles are applied correctly
- Ensure barcode generation is functioning

## Future Enhancements

### Planned Features
- **Template management UI** for creating/editing label layouts
- **Bulk template operations** for multiple products
- **Print history reporting** with detailed analytics
- **Custom label fields** beyond name/price/barcode
- **Print queue scheduling** for batch processing
- **Integration with external printers** via API
- **Enhanced scanner features** such as camera-based barcode scanning for mobile devices

### Performance Optimizations
- **Database indexing** on LabelLog event_type and created_at
- **Caching** for frequently accessed templates
- **Batch operations** for large product sets
- **Background job processing** for heavy print operations

## Related Documentation

- [POS Integration](./pos-integration.md) - Product data source
- [Architecture Overview](../architecture/overview.md) - System structure
- [Development Setup](../development/setup.md) - Local development

---

**Last Updated**: August 2025  
**Version**: 1.2.0