# 🔄 Dedicated Queue Workers Setup

This setup replaces the queue priority system with **truly independent** queue workers for coffee orders and invoice processing.

## 🎯 **What This Solves**

**Problem:** Queue priority still blocks coffee orders when many invoices are uploaded
**Solution:** Separate, dedicated workers for each system - no blocking possible!

---

## 📁 **Files Included**

1. **`osmanager-test-dedicated-workers.conf`** - Supervisor configuration
2. **`setup-dedicated-workers.sh`** - Automated setup script  
3. **`README-dedicated-workers.md`** - This file

---

## 🚀 **Quick Setup**

### **Copy Files to Server:**
```bash
# Copy these 3 files to the server
scp osmanager-test-dedicated-workers.conf jon@server:/var/www/html/osmanager-test/
scp setup-dedicated-workers.sh jon@server:/var/www/html/osmanager-test/
scp README-dedicated-workers.md jon@server:/var/www/html/osmanager-test/
```

### **Run Setup on Server:**
```bash
# SSH to server
ssh jon@server

# Go to test environment
cd /var/www/html/osmanager-test

# Run the setup script
sudo ./setup-dedicated-workers.sh /var/www/html/osmanager-test
```

---

## ✅ **Expected Result**

### **Before (Priority System):**
- Upload 20 invoices → Coffee orders blocked for 20+ minutes

### **After (Dedicated Workers):**
- **2 Coffee Workers**: Handle coffee orders continuously (never blocked)
- **2 Invoice Workers**: Handle invoice processing continuously (never blocked)  
- Upload 20 invoices → Coffee orders still process in 1-2 minutes ✅

---

## 📊 **Verification Commands**

### **Check Workers Are Running:**
```bash
# Should show both coffee and invoice workers
sudo supervisorctl status | grep -E "(coffee-worker|invoice-worker)"

# Should show separate queue workers
ps aux | grep "queue:work" | grep osmanager-test
```

### **Monitor Processing:**
```bash
# Watch coffee jobs (in one terminal)
tail -f storage/logs/coffee-worker.log

# Watch invoice jobs (in another terminal)  
tail -f storage/logs/invoice-worker.log
```

### **Test Independence:**
1. Upload multiple invoices
2. Check that coffee orders still process normally
3. Both log files should show activity independently

---

## 🔧 **Management Commands**

### **Restart Workers:**
```bash
# Restart coffee workers only
sudo supervisorctl restart osmanager-test-coffee-worker:*

# Restart invoice workers only
sudo supervisorctl restart osmanager-test-invoice-worker:*

# Restart all dedicated workers
sudo supervisorctl restart osmanager-test-coffee-worker:* osmanager-test-invoice-worker:*
```

### **Stop/Start Workers:**
```bash
# Stop all
sudo supervisorctl stop osmanager-test-coffee-worker:* osmanager-test-invoice-worker:*

# Start all
sudo supervisorctl start osmanager-test-coffee-worker:* osmanager-test-invoice-worker:*
```

---

## 🎯 **How It Works**

### **Coffee Workers (2 processes):**
- **Queue:** `default` (coffee monitoring jobs only)
- **Sleep:** 3 seconds between jobs
- **Log:** `coffee-worker.log`
- **Never blocked by:** Invoice processing

### **Invoice Workers (2 processes):**
- **Queue:** `invoices` (invoice parsing jobs only)
- **Sleep:** 1 second between jobs (faster response)
- **Log:** `invoice-worker.log`  
- **Never blocked by:** Coffee jobs

---

## 🚨 **Troubleshooting**

### **If Coffee Orders Stop Working:**
```bash
# Check coffee worker status
sudo supervisorctl status osmanager-test-coffee-worker:*

# Check coffee worker logs
tail -50 storage/logs/coffee-worker.log

# Restart coffee workers
sudo supervisorctl restart osmanager-test-coffee-worker:*
```

### **If Invoice Processing Stops:**
```bash
# Check invoice worker status
sudo supervisorctl status osmanager-test-invoice-worker:*

# Check invoice worker logs  
tail -50 storage/logs/invoice-worker.log

# Restart invoice workers
sudo supervisorctl restart osmanager-test-invoice-worker:*
```

### **If Setup Fails:**
```bash
# Check supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log

# Verify configuration files
sudo supervisorctl reread
sudo supervisorctl update
```

---

## 🔄 **Reverting Back**

If you need to go back to the old system:

```bash
# Stop dedicated workers
sudo supervisorctl stop osmanager-test-coffee-worker:* osmanager-test-invoice-worker:*

# Remove configuration
sudo rm /etc/supervisor/conf.d/osmanager-test-dedicated-workers.conf

# Restore old configuration (if backed up)
sudo cp /etc/supervisor/conf.d/osmanager-test-queue-worker.conf.backup-* /etc/supervisor/conf.d/osmanager-test-queue-worker.conf

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start osmanager-test-queue-worker:*
```

---

## 📈 **For Production**

To apply the same setup to production:

1. Create `osmanager-dedicated-workers.conf` (update paths for production)
2. Run `./setup-dedicated-workers.sh /var/www/html/osmanager`

---

*This setup provides true independence between coffee and invoice processing systems.*