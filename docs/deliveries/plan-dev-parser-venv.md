# Side plan — invoice-parser venv on the dev machine

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-10-19

Side plan (see `planimp.md`, "A small fix that must not wait for the current
cycle"). Report in `docs/deliveries/implemented-dev-parser-venv.md`. Kickoff:

```
Read docs/deliveries/planimp.md. You are the Implementer. Implement docs/deliveries/plan-dev-parser-venv.md.
```

## Goal

Importing a supplier PDF at `/deliveries/create` on the dev machine fails with
`Delivery parser execution failed: … ModuleNotFoundError: No module named
'pytesseract'`. After this plan the Python parser runs on this machine from a
real virtual environment at `scripts/invoice-parser/venv`, with the same
interpreter major version and the same pinned packages as production, so the
PDF import on `/deliveries/create` (and the invoice upload parsers, which share
the venv) work locally. No application code changes. From the owner's report,
2026-10-19 (`~/Downloads/Order_4804899.pdf`, a Udea order).

## Context

**How the parser is launched.** `App\Services\DeliveryParsingService::parseDeliveryPdf()`
(lines 36–60) runs `scripts/invoice-parser/venv/bin/python` if that file
exists, otherwise `config('invoices.parsing.python_executable')` =
`/usr/bin/python3`. `InvoiceParsingService`, `InvoiceBulkUploadController`
and `RtdController` do the same. The local `.env` sets none of the `PYTHON_*`
/ `INVOICE_PARSER_*` variables, so the defaults in `config/invoices.php` apply.
The process runs as Apache's `www-data` user.

**Why it fails here.** There is no venv (`scripts/invoice-parser/venv` does not
exist; it is gitignored), so the system `/usr/bin/python3` runs the parser
and it has none of `requirements.txt` installed (`pytesseract`, `pdfplumber`,
`pdf2image`, `pandas`, `xlrd` … all missing; only Pillow and openpyxl are
present as Ubuntu packages).

**Why the obvious fix does not work on this machine.**

- This is Ubuntu 26.04.1 with Python **3.14.4** and nothing else: no `pip`,
  no `ensurepip`, no `venv` module (`python3-venv` is not installed and
  installing it needs sudo, which the Implementer session does not have).
- Even with `venv`, `requirements.txt` would not install on 3.14: the pins
  `numpy==2.2.4`, `pandas==2.2.3` and `pillow==11.1.0` publish no `cp314`
  wheels (checked on PyPI 2026-10-19), so pip would try to build them from
  source and fail. The pins must not be loosened: they are what production
  runs.
- Production (`lilThink2`, Ubuntu 24.04, `/var/www/html/osmanager`) runs the
  venv on **Python 3.12.3** with exactly the pinned set (`numpy==2.2.4`,
  `pandas==2.2.3`, `pillow==11.1.0`, `pdfplumber==0.11.5`,
  `pypdfium2==4.30.0`, `pytesseract==0.3.13`, `pdf2image==1.17.0`,
  `xlrd==2.0.1`, `openpyxl==3.1.5`, `python-docx==1.2.0`, `odfpy==1.4.1`).
  Dev should match that.

**The approach: a uv-managed CPython 3.12, no sudo.** `uv` installs into
`~/.local/bin` (already first on `PATH`) with a one-line script and can
download a standalone CPython 3.12 build. That interpreter has `venv`,
`ensurepip` and `pip`, so the rest is exactly `setup.sh`'s recipe.

**Where the interpreter must live.** Apache runs with `ProtectHome=read-only`
and `/home/jon` is mode `750` (`other::---` in its ACL), so `www-data`
cannot execute anything under `/home/jon`, including uv's default
`~/.local/share/uv/python/`. Install the interpreter under
`/var/www/html/uv-python/` instead (`/var/www/html` is `jon:www-data 2775`,
writable by jon, readable by www-data). The venv's `bin/python` is a symlink
to that interpreter, so this is what makes the Laravel process able to run
it. Keep it outside the repo so nothing needs gitignoring or excluding from
backups.

**The `apt` warning in the error.** The lines
`/usr/lib/python3/dist-packages/apt/__init__.py:37: Warning: W:Unable to read
/etc/apt/apt.conf.d/ …` are Ubuntu's `apport` exception hook: when a Python
process dies with an uncaught exception it imports `apt` to build a crash
report, and `www-data` cannot read `/etc/apt` (Apache's unit lists it under
`InaccessiblePaths`). It is noise that appears only after the real failure,
and the managed CPython has no apport hook. Nothing to fix.

**System tools the parser shells out to** are already installed:
`/usr/bin/tesseract`, `/usr/bin/pdftotext` (poppler), `/usr/bin/libreoffice`.

**Checks available.** `scripts/invoice-parser/delivery_parser_laravel.py
--file <pdf> --output json` prints a JSON result and exits 0 on success
(line 224). `php artisan invoice:test-parser` reports whether the venv is
found. `App\Services\DeliveryParsingService::checkConfiguration()` returns
`all_checks_passed`. The parser test suite is
`scripts/invoice-parser/tests/` (pytest; 208 tests passed on 2026-10-16 with
only pdfplumber installed). The upload form on `/deliveries/create` posts to
`deliveries.store-pdf` (`DeliveryController::storePdf()`, line 422) with
`supplier_id`, `delivery_date` and `pdf_file[]`; the Udea supplier is
`SupplierID` 5.

**Browser tooling.** The Playwright MCP is configured in `.mcp.json`
(`mcp__playwright__*` tools, including `browser_file_upload`); its login is
minted with `php docs/deposit/scripts/browser/mint.php --playwright` (see
`docs/deposit/scripts/browser/README.md`). The tools only appear in a session
that started after the MCP was approved.

## Constraints

- No application code changes. Do not edit `requirements.txt`, `setup.sh`,
  `config/invoices.php`, the services or the controllers. The pins are what
  production runs and the fix is environmental.
- Do not install anything with `sudo`/`apt`. The session cannot, and the
  `python3-venv` package would still leave the 3.14 wheel problem.
- Do not change the local `.env`.
- Nothing under `/home/jon` may be what `www-data` executes (see Context).
- Do not commit. The venv is gitignored; the only tracked file this plan
  touches is `docs/development/fresh-install-guide.md` (step 7).
- Dev data: the browser check in step 6 creates a delivery in the local
  `osmanager` database. That is fine on dev; leave it or delete it through
  the UI, and say which in the report.

## Out of scope

- Reviewing cycle 3 (`plan.md` / `implemented.md` in this folder, DONE but not
  yet reviewed). Do not touch those files.
- Changing `setup.sh` to use uv, or any production setup.
- The scratchpad venvs earlier sessions built with `--without-pip` +
  `get-pip.py` (session-specific, no longer present).
- Adding `/var/www/html/uv-python` to any script or doc other than the
  fresh-install note in step 7.

## Steps

### 1. Install uv
Files: none (installs `~/.local/bin/uv`).
What:
```
curl -LsSf https://astral.sh/uv/install.sh | env UV_NO_MODIFY_PATH=1 sh
```
`UV_NO_MODIFY_PATH=1` stops the installer editing shell rc files;
`~/.local/bin` is already on `PATH`.
Check: `uv --version` prints a version.

### 2. Install a managed CPython 3.12 where www-data can read it
Files: none (creates `/var/www/html/uv-python/cpython-3.12.*-linux-x86_64-gnu/`).
What:
```
uv python install 3.12 --install-dir /var/www/html/uv-python
PY=$(ls -d /var/www/html/uv-python/cpython-3.12.*-linux-x86_64-gnu/bin/python3.12)
```
Check: `$PY --version` prints `Python 3.12.x`; `$PY -m ensurepip --version`
succeeds; `namei -m "$PY"` shows every directory component with `r-x` for
*other* and the binary with `x` for other (this is the www-data test, since
the session cannot switch user).

### 3. Create the venv with that interpreter, the way `setup.sh` does
Files: none (creates `scripts/invoice-parser/venv/`, gitignored).
What:
```
cd /var/www/html/osmanagercl/scripts/invoice-parser
$PY -m venv venv
venv/bin/pip install --upgrade pip
venv/bin/pip install -r requirements.txt
```
Use the plain `venv` module, not `uv venv` (that would not ship pip and would
diverge from production's layout).
Check: `venv/bin/python --version` → `Python 3.12.x`;
`readlink -f venv/bin/python` resolves under `/var/www/html/uv-python/`;
`venv/bin/pip freeze | grep -iE '^(numpy|pandas|pillow|pdfplumber|pypdfium2|pytesseract|pdf2image|xlrd|openpyxl|python-docx|odfpy)='`
shows exactly the production versions listed in Context (pypdfium2 may differ
in patch version; the rest must match). `git status --short` shows no new
tracked file.

### 4. Run the parser directly on the owner's PDF
Files: none.
What:
```
cd /var/www/html/osmanagercl
scripts/invoice-parser/venv/bin/python scripts/invoice-parser/delivery_parser_laravel.py \
  --file ~/Downloads/Order_4804899.pdf --output json | head -40; echo "exit=${PIPESTATUS[0]}"
```
Check: exit 0, JSON with `"success": true`, a detected supplier of Udea, and
a non-zero item count. Paste the first lines (header fields and the count,
not the whole item list) into the report. No `ModuleNotFoundError`, no apt
warning.

### 5. Parser test suite and the Laravel-side checks
Files: none.
What:
```
scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q
php artisan invoice:test-parser
php artisan tinker --execute='dump(app(App\Services\DeliveryParsingService::class)->checkConfiguration());'
```
Check: pytest all pass (208 expected; record the real count);
`invoice:test-parser` shows the virtual env as found; `checkConfiguration()`
returns `all_checks_passed => true` with an empty `errors` array.

### 6. Prove it through Apache (the www-data path)
Files: none.
What: the real failure was under `www-data`, so the final proof is the
upload itself. Preferred: with the Playwright MCP tools, mint the login
(`php docs/deposit/scripts/browser/mint.php --playwright`), open
`http://osmanager.local/deliveries/create`, choose supplier Udea (id 5), any
delivery date, attach `~/Downloads/Order_4804899.pdf` with
`browser_file_upload` on the `pdf_file[]` input, submit the PDF form, and
screenshot the result into the session scratchpad (absolute path).
Fallback if the MCP tools are not in the session: use the DevTools scripts
in `docs/deposit/scripts/browser/` if they can set a file input, otherwise
record in Deviations that the owner must retry the upload and set
`Status: BLOCKED` on this step only (the rest of the plan can still be DONE
in substance; the Planner will accept on the owner's retry).
Check: no "Failed to import PDF" flash; the page lands on the new delivery's
show page with Udea items listed; `tail -50 storage/logs/laravel.log` shows
`Executing delivery parser command` with `…/venv/bin/python` and no
`Delivery parser execution failed`.

### 7. Record the dev-machine recipe
Files: `docs/development/fresh-install-guide.md` (section 11, "Invoice Parser").
What: add a short sub-note after the manual recipe, roughly:

> **Ubuntu 26.04 / Python 3.14 (dev machines):** the pinned numpy, pandas and
> pillow have no 3.14 wheels and the distro ships no `venv`/`pip`. Use a
> uv-managed CPython 3.12 installed outside `/home` (Apache cannot read
> `/home/<user>`): `uv python install 3.12 --install-dir /var/www/html/uv-python`,
> then `.../bin/python3.12 -m venv venv` and `venv/bin/pip install -r requirements.txt`
> as above. Production stays on the distro Python 3.12.

Keep it to one paragraph; do not restructure the section.
Check: `git diff --stat` shows only that file changed.

## Verification

Run in order, record real output:

1. `scripts/invoice-parser/venv/bin/python --version` → `Python 3.12.x`.
2. `scripts/invoice-parser/venv/bin/python -c "import pytesseract, pdfplumber, pdf2image, pandas, xlrd, docx, odf; print('ok')"` → `ok`.
3. `scripts/invoice-parser/venv/bin/python scripts/invoice-parser/delivery_parser_laravel.py --file ~/Downloads/Order_4804899.pdf --output json > /dev/null; echo $?` → `0`.
4. `scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q` → all pass.
5. `php artisan invoice:test-parser` → venv found, no errors.
6. The browser upload from step 6 succeeds (or is recorded as the owner's retry).
7. `git status --short` → only `docs/development/fresh-install-guide.md` and the two files of this side plan; no venv or uv files tracked.

## Risks

- **uv installer blocked by the sandbox or no network**: then nothing here
  works without sudo. Report BLOCKED with the exact error; the owner's
  alternative is `! sudo apt install python3-venv` plus a 3.12 from the
  deadsnakes PPA, which is a different plan.
- **uv picks a different install layout name** than
  `cpython-3.12.*-linux-x86_64-gnu`: use whatever `uv python list
  --install-dir /var/www/html/uv-python` (or `uv python find 3.12`) reports and
  record the path.
- **Apache still cannot run the interpreter** (permission denied in the
  Laravel log): check `namei -m` on the resolved path and the venv; every
  directory needs `o+rx`. The session's umask is `0002`, which is fine.
- **A pin fails to install on 3.12**: it should not (production has the same
  set on 3.12.3). Do not change the pin; report BLOCKED with pip's output.
- **PrivateTmp**: Apache has its own `/tmp`. Irrelevant for the upload (Laravel
  stores the PDF under `storage/`), but if the parser ever writes temp files
  during OCR they land in Apache's private tmp, which is fine.

## Review

Planner: Fable 5.1, 2026-10-19. Read `implemented-dev-parser-venv.md` to the
end and reran every verification command myself.

### Criteria

| # | Criterion | Result |
|---|---|---|
| 1 | `venv/bin/python --version` → 3.12.x | pass: `Python 3.12.15`, resolves to `/var/www/html/uv-python/cpython-3.12.15-linux-x86_64-gnu/bin/python3.12` |
| 2 | All parser imports load | pass: `pytesseract, pdfplumber, pdf2image, pandas, xlrd, docx, odf, pypdfium2` → `ok`; pins match production (`numpy==2.2.4`, `pandas==2.2.3`, `pillow==11.1.0`, `pdfplumber==0.11.5`, `pypdfium2==4.30.0`, `pytesseract==0.3.13`) |
| 3 | Direct parse of `Order_4804899.pdf` | pass: exit 0, stderr empty, `success: True`, supplier Udea, 147 items, grand total 3238.27, `totals_match: True` |
| 4 | Parser test suite | pass: 208 passed |
| 5 | `invoice:test-parser` | pass: Virtual Env found (Python column still shows the 3.14 fallback, see notes) |
| 6 | Upload through Apache as www-data | pass: delivery 165 `DEL-20261019-144501`, supplier 5, draft, 147 items exists; `laravel.log` has 8 `Executing delivery parser command` lines using `venv/bin/python` and the last `Delivery parser execution failed` entry is from 14:15, before the fix |
| 7 | Nothing unreadable by www-data | pass: `find … ! -perm -o+r` → 0 under both `/var/www/html/uv-python` and the venv |
| 8 | Nothing new tracked | pass: `git ls-files` has no `venv/` or `uv-python`; the only tracked change is `docs/development/fresh-install-guide.md` |

### Deviations

1. **pypdfium2 pinned to 4.30.0 in the venv.** Accepted. `requirements.txt`
   leaves it unpinned and pip resolved 5.14.0, a major version away from what
   production runs. Pinning it in the venv keeps dev on production's set
   without touching the tracked file. The real fix is note 2 below.
2. **Fresh-install paragraph has two extra sentences** (uv one-liner, the
   pypdfium2 pin). Accepted; both are needed to follow the recipe.
3. **Step 6 by DevTools fallback** (scratchpad script over
   `docs/deposit/scripts/browser/lib.mjs`, `DOM.setFileInputFiles`). Accepted:
   the plan allowed it, and it proved the www-data path end to end rather
   than leaving it to the owner.

### Notes for Planner

1. **`invoice:test-parser` and `DeliveryParsingService::checkConfiguration()`
   report the fallback Python, not the venv interpreter**, so they pass with
   a venv that cannot import anything. Deferred to a small follow-up side
   plan ("parser environment checks"): report the interpreter that will run,
   and import `pytesseract` and `pdfplumber` through it. Worth doing because
   this is exactly how the dev failure went unnoticed.
2. **`pypdfium2` unpinned in `requirements.txt`.** Deferred to the same
   follow-up: add `pypdfium2==4.30.0` (production's version) so a fresh
   `setup.sh` reproduces production.
3. **Parser warnings on order 4804899** (price mismatch on `5004102`, deposit
   code 313 lines 24 units vs barrels section 36). Deferred; recorded as
   `findings/2026-10-19-udea-order-4804899-parser-warnings.md` for a
   deliveries or deposit cycle. Not an environment issue.
4. **Alpine `x-collapse` without the Collapse plugin** on the delivery show
   page. Deferred, cosmetic; noted in the same findings file.

### Dev data

Delivery 165 (draft, Udea, 147 items) stays in the local database as the
evidence of the www-data run. Delete it from its show page when it is in the
way.

### Archive

```
mkdir -p docs/deliveries/archive/2026-10-19-dev-parser-venv
mv docs/deliveries/plan-dev-parser-venv.md docs/deliveries/implemented-dev-parser-venv.md docs/deliveries/archive/2026-10-19-dev-parser-venv/
```
Plain `mv`, not `git mv`: both files are untracked. Commit the archive
folder together with `docs/development/fresh-install-guide.md` and the
`docs/deliveries/README.md` update when the deposit work is committed.
