# Coffee KDS (Kitchen Display System)

## Overview
The Coffee KDS is a real-time order tracking system designed specifically for coffee shops using uniCenta POS. It displays incoming coffee orders on a kitchen display for baristas, with optimized performance for fast-paced environments.

**Key Features:**
- 2-3 second order detection time
- Audio notifications for new orders
- One-click order completion
- Mobile-responsive design with order grouping
- Real-time system monitoring
- Smart coffee order grouping for mobile displays

## Technical Architecture

### Detection Method
The system uses direct database polling instead of queue workers for optimal performance:
- **Direct SQL queries** to POS database every 2 seconds
- **Bypasses Laravel queue system** eliminating 8-9 second delays
- **Response time**: 20-30ms per check
- **No supervisor/queue worker dependencies**

### Components

#### Backend
- `KdsController` - Main display and SSE streaming
- `KdsRealtimeController` - Fast direct polling endpoint
- `KdsOrder` / `KdsOrderItem` - Order data models
- `CoffeeOrderGroupingService` - Smart order grouping for mobile display
- `CoffeeProductMetadata` - Product classification (coffee types vs options)
- `MonitorCoffeeOrdersJob` - Legacy queue job (kept for backward compatibility)

#### Frontend
- Server-Sent Events (SSE) for real-time updates
- JavaScript polling every 2 seconds
- Alpine.js for interactive components
- Tailwind CSS for responsive design

## Setup and Configuration

### 1. Database Setup
```bash
php artisan migrate
```

Creates tables:
- `kds_orders` - Main order tracking
- `kds_order_items` - Individual drink items
- `kds_settings` - System settings

### 2. Timezone Configuration
Ensure Laravel timezone matches POS system:
```php
// config/app.php
'timezone' => 'Europe/Dublin',
```

### 3. POS Database Connection
Configure in `.env`:
```env
POS_DB_HOST=127.0.0.1
POS_DB_PORT=3306
POS_DB_DATABASE=unicenta2016
POS_DB_USERNAME=pos_user
POS_DB_PASSWORD=password
```

### 4. Coffee Category
The system looks for products in category `081` (Coffee Fresh). Verify this matches your POS setup.

### 5. Coffee Product Metadata
Seed the coffee product metadata for proper order grouping:
```bash
php artisan db:seed --class=CoffeeProductMetadataSeeder
```

This creates classification data for:
- **Coffee Types**: Main drinks (Cappuccino, Latte, Americano, etc.)
- **Options**: Modifiers (Oat Milk, Syrups, Takeaway, etc.)
- **Short Names**: Compact display names for mobile (Van for Vanilla, Oat for Oat Milk)

## Usage

### Accessing the KDS
Navigate to `/kds` in your browser. The page will:
1. Display all active coffee orders
2. Auto-refresh every 2 seconds
3. Play notification sound for new orders
4. Show system status in header

### Order Workflow

#### New Orders
- Appear automatically within 2-3 seconds
- Red border indicates new order
- Notification sound plays
- Shows waiting time in red

#### Completing Orders
1. Click "Complete Order" button
2. Order moves to completed section
3. Remains visible for 30 minutes

#### Restoring Orders
- Click "Restore" in completed section
- Order returns to active display

## Mobile Order Grouping

### Overview
On mobile devices (screens <640px), the KDS automatically groups coffee orders with their options into compact single lines for better readability.

### How It Works

#### Automatic Detection
- **Coffee Types**: Main drinks like Cappuccino, Latte, Americano
- **Options**: Modifiers like Oat Milk, Syrups, Takeaway, Extra Shots
- **Sequential Processing**: Maintains order entry sequence

#### Grouping Logic
Orders are processed in the exact sequence they were entered:
1. When a coffee type is detected → starts new group
2. Following options are added to that coffee group
3. Next coffee type → starts another group
4. Preserves the original staff entry order

#### Display Examples
**Before Grouping (Desktop):**
- 1x Cappuccino
- 1x Oat Milk
- 1x Takeaway

**After Grouping (Mobile):**
- 1x Cappuccino + Oat, Takeaway

**Multiple Orders:**
- 1x Cappuccino + Sit In
- 1x Latte + Oat, Vanilla, Takeaway

### Product Classification

#### Coffee Types (Main Drinks)
- Cappuccino → Cappuccino
- Latte → Latte
- Americano → Americano
- Flat White → Flat White
- Espresso → Espresso
- Hot Chocolate → Hot Choc S/L
- Tea varieties → Tea/Herbal Tea

#### Options by Category
- **Service**: Takeaway, Cup Discount, 2Go Cups
- **Milk**: Oat Milk, Alternative Milk
- **Syrups**: Vanilla, Hazelnut, Caramel
- **Coffee**: Extra Shots

### Managing Metadata
Access `/coffee/metadata` to:
- View all coffee products and their classifications
- Change product types (coffee vs option)
- Edit short names for mobile display
- Manage grouping categories
- Add new products to the system

### Troubleshooting Grouping

#### Orders Not Grouping
1. Check product metadata exists:
   ```bash
   php artisan tinker
   >>> App\Models\CoffeeProductMetadata::count()
   ```

2. Verify product classification:
   ```bash
   >>> App\Models\CoffeeProductMetadata::where('type', 'coffee')->count()
   >>> App\Models\CoffeeProductMetadata::where('type', 'option')->count()
   ```

3. Re-seed if needed:
   ```bash
   php artisan db:seed --class=CoffeeProductMetadataSeeder
   ```

#### Incorrect Grouping
- Check if products are properly classified as 'coffee' or 'option'
- Verify order sequence is preserved
- Use metadata management interface to fix classifications

#### Missing Short Names
- Products without metadata fall back to auto-generated short names
- Add proper metadata via `/coffee/metadata` interface
- Use descriptive short names (max 12 characters)

### Clearing All Active Orders
1. Click "Clear" dropdown button in header
2. Select "Complete All Orders"
3. Confirm the action
4. All active orders are marked as completed
5. Orders move to completed section (visible for 30 minutes)
6. Orders won't be re-imported since they exist in database

### Status Indicators

**System Status (Header)**
- 🟢 **Green**: Active, showing response time
- 🟡 **Yellow**: Checking connection
- 🔴 **Red**: Disconnected after 3 failed attempts

**Order Colors**
- 🔴 **Red Border**: New order
- 🟡 **Yellow Border**: Viewed (legacy)
- 🔵 **Blue Border**: Preparing (legacy)
- 🟢 **Green Border**: Ready (legacy)

## Performance Optimization

### Current Performance
- **Detection Time**: 2-3 seconds maximum
- **Query Execution**: 20-30ms
- **Display Update**: Instant via SSE
- **CPU Usage**: Minimal

### Optimization History
1. **Initial Implementation**: 10-17 seconds (queue-based)
2. **First Optimization**: 8-10 seconds (reduced intervals)
3. **Current Solution**: 2-3 seconds (direct polling)

### Key Optimizations
- Removed queue worker dependency
- Direct SQL queries instead of Eloquent
- Simplified detection logic
- Parallel fetch operations in JavaScript

## Troubleshooting

### Orders Not Appearing

1. **Check POS Connection**
   ```bash
   php artisan kds:diagnose
   ```

2. **Verify Coffee Category**
   ```bash
   php artisan tinker
   >>> App\Models\Product::where('CATEGORY', '081')->count()
   ```

3. **Check Timezone**
   - Ensure Laravel and POS have same timezone
   - Orders with future timestamps won't appear

4. **Clear Stuck Orders**
   - Use the "Complete All Orders" button in the UI
   - Or via command line:
   ```bash
   php artisan tinker
   >>> App\Models\KdsOrder::where('status', '!=', 'completed')->update(['status' => 'completed', 'completed_at' => now()])
   ```

### Slow Detection

1. **Check Database Performance**
   ```bash
   php artisan kds:diagnose --minutes=60
   ```

2. **Monitor Response Times**
   - Check browser console for timing logs
   - Look for "Response: XXms" in status indicator

3. **Verify Network**
   - Ensure stable connection to POS database
   - Check for firewall/proxy issues

### Audio Not Working

1. **Check File Location**
   ```bash
   ls -la public/sounds/notification.mp3
   ```

2. **Browser Permissions**
   - Allow audio autoplay for the domain
   - Check browser console for errors

## Development Commands

### Testing
```bash
# Generate test orders
php artisan kds:test-orders 5

# Simulate activity
php artisan kds:simulate demo

# Check system status
php artisan kds:diagnose
```

### Monitoring
```bash
# Watch logs
tail -f storage/logs/laravel.log | grep KDS

# Check response times
php artisan tinker
>>> $c = new App\Http\Controllers\KdsRealtimeController();
>>> $c->checkNewOrders();
```

### Maintenance

#### Clear Completed Orders
Removes completed orders older than 1 hour:
```bash
php artisan kds:clear-completed
```

#### Complete All Orders
Marks all active orders as completed (they remain in the database):
- Access KDS page (`/kds`)
- Click "Clear" dropdown in header
- Select "Complete All Orders"
- Confirm the action

**Note**: This doesn't delete orders, it marks them as completed. They will:
- Disappear from active display immediately
- Appear in "Recently Completed Orders" section
- Be automatically deleted after 24 hours
- Not be re-imported from POS (already exist in database)

## Configuration Options

### Polling Intervals
Located in `resources/views/kds/index.blade.php`:
```javascript
// Fast polling for new orders
setInterval(fastRealtimeCheck, 2000); // 2 seconds

// Display refresh backup
setInterval(manualRefresh, 5000); // 5 seconds
```

### SSE Updates
Located in `app/Http/Controllers/KdsController.php`:
```php
sleep(2); // Wait between SSE updates

if ($lastMonitorCheck->diffInSeconds(now()) >= 3) {
    // Dispatch monitoring job (legacy)
}
```

### Order Visibility
- Active orders: Last 24 hours
- Completed orders: Last 30 minutes
- Auto-cleanup: Orders older than 24 hours

## Security Considerations

1. **Authentication Required**: All KDS routes require login
2. **Read-Only POS Access**: System only reads from POS database
3. **CSRF Protection**: All POST requests validated
4. **Rate Limiting**: Built-in through polling intervals

## Future Enhancements

### Planned Features
- Customer name display
- Order type indicators (dine-in/takeaway)
- Multi-location support
- Order time targets/alerts
- Statistics dashboard

### Performance Goals
- Sub-1 second detection (WebSockets)
- Offline capability
- Progressive Web App (PWA)

## Browser Compatibility

### Supported
- Chrome 90+ (recommended)
- Firefox 88+
- Safari 14+
- Edge 90+

### Requirements
- JavaScript enabled
- SSE support
- Audio autoplay permissions

## Related Documentation
- [POS Integration](./pos-integration.md)
- [Performance Optimization Guide](../development/performance-optimization-guide.md)
- [Troubleshooting Guide](../development/troubleshooting.md)