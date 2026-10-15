# Backups

Nightly backup of the production database and application files, run **on production by
the Laravel scheduler** using [spatie/laravel-backup](https://spatie.be/docs/laravel-backup/v9).
Same package and shape as timekeeper's backup, so one mental model covers both apps.

## What is backed up

| | |
|---|---|
| Database | The `mysql` connection only (`osmanager` on production). Dumped with `mysqldump --single-transaction --routines --triggers`, gzipped. |
| Files | The whole project tree **including `.env`** and `storage/app` (invoice PDFs, delivery scans, F&V thumbnails, Udea data). |
| Also | `OSACCOUNTS_FILE_PATH` when it exists outside the project (empty on production today). |

**Not backed up, and why:**

- `vendor/`, `node_modules/`, `.git/`, `scripts/invoice-parser/venv/` — reproducible with
  `composer install`, `npm install`, git, and `pip install -r requirements.txt`.
- `storage/logs/` (~1 GB on production), framework caches, sessions, compiled views, `tests/`.
- `storage/app/private/temp/conversions/` — PDF→image conversions rebuilt on demand.
- **The POS (uniCenta) and OSAccounts databases.** On production they live on other machines
  (`POS_DB_HOST`, `INV_DB_HOST`) and the POS database is the live till. They are not this app's
  data and have no backup *here*. If the till is ever to be covered from this box, add the
  connection name to `backup.source.databases` and give its `.env` user `LOCK TABLES`/`SELECT`
  rights for `mysqldump` — but check the till has nothing of its own first.

## Where and when

| | |
|---|---|
| Destination | The `backups` disk → `BACKUP_PATH`, default `/var/backups/osmanager`. **Must be outside the project**, or the archive is swept into the next backup and sits under the web root. |
| Archive | `BACKUP_PATH/osmanager/osmanager-YYYY-MM-DD-HH-MM-SS.zip` containing `db-dumps/mysql-osmanager-….sql.gz` plus the files, paths relative to the project root. |
| Schedule | `backup:clean` 01:00 → `backup:run` 01:30 → `backup:monitor` 02:30, in `routes/console.php`. Runs from the existing `/etc/cron.d/osmanager` scheduler as `www-data`; no new cron entry. |
| Log | All three append to `storage/logs/backup.log`. This is the record that does not depend on mail. |
| Retention | Everything for 7 days, dailies for 30, weeklies for 8 weeks, monthlies for 6 months, yearlies for 2 years; then oldest-first once total storage passes `BACKUP_MAX_STORAGE_MB` (30 000). |
| Size | Dev archive: 537 MB, 6 294 files, 47 s. Production will be larger (`storage/app` is 762 MB there, mostly PDFs that do not compress). Expect roughly 0.7–1 GB a night; with the 30 GB ceiling that is 30+ days of nightly archives on a disk with 73 GB free. |

## Alerts

Only problems are mailed: backup failed, cleanup failed, or the monitor found no healthy
backup (none newer than a day, or storage over the ceiling). Successful runs are silent
except in `backup.log`. Recipients come from `BACKUP_NOTIFICATION_EMAIL` (comma-separated).
Empty means no mail at all — problems still land in `backup.log`.

> **Careful with that key.** The package validates it at boot. A malformed address throws
> from *every* artisan command, including `schedule:run`, which would stop all scheduled jobs,
> not only backups. `config/backup.php` splits and trims the value so the empty case is safe;
> after editing it on production, run `php artisan schedule:list` once to prove artisan still boots.

## Configuration

- `config/backup.php` — sources, exclusions, destination, retention, notifications.
- `config/filesystems.php` — the `backups` disk (`BACKUP_PATH`).
- `config/database.php` — the `dump` options on the `mysql` connection (`DB_DUMP_BINARY_PATH`,
  default `/usr/bin`, must contain `mysqldump`).
- `routes/console.php` — the three scheduled commands.
- `tests/Feature/BackupConfigurationTest.php` — guards all of the above.

`.env` keys (all in `.env.example`):

| Key | Default | Notes |
|---|---|---|
| `BACKUP_PATH` | `/var/backups/osmanager` | Outside the project. Dev: `/home/<you>/backups/osmanager`. |
| `BACKUP_NOTIFICATION_EMAIL` | empty | Comma-separated. Empty = no mail. |
| `BACKUP_ARCHIVE_PASSWORD` | empty | Set to AES-256-encrypt the zip (it contains `.env`). Restoring then needs `unzip -P` or 7-Zip; plain zip is simpler to restore. |
| `BACKUP_MAX_STORAGE_MB` | 30000 | Storage ceiling for both cleanup and the health check. |
| `DB_DUMP_BINARY_PATH` | `/usr/bin` | Directory holding `mysqldump`. |

## Production setup (one-time, needs sudo)

Deployment ships the package with `vendor/` as usual. Beyond that, production needs:

```bash
# 1. Destination, owned by the scheduler user
sudo mkdir -p /var/backups/osmanager
sudo chown www-data:www-data /var/backups/osmanager
sudo chmod 750 /var/backups/osmanager

# 2. .env on production (/var/www/html/osmanager/.env)
#    BACKUP_PATH=/var/backups/osmanager
#    BACKUP_NOTIFICATION_EMAIL=<owner address>
sudo -u www-data php artisan config:clear    # only if config is cached
sudo -u www-data php artisan schedule:list   # proves artisan boots with the new keys

# 3. First run by hand, as the scheduler user, then check it
sudo -u www-data php artisan backup:run
sudo -u www-data php artisan backup:list
sudo -u www-data php artisan backup:monitor
```

Next morning: `tail storage/logs/backup.log` should show clean → run → monitor with
"considered healthy" at the end.

## Checking on it

```bash
php artisan backup:list                 # count, newest, storage used, healthy?
tail -40 storage/logs/backup.log        # last nights' runs
ls -lh /var/backups/osmanager/osmanager # the archives themselves
```

## Restore

```bash
unzip /var/backups/osmanager/osmanager/osmanager-<timestamp>.zip -d /tmp/restore
# (add -P '<BACKUP_ARCHIVE_PASSWORD>' if encryption is on)

# Database — into an empty database of the right name
gunzip -c /tmp/restore/db-dumps/mysql-osmanager-*.sql.gz | mysql -u <user> -p osmanager

# Files: .env and storage/app are the parts that exist nowhere else
cp /tmp/restore/.env /var/www/html/osmanager/.env
rsync -a /tmp/restore/storage/app/ /var/www/html/osmanager/storage/app/

# Rebuild what was excluded
composer install --no-dev && npm install && npm run build
python3 -m venv scripts/invoice-parser/venv && scripts/invoice-parser/venv/bin/pip install -r scripts/invoice-parser/requirements.txt
php artisan migrate --force && php artisan optimize:clear
```

If the dump was made by a MariaDB 11+ client and is being loaded with an older `mysql`
client, the first line (`/*M!999999\- enable the sandbox mode */`) is rejected; drop it with
`tail -n +2` in the pipe. Same-server restores do not hit this.

To pull a production dump down to dev, see the [Fresh Install Guide](./fresh-install-guide.md#9a-application-database-from-production).

## Known gaps

- **On-box only.** The archives sit on the production disk. A disk failure takes the app and
  its backups together. Off-site is one more entry in `destination.disks` (an S3-compatible
  bucket or an rsync of `/var/backups/osmanager` to another machine); turn on
  `BACKUP_ARCHIVE_PASSWORD` at the same time, since the archive holds `.env`.
- **Exclusions match on `realpath()`.** A symlink inside the tree that points outside it is
  archived under its target's path, which is why `/usr/bin` is in the exclude list (the venv's
  `bin/python*` links). Any new symlink of that kind needs the same treatment.
