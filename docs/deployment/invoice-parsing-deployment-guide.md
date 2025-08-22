# 📄 Invoice Parsing System - Deployment Guide
## Complete Setup for Invoice Bulk Upload & Processing

This guide covers the specialized deployment requirements for OS Manager's invoice parsing system, including queue workers, Python parsers, and OCR setup. This supplements the [general production deployment guide](./production-deployment-guide.md).

> **Prerequisites:** Complete the [general production deployment](./production-deployment-guide.md) first, then follow this guide for invoice parsing features.

---

## 📋 **Pre-Deployment Checklist**

### **Server Requirements**
- [ ] Ubuntu 20.04+ or equivalent
- [ ] PHP 8.2+ with required extensions
- [ ] MySQL/MariaDB running
- [ ] Python 3.8+ available
- [ ] Sufficient disk space (>5GB free)

### **User Setup (CRITICAL)**
```bash
# Add deployment user to www-data group (REQUIRED for file permissions)
sudo usermod -a -G www-data jon

# Make web directories group-writable
sudo find /var/www/html -type d -exec chmod 775 {} \;
sudo chmod g+s /var/www/html  # Inherit group ownership

# Log out and back in for group membership to take effect
exit
ssh jon@server
```

### **System Dependencies**
```bash
# Install ALL required packages in one go to avoid issues
sudo apt-get update
sudo apt-get install -y \
    tesseract-ocr \
    poppler-utils \
    libreoffice \
    python3-venv \
    python3-dev \
    build-essential \
    supervisor
```

---

## 🎯 **Invoice Parsing Deployment Process**

### **Step 1: Run Pre-Deployment Verification**
```bash
./verify-deployment-ready.sh /var/www/html/osmanager www-data www-data
```

**Expected Result:** All checks should pass
**If Failed:** Install missing dependencies before continuing

### **Step 2: Start Deployment**
```bash
./deploy-production.sh
```

**Choose Environment:**
- Option 1: Test environment (recommended first)
- Option 2: Production environment

### **Step 3: Monitor Deployment Progress**

#### **Git and File Sync**
- Should complete without errors
- If git credential issues appear, they're usually non-blocking

#### **Parser Setup Phase**
**Expected Output:**
```
✅ Virtual environment already exists, verifying functionality...
✅ Invoice parser is already working, skipping setup
```

**If Parser Issues Occur:**
1. Note the error but let deployment continue
2. Fix manually afterward (see troubleshooting section)

#### **Permission Fixing Phase**
**Expected Output:**
```
✅ Basic write permissions working (user in www-data group)
✅ Final write permissions working
```

**If Permission Issues:**
- Choose "y" to continue deployment
- Permissions already set up properly via group membership

### **Step 4: Complete Manual Parser Setup (If Needed)**
```bash
# SSH to server
ssh jon@server

# Navigate to application directory
cd /var/www/html/osmanager  # or /osmanager-test

# Run parser setup with CORRECT path
sudo ./setup-invoice-parser-production.sh /var/www/html/osmanager www-data www-data
```

### **Step 5: Set Up Queue Workers (One-Time)**
```bash
# Copy supervisor configuration
sudo cp osmanager-queue-worker.conf /etc/supervisor/conf.d/

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start osmanager-queue-worker:*

# Verify workers are running
sudo supervisorctl status
```

### **Step 6: Set Up Dedicated Queue Workers (RECOMMENDED)**

**For true independence between coffee orders and invoice processing:**

```bash
# Copy the dedicated worker files to your server
scp osmanager-test-dedicated-workers.conf server:/path/to/app/
scp setup-dedicated-workers.sh server:/path/to/app/

# SSH to server and run setup
ssh user@server
cd /var/www/html/osmanager
sudo ./setup-dedicated-workers.sh /var/www/html/osmanager
```

**What this does:**
- Creates **2 Coffee workers** (handle coffee orders only - never blocked)
- Creates **2 Invoice workers** (handle invoices only - never blocked)  
- **True independence**: Upload 20 invoices, coffee orders still process normally
- Separate log files for easy monitoring

**Alternative: Priority Queue System (if dedicated workers not preferred):**
```bash
# Less optimal but simpler - uses priority instead of independence
./enable-invoice-priority-queue.sh /var/www/html/osmanager
```
⚠️ **Note**: Priority system still blocks coffee orders when many invoices are uploaded.

### **Step 7: Verify Dedicated Workers Setup**
```bash
# Check that both worker types are running
sudo supervisorctl status | grep -E "(coffee-worker|invoice-worker)"

# Should show:
# - 2 coffee workers (RUNNING)
# - 2 invoice workers (RUNNING)

# Verify separate processing
ps aux | grep "queue:work" | grep osmanager
# Should show:
# - Workers with --queue=default (coffee)
# - Workers with --queue=invoices (invoice)
```

### **Step 8: Test True Independence**
```bash
# Start monitoring both systems
tail -f storage/logs/coffee-worker.log &     # Coffee jobs
tail -f storage/logs/invoice-worker.log &    # Invoice jobs

# Upload multiple invoices and verify:
# 1. Invoice jobs process immediately (invoice-worker.log)
# 2. Coffee orders continue processing normally (coffee-worker.log)
# 3. No blocking occurs
```

### **Step 9: Run Post-Deployment Tests**
```bash
./test-deployment.sh /var/www/html/osmanager www-data www-data
```

**If parser tests fail:**
```bash
# Use the debug script to identify specific issues
./debug-parser-test.sh /var/www/html/osmanager

# Check queue processing issues (now with dedicated workers)
./debug-queue-processing.sh /var/www/html/osmanager
```

---

## 🔧 **Troubleshooting Common Issues**

### **Issue 1: Invoice Parser Directory Not Found**
**Problem:** `scripts/invoice-parser` directory missing from server
**Cause:** Parser files not committed to git
**Solution:**
1. Ensure parser files are committed locally
2. Re-run deployment to sync files

### **Issue 2: Python Virtual Environment Creation Fails**
**Problem:** `python3-venv package not available`
**Cause:** System package missing
**Solution:**
```bash
ssh jon@server "sudo apt-get install -y python3-venv"
```

### **Issue 3: Permission Denied Errors**
**Problem:** Can't write to web directories
**Cause:** User not in www-data group
**Solution:**
```bash
# Add user to group (CRITICAL STEP)
sudo usermod -a -G www-data jon

# Log out and back in
exit
ssh jon@server

# Verify group membership
groups  # Should show www-data
```

### **Issue 4: Sudo Password Prompts in SSH**
**Problem:** Scripts can't prompt for sudo password over SSH
**Cause:** Non-interactive SSH session
**Solution:**
- Let deployment continue with issues
- Run manual setup steps afterward

### **Issue 5: Parser Test Fails After Deployment**
**Problem:** `❌ Invoice parser failed`
**Cause:** Python packages not installed properly
**Solution:**
```bash
# Manual parser setup with correct path
sudo ./setup-invoice-parser-production.sh /var/www/html/osmanager www-data www-data

# Test manually
cd scripts/invoice-parser
source venv/bin/activate
python invoice_parser_laravel.py --help
```

### **Issue 6: Coffee Jobs Blocking Invoice Processing (SOLVED)**
**Problem:** Invoices stuck in "pending" status, coffee monitoring jobs taking 1+ minutes each
**Cause:** Coffee KDS monitoring jobs clogging the default queue
**Best Solution - Dedicated Workers:**
```bash
# Set up dedicated workers (recommended)
./setup-dedicated-workers.sh /var/www/html/osmanager

# Verify both worker types are running
sudo supervisorctl status | grep -E "(coffee-worker|invoice-worker)"

# Monitor independence
tail -f storage/logs/coffee-worker.log    # Coffee jobs
tail -f storage/logs/invoice-worker.log   # Invoice jobs
```

**Alternative Solution - Priority System:**
```bash
# Run the queue priority fix (less optimal)
./enable-invoice-priority-queue.sh /var/www/html/osmanager

# Clear queue backlog if needed
php artisan queue:clear
php artisan queue:flush

# Verify priority system
tail -f storage/logs/queue-worker.log
```
⚠️ **Note**: Priority system still blocks coffee when many invoices are uploaded.

### **Issue 7: Queue Workers Not Processing**
**Problem:** Jobs not being processed
**Diagnosis:**
```bash
# Check supervisor status
sudo supervisorctl status

# Check Laravel queue
php artisan queue:work --once

# Check logs
tail -f storage/logs/laravel.log
tail -f storage/logs/queue-worker.log

# Debug queue issues specifically
./debug-queue-processing.sh /var/www/html/osmanager
```

---

## ✅ **Verification Steps**

### **Basic Functionality Test**
```bash
# SSH to server
ssh jon@server
cd /var/www/html/osmanager

# Test Laravel
php artisan --version

# Test database
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected';"

# Test parser
cd scripts/invoice-parser
source venv/bin/activate
python invoice_parser_laravel.py --help
```

### **Web Interface Test**
1. Access application in browser
2. Navigate to invoice upload
3. Upload a test file
4. Verify processing works

### **Queue System Test**
```bash
# Check supervisor workers
sudo supervisorctl status

# Monitor queue processing
tail -f storage/logs/queue-worker.log
```

---

## 🎯 **Production Deployment Checklist**

**Before Production Deployment:**
- [ ] Test environment fully working
- [ ] All invoice upload features tested
- [ ] Queue workers processing jobs
- [ ] Parser handling all invoice types
- [ ] Database backup created

**Production-Specific Steps:**
- [ ] Use production domain/URL
- [ ] SSL certificate configured
- [ ] Database backup automated
- [ ] Monitoring set up
- [ ] Error logging configured

**Post-Production Steps:**
- [ ] Test all critical functionality
- [ ] Monitor logs for errors
- [ ] Verify queue processing
- [ ] Set up automated backups

---

## 📞 **Emergency Procedures**

### **If Deployment Fails Completely**
```bash
# Use emergency rollback
sudo ./rollback-deployment.sh /var/www/html/osmanager
```

### **If Only Parser Fails**
```bash
# Manual parser setup
sudo ./setup-invoice-parser-production.sh /var/www/html/osmanager www-data www-data
```

### **If Permissions Break**
```bash
# Fix all permissions
sudo ./fix-all-permissions.sh /var/www/html/osmanager www-data www-data
```

---

## 📝 **Known Working Configuration**

**System:**
- Ubuntu 24.04 LTS
- PHP 8.3
- Python 3.12
- MySQL 8.0

**Key Dependencies:**
- tesseract-ocr 5.3.4+
- poppler-utils
- libreoffice
- python3-venv
- supervisor

**User Setup:**
- Deployment user in www-data group
- Group write permissions on web directories
- Setgid bit on web root for inheritance

---

## 🔄 **For Future Deployments**

**What Should Work Smoothly:**
- ✅ Parser setup (skips if already installed)
- ✅ Permission fixing (uses group membership)
- ✅ File syncing (proper ownership)
- ✅ Database migrations

**What May Still Need Manual Steps:**
- ⚠️ First-time parser setup (if python packages missing)
- ⚠️ Queue worker configuration (one-time setup)
- ⚠️ Web server configuration changes

---

## 📚 **Additional Resources**

- **Main Scripts:**
  - `deploy-production.sh` - Main deployment
  - `verify-deployment-ready.sh` - Pre-deployment checks
  - `test-deployment.sh` - Post-deployment verification
  - `setup-invoice-parser-production.sh` - Parser setup
  - `fix-all-permissions.sh` - Permission fixing
  - `rollback-deployment.sh` - Emergency recovery

- **Configuration Files:**
  - `osmanager-queue-worker.conf` - Supervisor config
  - `DEPLOYMENT_SCRIPTS_README.md` - Script documentation

---

## 🎉 **Complete Solution Summary**

### ✅ **What We Achieved:**

**Before Optimization:**
- ❌ Invoices stuck in "pending" for 3+ minutes
- ❌ Coffee orders blocked when invoices uploaded  
- ❌ Single queue caused blocking issues
- ❌ PDF splitting failed due to timeouts

**After Dedicated Workers:**
- ✅ **Invoices process in 1-5 seconds**
- ✅ **Coffee orders unaffected** by invoice uploads
- ✅ **Upload 20 invoices**: Coffee still works normally
- ✅ **PDF splitting works** (no timeout issues)
- ✅ **True independence** between systems

### 🔧 **Final Architecture:**

```
Coffee Workers (2):  [Coffee Order 1] → [Coffee Order 2] → [Coffee Order 3]
                     ↓ (1-2 minutes each, never blocked)
                     Baristas receive orders continuously

Invoice Workers (2): [Invoice 1] → [Invoice 2] → [Invoice 3] 
                     ↓ (1-5 seconds each, never blocked)
                     Accounting gets instant processing
```

### 📊 **Key Files Created:**
- `osmanager-test-dedicated-workers.conf` - Supervisor configuration
- `setup-dedicated-workers.sh` - Automated setup script
- `README-dedicated-workers.md` - Complete instructions

### 🎯 **Performance Results:**
- **Invoice processing**: ~3 minutes → **~3 seconds** (60x faster)
- **Coffee order independence**: From blocked → **always working**
- **PDF splitting**: From timeout errors → **working correctly**
- **System reliability**: Single point of failure → **independent systems**

---

*This guide documents the complete solution to the invoice parsing queue blocking issue discovered and resolved during deployment testing.*