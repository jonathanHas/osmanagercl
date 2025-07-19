# Production Deployment Guide

This guide contains essential steps and considerations for deploying the application to production, particularly when switching from the example/development database to the live production POS database.

## 📋 Pre-Deployment Checklist

### Database Optimization Requirements

**CRITICAL**: The following database indexes MUST be applied to the production POS database for optimal performance:

```sql
-- Required for stock filtering performance
CREATE INDEX IF NOT EXISTS idx_stocking_barcode ON stocking(Barcode);

-- Required for in-stock filtering performance  
CREATE INDEX IF NOT EXISTS idx_stockcurrent_product_units ON STOCKCURRENT(PRODUCT, UNITS);
```

### Performance Impact
Without these indexes, stock filtering operations can take 5-10+ seconds. With the indexes:
- Stocked filter: < 0.5 seconds
- In Stock filter: < 0.3 seconds
- Statistics page: < 1 second (down from 17+ seconds)

## 🚀 Deployment Steps

### 1. Database Setup

1. **Apply Performance Indexes**:
   ```bash
   # Connect to production POS database and run:
   mysql -u [prod_username] -p [prod_database_name] < database/add_stocking_index.sql
   ```

2. **Verify Index Creation**:
   ```sql
   SHOW INDEX FROM stocking WHERE Key_name = 'idx_stocking_barcode';
   SHOW INDEX FROM STOCKCURRENT WHERE Key_name = 'idx_stockcurrent_product_units';
   ```

### 2. Environment Configuration

1. **Update `.env` file**:
   ```env
   # Production POS Database Connection
   POS_DB_HOST=your_production_host
   POS_DB_PORT=3306
   POS_DB_DATABASE=your_production_unicenta_db
   POS_DB_USERNAME=your_production_username
   POS_DB_PASSWORD=your_production_password
   ```

2. **Clear Configuration Cache**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

### 3. Test Critical Functionality

After switching to production database:

- [ ] **Product Listing**: Verify products load correctly
- [ ] **Stock Filtering**: Test "Stocked products" filter performance
- [ ] **In Stock Filter**: Test "In stock only" filter performance
- [ ] **Statistics**: Check dashboard statistics load time
- [ ] **VAT Calculations**: Verify tax rates are calculated correctly
- [ ] **Sales Data**: Ensure sales history displays properly
- [ ] **Price Editing**: Test price update functionality

### 4. Performance Verification

Run these tests to verify performance improvements:

1. **Stock Filter Performance**:
   - Navigate to Products page
   - Enable "Stocked products" filter
   - Should load in < 1 second

2. **Statistics Performance**:
   - Go to Products page
   - Click "Show Product Statistics"
   - Should load in < 2 seconds

3. **Combined Filters**:
   - Test multiple filters together
   - Should maintain fast performance

## 🔄 Rollback Plan

If issues occur after deployment:

### 1. Revert Database Connection
```env
# Revert to development/example database
POS_DB_HOST=127.0.0.1
POS_DB_DATABASE=example_unicenta_db
# ... other development settings
```

### 2. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 3. Remove Indexes (if needed)
```sql
DROP INDEX IF EXISTS idx_stocking_barcode ON stocking;
DROP INDEX IF EXISTS idx_stockcurrent_product_units ON STOCKCURRENT;
```

## 📊 Performance Monitoring

After deployment, monitor these metrics:

- **Page Load Times**: Products page should load in < 3 seconds
- **Filter Response**: Stock filters should respond in < 1 second
- **Statistics Loading**: Should complete in < 2 seconds
- **Memory Usage**: Monitor for any memory leaks with large datasets

## 🛠️ Troubleshooting

### Common Issues

1. **Slow Stock Filtering**:
   - Verify indexes were created correctly
   - Check database connection performance
   - Monitor query execution times

2. **Missing Products**:
   - Verify table structure matches expectations
   - Check for case sensitivity issues in table/column names
   - Confirm data exists in production database

3. **VAT Calculation Errors**:
   - Verify TAXES and TAXCATEGORIES tables exist
   - Check tax category assignments in PRODUCTS table
   - Confirm VAT rates are correctly formatted

### Performance Debugging

To debug slow queries, temporarily enable query logging:

```php
// Add to AppServiceProvider::boot() for debugging
DB::listen(function ($query) {
    if ($query->time > 1000) { // Log queries taking > 1 second
        Log::info('Slow Query: ' . $query->sql . ' - Time: ' . $query->time . 'ms');
    }
});
```

## 📝 Post-Deployment Notes

### Maintenance Tasks

1. **Regular Index Maintenance**:
   - Monitor index usage and performance
   - Consider additional indexes based on usage patterns

2. **Performance Monitoring**:
   - Set up alerts for slow page loads
   - Monitor database query performance

3. **Data Validation**:
   - Regularly verify data consistency
   - Monitor for any data synchronization issues

### Future Optimizations

Consider these optimizations based on production usage:

1. **Database Caching**: Implement Redis/Memcached for frequently accessed data
2. **Additional Indexes**: Add indexes based on actual query patterns
3. **Database Optimization**: Regular ANALYZE and OPTIMIZE table maintenance
4. **Connection Pooling**: Implement connection pooling for high-traffic scenarios

## 🔗 Related Documentation

- [POS Integration Guide](./pos-integration.md) - Detailed POS database integration
- [Performance Optimization](./performance.md) - Additional performance tips
- [Main Documentation Index](./README.md) - Complete documentation overview

---

**Last Updated**: January 2025  
**Version**: 1.0  
**Critical**: This document should be reviewed before every production deployment.