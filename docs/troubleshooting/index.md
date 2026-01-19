# Troubleshooting Guide

This guide covers common issues and their solutions when developing OSManager CL.

## Quick Navigation

| Issue Type | File | Common Problems |
|------------|------|-----------------|
| **PDF & File Upload** | [pdf-file-issues.md](./pdf-file-issues.md) | Upload failures, attachments missing, document conversion |
| **Frontend & JavaScript** | [frontend-issues.md](./frontend-issues.md) | Alpine.js errors, Blade conflicts, Chart.js issues |
| **Backend & System** | [backend-system-issues.md](./backend-system-issues.md) | Database connections, imports, recovery |

---

## Development Tools

### Useful Artisan Commands

```bash
# Clear specific caches
php artisan view:clear      # Clear compiled views
php artisan config:clear    # Clear configuration cache
php artisan route:clear     # Clear route cache
php artisan cache:clear     # Clear application cache

# Clear everything
php artisan optimize:clear

# Debug routes
php artisan route:list

# Debug models and data
php artisan tinker
```

### Testing Blade Templates

```bash
# Test specific view compilation
php artisan tinker
>>> view('your.view.name')->render();

# Test with data
>>> view('your.view.name', ['data' => 'value'])->render();
```

---

## Prevention Tips

1. **Always escape Alpine.js events** that match Blade directive names
2. **Avoid template literals** with Blade syntax inside
3. **Use null coalescing** operators for optional variables
4. **Test template compilation** after major changes
5. **Keep backups** of working templates before modifications
6. **Document known issues** in CLAUDE.md for future reference

---

## When All Else Fails

1. **Restore from backup** if available
2. **Compare with working similar templates**
3. **Start with minimal template** and add complexity gradually
4. **Check Laravel documentation** for Blade syntax changes
5. **Review recent git commits** for breaking changes

---

## Getting Help

- Check Laravel Blade documentation
- Search Laravel forums and Stack Overflow
- Review similar templates in the codebase
- Create minimal reproduction case for debugging

---

## Related Documentation

- [Known Issues](../development/known-issues.md) - Previously resolved issues
- [Quick Start Guide](../development/quick-start-guide.md) - Development setup
- [AI Assistant Guide](../development/ai-assistant-guide.md) - Guidelines for AI assistants
