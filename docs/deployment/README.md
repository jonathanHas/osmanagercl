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

All deployment scripts are now organized in `scripts/deployment/` with comprehensive documentation at [scripts/deployment/README.md](../../scripts/deployment/README.md).

### **Script Categories:**

**Deploy Scripts (`scripts/deployment/deploy/`):**
- `deploy-streamlined.sh` - Main deployment script with label sync
- `deploy-fast.sh` - Streamlined deployment for quick updates
- `deploy-production.sh` - Enhanced with invoice parser setup
- `deploy.sh` - Full comprehensive deployment
- `rollback-deployment.sh` - Emergency rollback

**Setup Scripts (`scripts/deployment/setup/`):**
- `setup-queue-workers.sh` - Queue worker setup for any environment
- `setup-dedicated-workers.sh` - Separate workers for coffee/invoice queues
- `setup-invoice-parser-production.sh` - Python parser setup
- `queue-worker-setup.sh` - Basic queue worker configuration

**Testing Scripts (`scripts/deployment/testing/`):**
- `verify-deployment-ready.sh` - Pre-deployment verification
- `test-deployment.sh` - Post-deployment testing
- `deploy-fast-dryrun.sh` - Preview deployment changes

**Debug Scripts (`scripts/deployment/debug/`):**
- `debug-parser-test.sh` - Detailed parser diagnostics
- `debug-queue-processing.sh` - Queue processing troubleshooting

**Permission Scripts (`scripts/deployment/permissions/`):**
- `fix-all-permissions.sh` - Comprehensive permission fixing
- `fix-storage-permissions.sh` - Fix storage directory permissions
- `fix-attachment-permissions.sh` - Fix invoice attachment permissions

---

## 🎯 **Quick Start**

### **For New Production Deployment:**
1. **Follow [General Production Deployment](./production-deployment-guide.md)**
2. **If using invoice features:** Follow [Invoice Parsing Deployment](./invoice-parsing-deployment-guide.md)
3. **Test everything** with the provided scripts

### **For Existing Deployments Adding Invoice Features:**
1. **Run:** `./scripts/deployment/setup/setup-dedicated-workers.sh /path/to/app`
2. **Test:** `./scripts/deployment/debug/debug-queue-processing.sh /path/to/app`

---

## 🚨 **Common Issues & Solutions**

### **Invoice Processing Issues:**
- **Invoices stuck "pending"** → Use `scripts/deployment/setup/setup-dedicated-workers.sh`
- **Parser not working** → Use `scripts/deployment/debug/debug-parser-test.sh` 
- **Queue workers failing** → Use `scripts/deployment/debug/debug-queue-processing.sh`

### **General Deployment Issues:**
- **Permission errors** → Use `scripts/deployment/permissions/fix-all-permissions.sh`
- **Missing dependencies** → Run `scripts/deployment/testing/verify-deployment-ready.sh`
- **Performance issues** → See [Performance Guide](../development/performance-optimization-guide.md)

---

## 📋 **Environment-Specific Notes**

### **Test Environment:**
- Use scripts with `/var/www/html/osmanager-test` path
- Coffee job blocking is common in test environments
- Run `scripts/deployment/setup/setup-dedicated-workers.sh` early in setup

### **Production Environment:**
- Follow security checklist in general deployment guide
- Set up proper monitoring and backups
- Use queue priority from day one to prevent issues

---

## 🔗 **Related Documentation**

- **[Feature Documentation](../features/)** - Individual feature setup guides
- **[Performance Optimization](../development/performance-optimization-guide.md)** - Speed improvements
- **[Troubleshooting Guide](../troubleshooting/index.md)** - General issue resolution
- **[Sales Data Import Plan](../features/sales-data-import-plan.md)** - Performance optimization patterns

---

*For questions about deployment, refer to the specific guides above or the feature documentation for detailed information about individual systems.*