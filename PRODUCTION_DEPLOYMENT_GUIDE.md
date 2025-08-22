# 🚀 Production Deployment Guide - OS Manager
## Based on Real Deployment Experience

This guide documents the complete production deployment process for OS Manager with invoice bulk upload system, based on actual deployment experience and issues encountered.

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

## 🎯 **Deployment Process**

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

### **Step 6: Run Post-Deployment Tests**
```bash
./test-deployment.sh /var/www/html/osmanager www-data www-data
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

### **Issue 6: Queue Workers Not Processing**
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

*This guide is based on actual deployment experience. Update as new issues are discovered.*