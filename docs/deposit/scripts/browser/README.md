# Browser checks for `/deposits` (dev only)

Headless Google Chrome driven over the DevTools protocol, logged in with a
session minted for a user, so an Implementer session can click through a page
and watch the console without a password or a desktop browser. Added in
deposit cycle 4 (Revision 4); used for the Revision 3 and 4 checks.

**Dev machine only. Never run these on production.** `boot.php` refuses to
run unless `APP_ENV` is `local`. `mint.php` writes a real login session to the
session store, and `flow.mjs` / `pct.mjs` change dev data (below).

Needs: Node 22 or later (built-in `WebSocket`; this machine has Node 24),
Google Chrome at `/usr/bin/google-chrome` (override with `CHROME=/path`), the
app served at `APP_URL` (here `http://osmanager.local`), and built assets
(`npm run build` after adding Tailwind classes or changing
`resources/css/modernist.css`). Run everything from the repo root.

## Render a page and print console errors

```bash
node docs/deposit/scripts/browser/cdp.mjs /deposits
```

Prints the page title, whether Alpine started, the number of price rows, and
ends with `console errors/warnings: []` when the console is clean.

## The scripts

| File | What it does | Writes |
|---|---|---|
| `boot.php` | Boots Laravel for the scripts; exits unless `APP_ENV=local` | nothing |
| `mint.php [user_id]` | Logs a user in (default 1) and prints the session cookie name, its encrypted value and `APP_URL` | a session row |
| `mint.php [user_id] --playwright [path]` | The same login written as a Playwright storage-state file (default `~/.cache/osmanagercl/playwright-state.json`, mode 0600) for the Playwright MCP; prints the path and the cookie's expiry | a session row, the state file |
| `lib.mjs` | Shared plumbing: starts Chrome with a throwaway profile in the system temp dir, sets the cookie, opens pages, collects console errors and exceptions | nothing |
| `cdp.mjs [/path] ["product name"]` | Renders a page; with a product name, tries Keep, −deposit and a typed 2.49 on that `/deposits` row and records the New markup text, the hidden form values and the `confirm()` texts. `confirm()` answers no, so nothing is submitted | nothing |
| `flow.mjs "product name"` | Confirms that row with −deposit (dialog accepted) and then Undoes it, printing both flashes | dev till price down and back, two `label_logs` rows, the row confirmed then suggested again |
| `pct.mjs` | Renders the brand and flat views, then clicks the delivery charge + and − | `app_settings` `deposits.delivery_pct` (ends at the value it started at) |
| `clicks.mjs [row]` | **Real pointer clicks** (DevTools input events, not `element.click()`) on one `/deposits` row's −deposit / Keep / typed price, printing the state and whether the window scrolled. Use this for any control that takes focus: a scripted `.click()` does not move focus and missed the Revision 4 scroll bug | nothing |
| `measure.mjs [/path] [shot.png]` | Design-system checks (Archivo, accent colour, square corners, the sidebar font untouched), every row's height, clipped one-line texts; optional screenshot from the page bar to the end of the first group | nothing |

Example, the Revision 4 checks:

```bash
node docs/deposit/scripts/browser/measure.mjs /deposits /tmp/deposits.png
node docs/deposit/scripts/browser/flow.mjs "Your Organic Nature Lime juice 200ml"
```

The window is 1600×1000 by default (`open({ width, height })` in `lib.mjs`).

## From a Claude Code session: the Playwright MCP

`.mcp.json` at the repo root registers a project MCP server, `playwright`
(`@playwright/mcp` through `npx`): headless Google Chrome
(`/usr/bin/google-chrome`), an isolated context per browser start, viewport
1600×1000, console messages from warning level up, output in
`/tmp/claude-playwright`, and the login read from
`/home/jon/.cache/osmanagercl/playwright-state.json`. A Claude Code session
started in this repo asks once to approve the server; it then has the
`mcp__playwright__browser_*` tools (`browser_navigate`, `browser_snapshot`,
`browser_click`, `browser_type`, `browser_evaluate`,
`browser_take_screenshot`, `browser_console_messages`,
`browser_handle_dialog`, `browser_close`, …). A session that was already
running when `.mcp.json` changed does not see the server until it is
restarted. The first start fetches the package, so it needs the network.

- **Login.** When a tool lands on `/login`, the session cookie has expired
  (`SESSION_LIFETIME`, 120 minutes). Mint a new one, then call
  `browser_close` so the next navigation opens a fresh context that reads it:

  ```bash
  php docs/deposit/scripts/browser/mint.php --playwright
  ```

- **Screenshots.** `browser_take_screenshot` with a relative `filename`
  writes into the repo (the server's working directory). The server only
  writes inside its allowed roots, `/tmp/claude-playwright` and the repo, and
  refuses anything else ("outside allowed roots"), so pass an absolute path
  under **`/tmp/claude-playwright/`** and copy the file to the session
  scratchpad if it is needed there.
- **Segmented options** (Keep / −€0.25, Status, Group by): the hidden radio
  has no ref in `browser_snapshot`; its label does (the
  `generic [ref=…] [cursor=pointer]` line just above `radio "−€0.25"`).
  Click that ref, as a person clicks the label. `browser_click` takes the ref
  as `target`.
- **Dialogs.** A `confirm()` puts the tools in a modal state; answer it with
  `browser_handle_dialog` (`accept: true` or `false`). Accepting a Confirm or
  Undo on `/deposits` writes the dev till, like `flow.mjs`.
- **Clicks** are real pointer events, so focus and scroll bugs like the
  Revision 4 one show up.
- The DevTools scripts above stay for measured, repeatable checks (row
  heights, clipped text, design-system values, the confirm-then-Undo flow).
