# Side plan — the Implementer tests in a real browser through the Playwright MCP

Status: READY
Revision: 1
Planner: Fable 5.1
Date: 2026-10-17

## Goal

The owner wants the Implementer to test directly in a browser, not only
through the DevTools scripts in `docs/deposit/scripts/browser/`. The Planner
has registered the Playwright MCP server for this project and proved it
end-to-end; this side plan makes the login reproducible and records how to
use it, and its verification is the first run of the tools from an
Implementer session.

## Context (what the Planner set up, 2026-10-17)

- `.mcp.json` at the repo root (project scope, checked in) registers server
  `playwright`: `npx -y @playwright/mcp@latest --executable-path
  /usr/bin/google-chrome --headless --isolated --storage-state
  /home/jon/.cache/osmanagercl/playwright-state.json --viewport-size
  1600x1000 --console-level warning --output-dir /tmp/claude-playwright`.
  A Claude Code session started in this repo asks once to approve the
  project server, then has 25 `mcp__playwright__browser_*` tools:
  `browser_navigate`, `browser_snapshot`, `browser_click`, `browser_type`,
  `browser_fill_form`, `browser_press_key`, `browser_evaluate`,
  `browser_take_screenshot`, `browser_console_messages`,
  `browser_handle_dialog`, `browser_close` and the rest (`tools/list`).
- **Login** comes from the storage-state file: the cookie that
  `mint.php` mints for user 1, written in Playwright's storage-state JSON.
  The Planner wrote the current one by hand (a one-off script); it is
  readable by the owner only (`chmod 600`), outside the repo, and expires
  with the session (`SESSION_LIFETIME` 120 minutes from last use). When the
  tools land on the login page, regenerate it and `browser_close` so the
  next navigation opens a fresh context.
- Proved by the Planner with a stdio client speaking the MCP protocol:
  `browser_navigate` to `/deposits` → `browser_evaluate` returned
  `{title: "OS Manager - Admin", loginForm: false, rows: 57, confirmAll:
  "Confirm all suggested (57)"}`; `browser_take_screenshot` wrote a PNG;
  `browser_console_messages` at warning level: 0.
- Screenshots: `browser_take_screenshot` with a `filename` writes relative
  to the server's cwd, which is the repo root. **Always pass an absolute path
  under the session scratchpad**, or the PNG lands in the repo (the Planner
  removed one).
- `confirm()` dialogs: the MCP reports a modal state; answer with
  `browser_handle_dialog` (`accept: true/false`). Real pointer events are
  what `browser_click` sends, so the Revision 4 scroll bug class is visible
  through these tools.

## Constraints

- Dev only. `mint.php` already refuses outside `APP_ENV=local`.
- No secrets in the repo: the state file stays under `~/.cache/osmanagercl/`.
- Do not change `.mcp.json` except as step 1 says.
- No commit.

## Out of scope

- Headed (visible) Chrome, other users, production, Firefox/WebKit.
- Replacing the DevTools scripts; they stay for the measured checks.

## Steps

### 1. `mint.php --playwright [path]`
Files: `docs/deposit/scripts/browser/mint.php`
What: with `--playwright`, write (instead of printing the three lines) a
Playwright storage-state file:
`{"cookies":[{"name":<cookie>,"value":<encrypted value>,"domain":<APP_URL host>,"path":"/","expires":<now + session lifetime in seconds>,"httpOnly":true,"secure":false,"sameSite":"Lax"}],"origins":[]}`
to `$argv` path or the default `~/.cache/osmanagercl/playwright-state.json`
(create the directory, mode 0600 on the file), and print the path and the
expiry time. Keep the plain three-line output for the `.mjs` scripts. The
user id argument still works (`--playwright` may come before or after it).
Check: `php docs/deposit/scripts/browser/mint.php --playwright` prints the
path; `python3 -c 'import json;print(len(json.load(open("/home/jon/.cache/osmanagercl/playwright-state.json"))["cookies"]))'` → `1`.

### 2. README
Files: `docs/deposit/scripts/browser/README.md`
What: a section "From a Claude Code session: the Playwright MCP" with: what
`.mcp.json` registers; the mint line to run when the tools land on the login
page, then `browser_close`; absolute screenshot paths; dialogs; that the
`.mjs` scripts remain for measured checks. One table row for `--playwright`.
Check: the section reads correctly against `.mcp.json`.

### 3. Prove it from this session
Files: none.
What: using the `mcp__playwright__*` tools (not the scripts):
`browser_navigate` `http://osmanager.local/deposits`; `browser_snapshot`;
click the `−€0.25` option on the "Your Organic Nature Lime juice 200ml"
row; `browser_evaluate` that its New markup text changed and the hidden
`price_action` is `lower`; click `Keep` and check it went back;
`browser_take_screenshot` to an absolute scratchpad path;
`browser_console_messages` at warning level. Do **not** click Confirm.
Paste the tool outputs (trimmed) in `implemented-browser-mcp.md`.
Check: rows 57, the three evaluate results, the screenshot path, 0 console
messages.

## Verification

1. Step 1's two checks.
2. Step 3's outputs.
3. `./vendor/bin/pint --test docs/deposit/scripts/browser/mint.php` → clean.
4. `git status --short`: `mint.php`, `README.md` changed; nothing else new.

## Risks

- `npx -y @playwright/mcp@latest` fetches the package on first start and
  needs the network; afterwards it is cached.
- If the session cookie has expired, every tool lands on `/login`; step 1's
  command fixes it.

## Review

(Planner.)
