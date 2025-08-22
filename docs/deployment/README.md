# 🚀 OS Manager Deployment Documentation

This directory contains comprehensive deployment guides for OS Manager in production environments.

---

## 📚 **Deployment Guides**

### **🏗️ [General Production Deployment](./production-deployment-guide.md)**
Complete guide for deploying OS Manager to production, covering:
- Server setup and configuration
- Web server setup (Apache/Nginx)
- SSL certificates and security
- Database configuration
- Basic monitoring and maintenance
- Application optimization

**Use this first** for any new production deployment.

### **📄 [Invoice Parsing System Deployment](./invoice-parsing-deployment-guide.md)**  
Specialized guide for the invoice bulk upload and parsing system, covering:
- Python parser setup with OCR dependencies
- Queue worker configuration with priority handling
- Coffee KDS job blocking solutions
- Parser debugging and troubleshooting
- Performance optimization for parsing

**Use this after** completing the general deployment for invoice parsing features.

### **📦 [Legacy Import Guide](./import_invoices.md)**
Guide for importing historical data from OSAccounts system.

---

## 🛠️ **Deployment Scripts**

All deployment scripts are located in the project root and are documented in [DEPLOYMENT_SCRIPTS_README.md](../../DEPLOYMENT_SCRIPTS_README.md).

### **Main Scripts:**
- `deploy-production.sh` - Master deployment orchestrator
- `setup-queue-workers.sh` - Queue worker setup for any environment
- `enable-invoice-priority-queue.sh` - Fix for coffee job blocking (NEW)
- `verify-deployment-ready.sh` - Pre-deployment verification
- `test-deployment.sh` - Post-deployment testing

### **Debug Scripts:**
- `debug-parser-test.sh` - Detailed parser diagnostics
- `debug-queue-processing.sh` - Queue processing troubleshooting

### **Utility Scripts:**
- `fix-all-permissions.sh` - Comprehensive permission fixing
- `setup-invoice-parser-production.sh` - Python parser setup

---

## 🎯 **Quick Start**

### **For New Production Deployment:**
1. **Follow [General Production Deployment](./production-deployment-guide.md)**
2. **If using invoice features:** Follow [Invoice Parsing Deployment](./invoice-parsing-deployment-guide.md)
3. **Test everything** with the provided scripts

### **For Existing Deployments Adding Invoice Features:**
1. **Run:** `./setup-queue-workers.sh /path/to/app`
2. **Run:** `./enable-invoice-priority-queue.sh /path/to/app`
3. **Test:** `./debug-queue-processing.sh /path/to/app`

---

## 🚨 **Common Issues & Solutions**

### **Invoice Processing Issues:**
- **Invoices stuck "pending"** → Use `enable-invoice-priority-queue.sh`
- **Parser not working** → Use `debug-parser-test.sh` 
- **Queue workers failing** → Use `debug-queue-processing.sh`

### **General Deployment Issues:**
- **Permission errors** → Use `fix-all-permissions.sh`
- **Missing dependencies** → Run `verify-deployment-ready.sh`
- **Performance issues** → See [Performance Guide](../development/performance-optimization-guide.md)

---

## 📋 **Environment-Specific Notes**

### **Test Environment:**
- Use scripts with `/var/www/html/osmanager-test` path
- Coffee job blocking is common in test environments
- Run `enable-invoice-priority-queue.sh` early in setup

### **Production Environment:**
- Follow security checklist in general deployment guide
- Set up proper monitoring and backups
- Use queue priority from day one to prevent issues

---

## 🔗 **Related Documentation**

- **[Feature Documentation](../features/)** - Individual feature setup guides
- **[Performance Optimization](../development/performance-optimization-guide.md)** - Speed improvements
- **[Troubleshooting Guide](../development/troubleshooting.md)** - General issue resolution
- **[Sales Data Import Plan](../features/sales-data-import-plan.md)** - Performance optimization patterns

---

*For questions about deployment, refer to the specific guides above or the feature documentation for detailed information about individual systems.*