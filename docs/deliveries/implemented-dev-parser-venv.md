# Side plan — invoice-parser venv on the dev machine — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-10-19

## Baseline
HEAD: 5775fbef
Pre-existing dirty files:
```
A  .mcp.json
M  app/Http/Controllers/DepositController.php
M  app/Http/Requests/UpdateProductDepositRequest.php
A  app/Models/AppSetting.php
M  app/Models/ProductDeposit.php
A  app/Services/Deposits/DepositPriceReviewService.php
A  database/migrations/2026_10_17_000001_add_price_review_to_product_deposits.php
 M docs/deliveries/README.md
M  docs/deposit/README.md
R  docs/deposit/implemented.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/implemented.md
R  docs/deposit/plan.md -> docs/deposit/archive/2026-10-05-cycle-3-go-live/plan.md
A  docs/deposit/archive/2026-10-17-cycle-4-price-review/implemented.md
A  docs/deposit/archive/2026-10-17-cycle-4-price-review/plan.md
A  docs/deposit/plan-browser-mcp.md
AM docs/deposit/scripts/browser/README.md
A  docs/deposit/scripts/browser/boot.php
A  docs/deposit/scripts/browser/cdp.mjs
A  docs/deposit/scripts/browser/clicks.mjs
A  docs/deposit/scripts/browser/flow.mjs
A  docs/deposit/scripts/browser/lib.mjs
A  docs/deposit/scripts/browser/measure.mjs
AM docs/deposit/scripts/browser/mint.php
A  docs/deposit/scripts/browser/pct.mjs
M  docs/features/barrel-deposit-tracking.md
A  resources/css/modernist.css
M  resources/views/deposits/index.blade.php
M  resources/views/layouts/admin.blade.php
M  routes/web.php
M  tests/Concerns/CreatesDepositPosTables.php
A  tests/Feature/DepositPriceReviewServiceTest.php
M  tests/Feature/DepositScreenTest.php
M  vite.config.js
?? docs/deliveries/plan-dev-parser-venv.md
?? docs/deposit/implemented-browser-mcp.md
```
Preconditions: `scripts/invoice-parser/venv` and `/var/www/html/uv-python` absent; `~/Downloads/Order_4804899.pdf` present (123998 bytes).

## Steps

### 1. Install uv — done
Changed: `~/.local/bin/uv`, `~/.local/bin/uvx` (installer run with `UV_NO_MODIFY_PATH=1`, no rc files edited)
Check output:
```
downloading uv 0.13.1 x86_64-unknown-linux-gnu
installing to /home/jon/.local/bin
everything's installed!
$ uv --version
uv 0.13.1 (x86_64-unknown-linux-gnu)
```

### 2. Install a managed CPython 3.12 where www-data can read it — done
Changed: `/var/www/html/uv-python/cpython-3.12.15-linux-x86_64-gnu/` (plus a uv-made minor-version
symlink `/var/www/html/uv-python/cpython-3.12-linux-x86_64-gnu -> …3.12.15…`).
`PY=/var/www/html/uv-python/cpython-3.12.15-linux-x86_64-gnu/bin/python3.12`
Check output:
```
Installed Python 3.12.15 in 12.13s
$ $PY --version
Python 3.12.15
$ $PY -m ensurepip --version
pip 25.0.1
$ namei -m "$PY"
 drwxr-xr-x /
 drwxr-xr-x var
 drwxr-xr-x www
 drwxrwsr-x html
 drwxrwsr-x uv-python
 drwxrwsr-x cpython-3.12.15-linux-x86_64-gnu
 drwxrwsr-x bin
 -rwxrwxr-x python3.12
$ find /var/www/html/uv-python ! -perm -o+r | wc -l
0
```
`python3.12` is a real file (not a symlink), so `namei` on it is the whole path. Nothing in the tree is unreadable by other.

### 3. Create the venv the way `setup.sh` does — done (one deviation)
Changed: `scripts/invoice-parser/venv/` (gitignored)
Commands: `$PY -m venv venv`, `venv/bin/pip install --upgrade pip` (→ pip 26.2.1), `venv/bin/pip install -r requirements.txt`.
All pins installed from wheels with no build failures (odfpy is sdist-only and built fine).
Check output:
```
$ venv/bin/python --version
Python 3.12.15
$ readlink -f venv/bin/python
/var/www/html/uv-python/cpython-3.12.15-linux-x86_64-gnu/bin/python3.12
$ venv/bin/pip freeze | grep -iE '^(numpy|pandas|pillow|pdfplumber|pypdfium2|pytesseract|pdf2image|xlrd|openpyxl|python-docx|odfpy)='
# first run, straight from requirements.txt:
pypdfium2==5.14.0        <- not production's 4.30.0, see Deviations
# after `venv/bin/pip install 'pypdfium2==4.30.0'`:
numpy==2.2.4
odfpy==1.4.1
openpyxl==3.1.5
pandas==2.2.3
pdf2image==1.17.0
pdfplumber==0.11.5
pillow==11.1.0
pypdfium2==4.30.0
pytesseract==0.3.13
python-docx==1.2.0
xlrd==2.0.1
$ venv/bin/pip check
No broken requirements found.
$ git status --short | grep -E 'venv|uv-python|invoice-parser'
(no output, exit 1)
```

### 4. Run the parser directly on the owner's PDF — done
Check output (stdout to scratchpad file, stderr captured separately):
```
exit=0
stderr: 0 bytes   (no ModuleNotFoundError, no apt warning)
{'success': True, 'confidence': 99.3, 'data': <dict len=5>, 'errors': <list len=0>, 'warnings': <list len=2>, 'metadata': <dict len=5>}
data     {'supplier': 'Udea', 'items': <list len=147>, 'totals': <dict len=14>, 'barrels': <dict len=3>, 'costs': <dict len=2>}
metadata {'filename': 'Order_4804899.pdf', 'parsing_method': 'pdfplumber', 'supplier_detected': 'Udea', 'processing_time': 1.245385, 'order_number': '4804899'}
totals   line_count 147, products 2845.15, barrels 29.06, costs 364.06, grand 3238.27, totals_match True, discrepancy 0.0
warnings ['Price mismatch for 5004102: 1×0.49×401.0=196.49, actual=19.60',
          'Deposit code 313: product lines total 24 units, barrels section says 36']
```
The two warnings are parser content warnings about this order, not environment problems (see Notes).

### 5. Parser test suite and the Laravel-side checks — done
Check output:
```
$ scripts/invoice-parser/venv/bin/python -m pytest scripts/invoice-parser/tests -q
208 passed in 0.68s
$ php artisan invoice:test-parser
| Python Exists | ✓      | Python 3.14.4      |
| Parser Script | ✓      | Found              |
| Virtual Env   | ✓      | Found              |
| Tesseract OCR | ✓      | /usr/bin/tesseract |
$ php artisan tinker --execute='dump(app(App\Services\DeliveryParsingService::class)->checkConfiguration());'
  "python_exists" => true
  "parser_script_exists" => true
  "venv_exists" => true
  "errors" => []
  "python_version" => "Python 3.14.4"
  "all_checks_passed" => true
```
The `Python 3.14.4` shown is the configured fallback `/usr/bin/python3`, not the interpreter that runs the parser (see Notes).

### 6. Prove it through Apache (the www-data path) — done (DevTools fallback)
The Playwright MCP tools are not in this session, so the plan's fallback was used: a one-off
script in the session scratchpad
(`/tmp/claude-1000/-var-www-html-osmanagercl/3b4d166f-6c91-46c4-a0e9-51732cfb9831/scratchpad/upload.mjs`,
not added to the repo) that imports `docs/deposit/scripts/browser/lib.mjs` (session minted for
user 1), opens `/deliveries/create`, sets `#pdf_file` with DevTools `DOM.setFileInputFiles` to
`/home/jon/Downloads/Order_4804899.pdf`, fires `change` (the page's own supplier auto-detect
then runs), sets supplier 5, and clicks `#submit-pdf-btn`.
Check output:
```
url http://osmanager.local/deliveries/create
detection  Detected: Udea                 <- detect-supplier endpoint, also runs the parser under www-data
supplier before 5
file Order_4804899.pdf 123998
landed http://osmanager.local/deliveries/165
flash Delivery imported successfully from PDF. 147 items loaded. Total value: 3,238.27 (2 warnings - check items)
body has "Failed to import PDF": false
h1/h2 Delivery DEL-20261019-144501
console errors/warnings: two pre-existing Alpine "x-collapse without the Collapse plugin" warnings on the show page (not this plan)

$ tail of storage/logs/laravel.log since the run, grep "Executing delivery parser|execution failed|ModuleNotFound|ERROR":
[2026-10-19 14:44:53] local.INFO: Executing delivery parser command {"command":"/var/www/html/osmanagercl/scripts/invoice-parser/venv/bin/python …delivery_parser_laravel.py --file …/storage/app/private/temp/kqCb….pdf --output json", …
[2026-10-19 14:44:53] local.INFO: Executing delivery parser command {"command":"…/venv/bin/python … --output json", …
[2026-10-19 14:44:59] local.INFO: Executing delivery parser command {"command":"…/venv/bin/python … --output json --supplier udea", …
(no "Delivery parser execution failed", no ModuleNotFoundError, no ERROR lines)

$ tinker: Delivery 165
  id 165, delivery_number DEL-20261019-144501, supplier_id 5, status draft, total_expected 2845.15, items()->count() 147
```
Screenshot: `/tmp/claude-1000/-var-www-html-osmanagercl/3b4d166f-6c91-46c4-a0e9-51732cfb9831/scratchpad/delivery-upload.png`
(show page with the Udea badge, the green import flash, Import Summary €2,845.15 / €29.06 / €364.06 /
€3,238.27, 147 items, Draft).

**Dev data:** delivery 165 (`DEL-20261019-144501`, Draft, Udea) was **left** in the local
`osmanager` database. It was not synced to legacy and nothing was scanned. Delete it from its show
page if it is not wanted.

### 7. Record the dev-machine recipe — done
Changed: `docs/development/fresh-install-guide.md` (section 11, one paragraph after the manual recipe).
The paragraph follows the plan's wording, plus the uv install one-liner and the `pypdfium2==4.30.0`
line (see Deviations).
Check output:
```
$ git diff --stat
 docs/deliveries/README.md               |  5 +--     <- pre-existing (` M` in baseline)
 docs/deposit/scripts/browser/README.md  | 44 +++++   <- pre-existing (`AM` in baseline)
 docs/deposit/scripts/browser/mint.php   | 62 +++++   <- pre-existing (`AM` in baseline)
 docs/development/fresh-install-guide.md | 10 ++++++  <- this plan
```
The only tracked file this plan changed is `fresh-install-guide.md`. The other three entries were already in the baseline.

## Deviations

1. **pypdfium2 pinned to 4.30.0 inside the venv.** `requirements.txt` does not pin pypdfium2;
   pdfplumber 0.11.5 asks for `pypdfium2>=4.18.0`, so `pip install -r requirements.txt` resolved
   **5.14.0**. That is a major version, not the patch difference the plan allowed. To match
   production (4.30.0) without touching `requirements.txt`, I ran
   `venv/bin/pip install 'pypdfium2==4.30.0'` in the venv. `pip check` is clean, and steps 4–6 all
   ran on 4.30.0. This is environment-only, the same kind of change as the rest of the plan.
2. **Fresh-install note** is one paragraph, as asked. It has two extra sentences beyond the
   plan's draft: how to install uv, and the pypdfium2 pin from deviation 1.
3. **Step 6** used the DevTools fallback (a scratchpad script, not added to the repo) because the
   Playwright MCP tools were not in the session. The fallback could set a file input, so the
   upload was proven through Apache and the step is done, not BLOCKED.

## Verification
```
1. $ scripts/invoice-parser/venv/bin/python --version
   Python 3.12.15
2. $ venv/bin/python -c "import pytesseract, pdfplumber, pdf2image, pandas, xlrd, docx, odf; print('ok')"
   ok
3. $ … delivery_parser_laravel.py --file ~/Downloads/Order_4804899.pdf --output json > /dev/null; echo $?
   0
4. $ venv/bin/python -m pytest scripts/invoice-parser/tests -q
   208 passed in 0.49s
5. $ php artisan invoice:test-parser
   | Python Exists | ✓ | Python 3.14.4 |  | Parser Script | ✓ | Found |
   | Virtual Env   | ✓ | Found         |  | Tesseract OCR | ✓ | /usr/bin/tesseract |
6. Browser upload through Apache: succeeded, delivery 165 with 147 Udea items (step 6).
7. $ git status --short   -> new compared with the baseline: only
    M docs/development/fresh-install-guide.md
   ?? docs/deliveries/implemented-dev-parser-venv.md
   ?? docs/deliveries/plan-dev-parser-venv.md
   $ git ls-files | grep -cE '(^|/)venv/|uv-python'
   0
```

## Files changed
`git status --short` at the end differs from the baseline only by:
```
 M docs/development/fresh-install-guide.md          <- this plan
?? docs/deliveries/implemented-dev-parser-venv.md   <- this report
```
(`docs/deliveries/plan-dev-parser-venv.md` was already untracked in the baseline. All other entries are pre-existing.)

Outside git: `~/.local/bin/uv`, `~/.local/bin/uvx`;
`/var/www/html/uv-python/` (CPython 3.12.15 and uv's `cpython-3.12-linux-x86_64-gnu` symlink);
`scripts/invoice-parser/venv/` (gitignored). Dev DB: delivery 165 (left in place).

## Notes for Planner
- **`invoice:test-parser` and `checkConfiguration()` report the wrong Python.** Both show
  `Python 3.14.4`, the configured fallback `/usr/bin/python3`, even though every parser run uses
  `venv/bin/python` (3.12.15). With no venv, the checks would still pass on "Python Exists", which
  is how the original failure went unnoticed. A follow-up could make them report the interpreter
  that will actually run, and check that the venv imports `pytesseract`. Not touched: no app code
  changes in this plan.
- **pypdfium2 is unpinned in `requirements.txt`.** A fresh `setup.sh` on production would now pull
  5.x, not the 4.30.0 it runs today. Consider pinning `pypdfium2==4.30.0`. Not touched: the plan
  forbids editing `requirements.txt`.
- **Parser warnings on this order** (content, not environment): `Price mismatch for 5004102:
  1×0.49×401.0=196.49, actual=19.60` and `Deposit code 313: product lines total 24 units, barrels
  section says 36`. The first looks like a weight/unit line the price check misreads. Worth a look
  in a deliveries cycle if the owner sees the same warnings on production.
- **Pre-existing console warnings** on the delivery show page: Alpine `x-collapse` is used without
  the Collapse plugin (two elements). Harmless, but those sections may not animate or collapse
  as intended.
