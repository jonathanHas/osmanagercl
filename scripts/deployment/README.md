# Deployment Scripts Documentation

This directory contains all shell scripts used for deploying, setting up, and maintaining the OS Manager application.

## 📁 Directory Structure

```
deployment/
├── deploy/           # Main deployment scripts
├── setup/            # One-time setup scripts  
├── permissions/      # Permission fixing scripts
├── testing/          # Testing and verification
└── debug/            # Debug utilities
```

## 🚀 Quick Start

For most deployments, use the fast deployment script:

```bash
./scripts/deployment/deploy/deploy-fast.sh
```

Or use the symbolic link from project root:

```bash
./deploy-fast.sh
```

## 📋 Script Reference

### Deploy Scripts (`deploy/`)

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `deploy.sh` | Full comprehensive deployment with all checks | Initial deployment or major updates |
| `deploy-fast.sh` | Streamlined deployment (recommended) | Regular deployments when infrastructure is stable |
| `deploy-production.sh` | Enhanced deployment with invoice parser setup | When deploying invoice parsing features |
| `deploy-streamlined.sh` | Optimized with automatic label template sync | When label templates need syncing |
| `rollback-deployment.sh` | Emergency rollback to previous state | When deployment fails and quick recovery needed |

#### Usage Examples

**Standard deployment:**
```bash
cd /var/www/html/osmanagercl
./scripts/deployment/deploy/deploy-fast.sh
```

**Deploy to test environment:**
```bash
./scripts/deployment/deploy/deploy-fast.sh test
```

**Full deployment with all checks:**
```bash
./scripts/deployment/deploy/deploy.sh
```

**Emergency rollback:**
```bash
ssh jon@lilThink2 "bash -s" < ./scripts/deployment/deploy/rollback-deployment.sh
```

### Setup Scripts (`setup/`)

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `setup-queue-workers.sh` | Configure supervisor-managed queue workers | Initial server setup |
| `setup-dedicated-workers.sh` | Setup separate workers for coffee/invoice queues | When queue isolation needed |
| `setup-invoice-parser-production.sh` | Setup Python invoice parser environment | First time parser deployment |
| `queue-worker-setup.sh` | Basic queue worker configuration | Simple queue setup |

#### Usage Examples

**Initial queue worker setup (run on production server):**
```bash
sudo ./setup-queue-workers.sh /var/www/html/osmanager www-data www-data 2
```

**Setup dedicated workers for better performance:**
```bash
sudo ./setup-dedicated-workers.sh /var/www/html/osmanager
```

**Setup invoice parser (run during deployment):**
```bash
sudo ./setup-invoice-parser-production.sh /var/www/html/osmanager www-data www-data
```

### Permission Scripts (`permissions/`)

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `fix-all-permissions.sh` | Comprehensive permission fix for entire app | After deployment or permission issues |
| `fix-storage-permissions.sh` | Fix storage directory permissions | File upload issues |
| `fix-storage-permissions-nosudo.sh` | Fix permissions without sudo | Development environment |
| `fix-attachment-permissions.sh` | Fix invoice attachment permissions | Attachment access issues |

#### Usage Examples

**Fix all permissions after deployment (on production):**
```bash
sudo ./fix-all-permissions.sh /var/www/html/osmanager www-data www-data
```

**Fix storage permissions locally:**
```bash
./fix-storage-permissions-nosudo.sh
```

### Testing Scripts (`testing/`)

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `test-deployment.sh` | Comprehensive post-deployment testing | After each deployment |
| `verify-deployment-ready.sh` | Pre-deployment verification checks | Before deployment |
| `deploy-fast-dryrun.sh` | Show what would be deployed without doing it | Planning deployment |

#### Usage Examples

**Verify before deployment:**
```bash
./verify-deployment-ready.sh /var/www/html/osmanager www-data www-data
```

**Test after deployment (on production):**
```bash
./test-deployment.sh /var/www/html/osmanager www-data www-data
```

**Dry run to see what would change:**
```bash
./deploy-fast-dryrun.sh
```

### Debug Scripts (`debug/`)

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `debug-parser-test.sh` | Diagnose invoice parser issues | Parser not working |
| `debug-queue-processing.sh` | Debug stuck queue jobs | Jobs stuck in pending |

#### Usage Examples

**Debug parser issues (on production):**
```bash
./debug-parser-test.sh /var/www/html/osmanager www-data
```

**Debug queue issues:**
```bash
./debug-queue-processing.sh /var/www/html/osmanager www-data
```

## 🔄 Deployment Workflow

### Standard Deployment Process

1. **Pre-deployment checks:**
   ```bash
   ./scripts/deployment/testing/verify-deployment-ready.sh
   ```

2. **Deploy to test environment first:**
   ```bash
   ./scripts/deployment/deploy/deploy-fast.sh test
   ```

3. **Test the deployment:**
   ```bash
   ssh jon@lilThink2 "cd /var/www/html/osmanager-test && ./test-deployment.sh"
   ```

4. **Deploy to production:**
   ```bash
   ./scripts/deployment/deploy/deploy-fast.sh production
   ```

5. **Fix permissions if needed:**
   ```bash
   ssh jon@lilThink2 "cd /var/www/html/osmanager && sudo ./fix-all-permissions.sh"
   ```

### First-Time Setup

1. **Clone repository on production:**
   ```bash
   cd /var/www/html
   git clone https://github.com/yourusername/osmanagercl.git osmanager
   ```

2. **Run full deployment:**
   ```bash
   ./scripts/deployment/deploy/deploy.sh
   ```

3. **Setup queue workers:**
   ```bash
   sudo ./scripts/deployment/setup/setup-dedicated-workers.sh /var/www/html/osmanager
   ```

4. **Setup invoice parser:**
   ```bash
   sudo ./scripts/deployment/setup/setup-invoice-parser-production.sh /var/www/html/osmanager
   ```

5. **Verify deployment:**
   ```bash
   ./scripts/deployment/testing/test-deployment.sh /var/www/html/osmanager
   ```

## ⚙️ Configuration

### Environment Variables

Scripts expect these paths to be configured:

- `DEV_DIR`: `/var/www/html/osmanagercl` (development)
- `PROD_PATH`: `/var/www/html/osmanager` (production)
- `TEST_PATH`: `/var/www/html/osmanager-test` (test environment)
- `PROD_USER`: `jon`
- `PROD_HOST`: `lilThink2`

### Prerequisites

- SSH key-based authentication to production server
- Sudo access on production server
- Git repository access
- MySQL/MariaDB database
- PHP 8.2+
- Node.js 18+
- Python 3.8+ (for invoice parser)
- Supervisor (for queue workers)

## 🔧 Troubleshooting

### Common Issues

**Permission denied errors:**
```bash
sudo ./scripts/deployment/permissions/fix-all-permissions.sh /var/www/html/osmanager www-data www-data
```

**Queue jobs not processing:**
```bash
sudo supervisorctl restart osmanager-coffee-worker:*
sudo supervisorctl restart osmanager-invoice-worker:*
```

**Invoice parser not working:**
```bash
./scripts/deployment/debug/debug-parser-test.sh /var/www/html/osmanager www-data
```

**Storage not writable:**
```bash
sudo ./scripts/deployment/permissions/fix-storage-permissions.sh
```

### Checking Deployment Status

**View current deployed version:**
```bash
ssh jon@lilThink2 "cd /var/www/html/osmanager && git log -1 --oneline"
```

**Check application status:**
```bash
ssh jon@lilThink2 "cd /var/www/html/osmanager && php artisan about"
```

**View queue worker status:**
```bash
ssh jon@lilThink2 "sudo supervisorctl status"
```

## 📝 Notes

- Always test deployments in the test environment first
- Database backups are automatically created during deployment
- Queue workers are automatically restarted after deployment
- The `deploy-fast.sh` script is recommended for regular deployments
- Use `deploy.sh` for initial setup or when major changes are made
- All scripts include colored output for better readability
- Deployment logs are saved with timestamps for auditing

## 🔗 Related Documentation

- [Main Project README](../../README.md)
- [CLAUDE.md](../../CLAUDE.md) - AI assistant instructions
- [Contributing Guidelines](../../CONTRIBUTING.md)
- [Invoice Parser Documentation](../invoice-parser/README.md)

## 📞 Support

For deployment issues:
1. Check the troubleshooting section above
2. Review deployment logs in project root (`deploy_*.log`)
3. Run debug scripts to diagnose specific issues
4. Check supervisor logs: `sudo supervisorctl tail -f osmanager-coffee-worker:*`

---

Last updated: August 2025