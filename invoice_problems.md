# Invoice Attachment 404s

## Observed Behaviour
- Opening older invoice attachments (e.g. invoice `9587`) returns HTTP 404 through `/invoice-attachments/{id}/view`.
- Error originates in `InvoiceAttachmentController::view()` which aborts whenever `$attachment->exists()` fails.

## Root Cause
- `InvoiceAttachment::exists()` checks the file on the `private` storage disk.  
- Legacy attachments are still stored under `storage/app/private/invoices/attachments`.  
- That folder is owned by `www-data:www-data` with `0700` permissions, so any process running as a different user (CLI shells, queue workers, some PHP-FPM pools) cannot read the files.  
- Consequently the existence check fails and the controller responds with `404` even though the DB row remains.

## Evidence
- `ls -ld storage/app/private/invoices/attachments` → `drwx------ 4 www-data www-data …`.  
- Directories for newer uploads (`storage/app/private/invoices/{year}/{month}/{invoice_id}`) are readable (`rwxrwxr-x`), so recent attachments continue to work.

## Recommended Fix
1. Restore shared permissions on the legacy path:
   ```bash
   sudo chown -R www-data:www-data storage/app/private/invoices/attachments
   sudo chmod -R 2775 storage/app/private/invoices/attachments
   ```
   (Use the actual web user/group if different.)
2. Run a quick SQL check to confirm which records still reference the legacy folder:
   ```sql
   SELECT id, invoice_id, file_path
   FROM invoice_attachments
   WHERE file_path LIKE 'invoices/attachments/%';
   ```
3. Optionally migrate those files into the year/month structure so everything lives under `invoices/{year}/{month}/{invoice_id}` and update the `file_path` column accordingly. This removes dependence on the restrictive legacy tree going forward.

After applying the permission fix (or migration), retest invoice `9587`—the attachment should load normally.
