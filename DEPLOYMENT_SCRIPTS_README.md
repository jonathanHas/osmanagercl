# 🚀 OS Manager Production Deployment Scripts

This directory contains comprehensive scripts for deploying OS Manager to production with the new invoice bulk upload system and Amazon payment processing features.

## 📋 Script Overview

### 1. **deploy-production.sh** - Master Deployment Script
**Main deployment orchestrator with test/production environment selection**

```bash
./deploy-production.sh
```

**Features:**
- Interactive target selection (test vs production)
- Complete deployment pipeline
- Invoice parser setup integration
- Comprehensive permission handling
- Pre and post deployment verification
- Database backup (production only)

### 2. **verify-deployment-ready.sh** - Pre-Deployment Verification
**Checks all requirements before deployment**

```bash
./verify-deployment-ready.sh [target_dir] [web_user] [web_group]
```

**Checks:**
- PHP version and extensions
- MySQL connectivity
- Python and invoice parser dependencies
- System dependencies (tesseract, poppler-utils, libreoffice)
- Directory permissions
- Network connectivity

### 3. **setup-invoice-parser-production.sh** - Parser Setup
**Production-ready invoice parser installation**

```bash
sudo ./setup-invoice-parser-production.sh [app_path] [web_user] [web_group]
```

**Actions:**
- Installs system dependencies
- Creates Python virtual environment
- Installs required Python packages
- Sets proper permissions for www-data
- Configures Laravel environment variables
- Tests parser functionality

### 4. **fix-all-permissions.sh** - Comprehensive Permission Fixing
**Unified permission management for all directories**

```bash
sudo ./fix-all-permissions.sh [app_path] [web_user] [web_group]
```

**Manages:**
- Storage directories (temp/invoices, invoices/YYYY/MM)
- Python parser permissions
- Queue worker access
- Web server (www-data) permissions
- ACL permissions (if available)

### 5. **test-deployment.sh** - Post-Deployment Testing
**Comprehensive testing after deployment**

```bash
./test-deployment.sh [app_path] [web_user] [web_group] [test_domain]
```

**Tests:**
- Laravel functionality
- Database connectivity
- File permissions
- Invoice parser
- Queue system
- Web server response
- Cache functionality

### 6. **rollback-deployment.sh** - Emergency Recovery
**Quick recovery from failed deployments**

```bash
sudo ./rollback-deployment.sh [app_path] [web_user] [web_group]
```

**Actions:**
- Stops services safely
- Restores database from backup
- Reverts code to previous version
- Fixes permissions
- Restarts services
- Verifies recovery

### 7. **osmanager-queue-worker.conf** - Supervisor Configuration
**Queue worker configuration for supervisor**

```bash
sudo cp osmanager-queue-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start osmanager-queue-worker:*
```

## 🎯 Recommended Deployment Workflow

### For Test Environment
```bash
# 1. Verify readiness
./verify-deployment-ready.sh /var/www/html/osmanager-test

# 2. Deploy to test
./deploy-production.sh
# Select option 1 (test environment)

# 3. Test deployment
./test-deployment.sh /var/www/html/osmanager-test

# 4. If issues, fix permissions
sudo ./fix-all-permissions.sh /var/www/html/osmanager-test
```

### For Production Environment
```bash
# 1. Verify readiness
./verify-deployment-ready.sh /var/www/html/osmanager

# 2. Deploy to production (includes automatic backup)
./deploy-production.sh
# Select option 2 (production environment)
# Type 'DEPLOY' to confirm

# 3. Test deployment
./test-deployment.sh /var/www/html/osmanager

# 4. Set up supervisor (one-time)
sudo cp osmanager-queue-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start osmanager-queue-worker:*
```

### Emergency Recovery
```bash
# If deployment fails
sudo ./rollback-deployment.sh /var/www/html/osmanager
# Type 'ROLLBACK' to confirm
```

## 🔧 Manual Operations

### Setup Invoice Parser Only
```bash
sudo ./setup-invoice-parser-production.sh /var/www/html/osmanager
```

### Fix Permissions Only
```bash
sudo ./fix-all-permissions.sh /var/www/html/osmanager
```

### Test Existing Deployment
```bash
./test-deployment.sh /var/www/html/osmanager
```

## 📁 Directory Structure Created

```
/var/www/html/osmanager/
├── storage/app/private/
│   ├── temp/
│   │   └── invoices/         # Temporary batch uploads
│   │       └── BATCH-*/      # Individual batch folders
│   └── invoices/             # Permanent storage
│       ├── 2025/
│       │   └── 08/
│       │       └── [invoice_id]/
│       └── attachments/      # Legacy (being phased out)
└── scripts/
    └── invoice-parser/
        ├── venv/             # Python virtual environment
        ├── parsers/          # Individual parser modules
        └── *.py              # Parser scripts
```

## 🔍 Troubleshooting

### Common Issues

**Permission Errors:**
```bash
sudo ./fix-all-permissions.sh /var/www/html/osmanager
```

**Invoice Parser Not Working:**
```bash
# Re-setup parser
sudo ./setup-invoice-parser-production.sh /var/www/html/osmanager

# Test manually
cd /var/www/html/osmanager/scripts/invoice-parser
sudo -u www-data bash -c "source venv/bin/activate && python invoice_parser_laravel.py --help"
```

**Queue Workers Not Processing:**
```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart osmanager-queue-worker:*

# Check logs
tail -f /var/www/html/osmanager/storage/logs/queue-worker.log
```

**Database Issues:**
```bash
# Test connection
cd /var/www/html/osmanager
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected';"

# Run migrations
php artisan migrate --force
```

### Scheduled Jobs (post-deploy check)

```bash
php artisan schedule:list
```
must list **eight** commands. If any is missing, the schedule file did not deploy:

| When | Command |
|---|---|
| Sun 05:00 | `sales:import-daily --last-week` |
| Sun 05:30 | `fruit-veg:prune-thumbnails` |
| 06:00 | `sales:import-daily --yesterday` |
| 06:10 | `sales-accounting:import --days=7` |
| 06:15 | `pos:populate-daily-summaries --last-days=7` |
| 20:00 | `sales:import-daily --today` |
| 20:15 | `suppliers:send-daily-sales` |
| 1st of month 07:00 | `customers:send-statements` |

Every schedule lives in `routes/console.php`. There is deliberately no
`app/Console/Kernel.php`: this application's `bootstrap/app.php` does not bind a
console kernel, so a `schedule()` method there is never called — which is how four
of these jobs silently stopped running (cycle 18).

### Log Files

**Deployment Logs:**
- `deploy-production_YYYYMMDD_HHMMSS.log`
- `rollback-deployment_YYYYMMDD_HHMMSS.log`

**Application Logs:**
- `/var/www/html/osmanager/storage/logs/laravel.log`
- `/var/www/html/osmanager/storage/logs/queue-worker.log`

**System Logs:**
- `/var/log/supervisor/supervisord.log`
- `/var/log/apache2/error.log` (or nginx)

## 🔐 Security Notes

- All scripts require appropriate permissions (sudo for system changes)
- Database credentials are never exposed in logs
- Backup files are created with restricted permissions
- Queue workers run as www-data user for security

## 📞 Support

If deployment fails:
1. Check the deployment log files
2. Run the test script for detailed diagnostics
3. Use rollback script if needed
4. Review Laravel and system logs
5. Fix permissions and retry

## 🎉 Success Indicators

Deployment is successful when:
- ✅ `test-deployment.sh` passes all tests
- ✅ Web application loads without errors
- ✅ Invoice upload functionality works
- ✅ Queue workers are processing jobs
- ✅ Database connectivity is confirmed
- ✅ File permissions are correct

---

*Generated for OS Manager deployment with invoice bulk upload system and Amazon payment processing features.*