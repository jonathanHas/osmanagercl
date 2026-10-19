# Side plan — the Implementer tests in a real browser through the Playwright MCP — implementation

Status: BLOCKED
Plan revision: 1
Implementer: Opus
Date: 2026-10-17

## Baseline
HEAD: 5775fbef
Pre-existing state: the whole working tree is **staged** (31 entries, all
`A`/`M`/`R` in the index: cycle 4, its archive in
`docs/deposit/archive/2026-10-17-cycle-4-price-review/`, `.mcp.json`,
`docs/deposit/plan-browser-mcp.md`, `docs/deposit/scripts/browser/*`). Staged
before this session started; not by me. Nothing unstaged at the start.
`~/.cache/osmanagercl/playwright-state.json` exists (533 bytes, 0600, written
by the Planner at 16:08).

This session's tool list has **no `mcp__playwright__*` tools** (ToolSearch for
"playwright browser" finds none): it started before `.mcp.json` was added,
and project servers are loaded and approved at session start.

## Steps

### 1. `mint.php --playwright [path]` — done
Changed: `docs/deposit/scripts/browser/mint.php`. Arguments are scanned in
any order: a bare number is the user id; `--playwright` may be followed by a
path (anything that is not a bare number). Writes the storage-state JSON
(`expires` = now + `session.lifetime` × 60), creates the directory (0700) if
needed, `chmod 0600`, prints the path and expiry. Without the flag the three
plain lines are unchanged.
Check output:
```
$ php docs/deposit/scripts/browser/mint.php --playwright
Playwright storage state for user 1: /home/jon/.cache/osmanagercl/playwright-state.json
Cookie expires: 2026-10-17 18:43:43 (SESSION_LIFETIME); mint again after that, then browser_close.
$ python3 -c 'import json;print(len(json.load(open(".../playwright-state.json"))["cookies"]))'
1      (name o_s_manager_session, domain osmanager.local, path /, httpOnly true, secure false, sameSite Lax; origins [])
$ stat -c '%a' ~/.cache/osmanagercl/playwright-state.json
600
mint.php 1 --playwright <path>  and  mint.php --playwright <path> 1  -> both write <path> for user 1
mint.php (plain) -> o_s_manager_session / <value> / http://osmanager.local ; cdp.mjs /deposits -> console errors/warnings: []
```

### 2. README — done
Changed: `docs/deposit/scripts/browser/README.md`: table row for
`mint.php [user_id] --playwright [path]`; section "From a Claude Code
session: the Playwright MCP" (what `.mcp.json` registers, approval once per
session, a running session needs a restart to see it, first start needs the
network; the mint line then `browser_close`; screenshots; dialogs; real
pointer clicks; the DevTools scripts stay for measured checks). Read against
`.mcp.json`: command, Chrome path, `--headless`, `--isolated`,
`--storage-state` path, viewport, console level and output dir all match.
Two things in it differ from the plan, from step 3's evidence (Deviations 1
and 2): the screenshot root, and how to click a segmented option.

### 3. Prove it from this session — **blocked**
The step requires the `mcp__playwright__*` tools in this session, and this
session has none (Baseline). It needs a Claude Code session started in the
repo after `.mcp.json` existed, with the project server approved.

**Supporting evidence, not the step itself.** To check that the state file
from step 1 and the server work, I started the server exactly as
`.mcp.json` does (same command, args and cwd, read from the file) and called
its tools over stdio from a small client (`mcp-stdio.mjs` in the session
scratchpad). The same sequence the step names, through the server's own
`browser_*` tools, with `browser_click` sending real pointer events:
```
tools/list: 25 tools
browser_navigate http://osmanager.local/deposits -> Page URL /deposits, Page Title "OS Manager - Admin"
browser_snapshot -> lime row found; option labels Keep [ref=e1772], −€0.25 [ref=e1773]
browser_evaluate (before):     {"title":"OS Manager - Admin","loginForm":false,"rows":57,"newMarkup":"73% no change €1.14 profit / unit","price_action":"keep","windowScrollY":0}
browser_click target e1773 (−€0.25)
browser_evaluate (after −€0.25): {"rows":57,"newMarkup":"57% −16 pts €0.89 profit / unit","price_action":"lower","windowScrollY":0}
browser_click target e1772 (Keep)
browser_evaluate (after Keep):   {"rows":57,"newMarkup":"73% no change €1.14 profit / unit","price_action":"keep","windowScrollY":0}
browser_take_screenshot -> /tmp/claude-playwright/mcp-stdio-deposits.png (copied to the session scratchpad:
   /tmp/claude-1000/-var-www-html-osmanagercl/642cfe52-65a1-4e22-b3f0-fdac33ba9059/scratchpad/mcp-stdio-deposits.png)
browser_console_messages level warning -> "Total messages: 1 (Errors: 0, Warnings: 0) Returning 0 messages for level "warning""
browser_close
```
Confirm was not clicked; nothing was written to dev. The first attempt
failed in two instructive ways (Deviations 1 and 2):
```
browser_take_screenshot filename=<scratchpad>/…png -> Error: File access denied: … is outside allowed roots. Allowed roots: /tmp/claude-playwright, /var/www/html/osmanagercl
browser_click {ref: …} -> Invalid arguments for tool "browser_click": … expected string, received undefined → at target
```

## Deviations

1. **Screenshots go under `/tmp/claude-playwright/`, not the session
   scratchpad.** The server refuses paths outside its allowed roots (its
   `--output-dir` and the repo). The README says to pass an absolute path
   under `/tmp/claude-playwright/` and copy the file if needed. Adding the
   scratchpad as a root would mean editing `.mcp.json`, which the
   Constraints forbid; and the scratchpad path changes every session anyway.
2. **Clicking Keep / −€0.25 uses the label's ref.** The hidden radios get no
   ref in `browser_snapshot`; the clickable label just above each does.
   `browser_click` in the version `npx` fetched today takes the ref as
   `target` (not `ref`). Both are in the README.

## Verification

1. Step 1's two checks: pass (path printed; 1 cookie; mode 600).
2. Step 3's outputs: **not run from the session tools (blocked)**; the
   server-level run above passes on every figure the step names (rows 57,
   −€0.25 → `lower` with New markup changed, Keep → back, screenshot path,
   0 console messages).
3. `./vendor/bin/pint --test docs/deposit/scripts/browser/mint.php` →
   `PASS ... 1 file`.
4. `git status --short` (entries that are not purely staged):
   ```
   AM docs/deposit/scripts/browser/README.md
   AM docs/deposit/scripts/browser/mint.php
   ?? docs/deposit/implemented-browser-mcp.md      (this report)
   ```
   Everything else is as staged at the baseline. No PNG in the repo.
   Outside the repo: `~/.cache/osmanagercl/playwright-state.json` rewritten
   (fresh cookie, expires 18:43), `/tmp/claude-playwright/mcp-stdio-deposits.png`.

## What is needed to finish

Start a new Claude Code session in `/var/www/html/osmanagercl`, approve the
project MCP server `playwright` when asked, and run step 3 there (the
kickoff prompt for this side plan again is enough). If the cookie has
expired by then (after 18:43 today), the session first runs
`php docs/deposit/scripts/browser/mint.php --playwright`.

## Notes for Planner

1. `@playwright/mcp@latest` is unpinned, and its argument names already
   differ from what the plan expected (`target`). A pinned version in
   `.mcp.json` would keep the README true; your call (I did not edit it).
2. The staged tree: everything was staged before this session; my two edits
   show as `AM`. Nothing committed.
