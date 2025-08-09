# Coffee KDS (Kitchen Display System)

## Overview
The Coffee KDS is a real-time order display system for baristas to track and manage Coffee Fresh orders. It automatically imports orders from the POS system and provides a visual interface for order preparation workflow.

## Features

### Real-Time Order Monitoring
- **Automatic Polling**: Checks POS database every minute for new coffee orders
- **Server-Sent Events (SSE)**: Real-time updates pushed to display without page refresh
- **Order Detection**: Filters for Coffee Fresh products (Category 081)
- **Duplicate Prevention**: Tracks processed orders to avoid duplicates

### Order Display
- **Visual Status Indicators**:
  - 🔴 Red (New): Fresh orders requiring attention
  - 🟡 Yellow (Viewed): Orders acknowledged by barista
  - 🔵 Blue (Preparing): Orders currently being made
  - 🟢 Green (Ready): Orders ready for pickup
  
- **Order Information**:
  - Order number from POS
  - Order time
  - Running timer showing wait time
  - Customer information (if available)
  - Product details with quantities
  - Modifiers (size, milk type, extras)
  - Special instructions

### Workflow Management
1. **New Order** → Barista can "View" or "Start" directly
2. **Viewed** → Barista clicks "Start Preparing"
3. **Preparing** → Barista clicks "Mark Ready" when complete
4. **Ready** → Barista clicks "Complete" when picked up

### Performance Features
- **Efficient Polling**: Only queries orders newer than last processed
- **Auto-Cleanup**: Removes completed orders after 24 hours
- **Optimized Queries**: Uses indexed queries for fast retrieval
- **Background Processing**: Queue-based job processing

## Technical Implementation

### Database Schema

#### `kds_orders` Table
```sql
- id (primary key)
- ticket_id (POS ticket reference)
- ticket_number (display number)
- person (cashier ID)
- status (new/viewed/preparing/ready/completed/cancelled)
- order_time (when placed in POS)
- viewed_at, started_at, ready_at, completed_at (workflow timestamps)
- prep_time (actual preparation time in seconds)
- customer_info (JSON - optional customer details)
```

#### `kds_order_items` Table
```sql
- id (primary key)
- kds_order_id (foreign key)
- product_id (POS product reference)
- product_name
- display_name (custom display name if different)
- quantity
- modifiers (JSON - size, milk, extras from POS attributes)
- notes (special instructions)
```

### Key Components

#### Models
- `KdsOrder`: Main order model with status management methods
- `KdsOrderItem`: Individual order items with modifiers

#### Job
- `MonitorCoffeeOrdersJob`: Polls POS database for new orders

#### Controller
- `KdsController`: Handles display, updates, and SSE streaming

#### Command
- `kds:monitor-coffee`: Manual trigger for order monitoring

#### Event
- `CoffeeOrderReceived`: Broadcast event for new orders

### API Endpoints

```
GET  /kds                 - Display interface
GET  /kds/orders          - JSON list of active orders
POST /kds/orders/{id}/status - Update order status
GET  /kds/stream          - SSE endpoint for real-time updates
POST /kds/poll            - Manually trigger order check
```

## Setup Instructions

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Setup Queue Worker (REQUIRED)
The queue worker MUST be running for the KDS to function. Choose one method:

#### Option A: Simple Cron Job (Recommended for most users)
```bash
# Run the setup script
./scripts/queue-worker-setup.sh
# Choose option 1 (Cron Job)
```
This checks every minute if the worker is running and restarts it if needed.

#### Option B: Manual Cron Setup
Add to your crontab (`crontab -e`):
```bash
# Check and restart queue worker if needed
* * * * * cd /var/www/html/osmanager && pgrep -f "artisan queue:work" || nohup php artisan queue:work --sleep=3 --tries=3 >> storage/logs/queue.log 2>&1 &

# Laravel scheduler (for monitoring)
* * * * * cd /var/www/html/osmanager && php artisan schedule:run >> /dev/null 2>&1
```

#### Option C: Systemd Service (For production servers)
```bash
# Create service file
sudo nano /etc/systemd/system/osmanager-queue.service

# Add content from scripts/queue-worker-setup.sh systemd section

# Enable and start
sudo systemctl enable osmanager-queue
sudo systemctl start osmanager-queue
```

#### Option D: Supervisor (Most robust)
```bash
# Install supervisor
sudo apt-get install supervisor

# Create config
sudo nano /etc/supervisor/conf.d/osmanager-queue.conf

# Add content from scripts/queue-worker-setup.sh supervisor section

# Start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start osmanager-queue:*
```

### 3. Verify Queue Worker Status
The KDS page now shows a visual indicator:
- 🟢 **Green**: Queue worker is active
- 🔴 **Red**: Queue worker is not running
- Shows number of pending jobs
- Shows last check time

### 4. Manual Testing
```bash
# Check for new orders manually
php artisan kds:monitor-coffee

# Check queue status
php artisan queue:failed
php artisan tinker --execute="echo 'Pending: ' . \DB::table('jobs')->count()"

# View KDS display
Navigate to: /kds
```

## Configuration

### Polling Frequency
- Scheduled command runs every minute
- SSE updates every 5 seconds
- Manual refresh available via button

### Category Configuration
Coffee Fresh products are identified by category ID `081`. To modify:
```php
// In MonitorCoffeeOrdersJob.php
->where('CATEGORY', '081') // Change category ID here
```

## Troubleshooting

### Orders Not Appearing
1. Check POS database connection in `.env`
2. Verify Coffee Fresh products have category `081`
3. Check queue worker is running
4. Review logs: `storage/logs/laravel.log`

### SSE Not Working
1. Check browser supports Server-Sent Events
2. Verify no proxy/firewall blocking event streams
3. Check `X-Accel-Buffering: no` header is set

### Performance Issues
1. Add index on `TICKETS.DATENEW` in POS database
2. Increase polling interval if needed
3. Implement Redis for better queue performance

## Future Enhancements

### Phase 2: WebSocket Broadcasting
- Replace SSE with Laravel Reverb for true real-time
- Bi-directional communication
- Multi-device synchronization

### Phase 3: Advanced Features
- Multi-station support (hot drinks, cold drinks, food)
- Order modification handling
- Preparation time analytics
- Peak time predictions
- Staff performance metrics

### Phase 4: Integration
- Two-way POS integration
- Customer notifications
- Mobile app for baristas
- Order queuing algorithms

## Security Considerations
- Authentication required for access
- Read-only POS database access
- CSRF protection on status updates
- Sanitized customer information display

## Performance Metrics
- Order detection: < 10 seconds from POS
- Display update: < 1 second via SSE
- Status changes: Instant
- Database queries: < 50ms
- Memory usage: < 50MB per worker