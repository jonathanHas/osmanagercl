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
  - Manual re-queue requests
- **Smart filtering** excludes recently printed products (7-day window)
- **Real-time updates** when products are added back to the queue

### 🖨️ Printing System
- **Single label printing** for individual products
- **Bulk A4 printing** with optimized layout based on template
- **Dynamic print forms** that collect current product states
- **Print event logging** to track label generation history

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
LabelLog::logRequeueLabel($barcode);
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
- **Quick stats**: Products needing labels, recent prints, A4 sheets needed
- **Template selector**: Choose label layout and dimensions
- **Products table**: Current products needing labels with preview/print actions
- **Recent prints**: Previously printed labels with re-queue options

### Dynamic Forms
- **Print All**: Collects current visible products dynamically (not cached)
- **Preview All**: Uses live product list for accurate previews
- **Real-time updates**: No need to navigate away and back after re-queuing

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

**Last Updated**: July 2025  
**Version**: 1.0.0