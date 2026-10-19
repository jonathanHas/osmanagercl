// Shared plumbing for the browser-check scripts: start headless Chrome, log in
// with a session minted by mint.php, open a page, and collect console errors.
// Dev only; see README.md.
import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const CHROME = process.env.CHROME ?? '/usr/bin/google-chrome';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export { sleep };

export async function open({ width = 1600, height = 1000, user = 1 } = {}) {
    const [cookieName, cookieValue, appUrl] = execFileSync('php', [join(here, 'mint.php'), String(user)], { encoding: 'utf8' })
        .trim().split('\n');
    const profile = mkdtempSync(join(tmpdir(), 'osm-browser-check-'));
    const port = 9300 + Math.floor(Math.random() * 600);
    const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`,
        '--no-first-run', `--window-size=${width},${height}`, 'about:blank'], { stdio: 'ignore' });

    let targets = [];
    for (let i = 0; i < 50 && !targets.find((t) => t.type === 'page'); i++) {
        try { targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); } catch { await sleep(200); }
    }
    const ws = new WebSocket(targets.find((t) => t.type === 'page').webSocketDebuggerUrl);
    await new Promise((r) => { ws.onopen = r; });

    let id = 0;
    let loaded = null;
    const pending = {};
    const errors = [];
    ws.onmessage = (m) => {
        const d = JSON.parse(m.data);
        if (d.id && pending[d.id]) { pending[d.id](d); delete pending[d.id]; }
        if (d.method === 'Runtime.consoleAPICalled' && ['error', 'warning'].includes(d.params.type)) {
            errors.push(d.params.type + ': ' + d.params.args.map((a) => a.value ?? a.description).join(' '));
        }
        if (d.method === 'Runtime.exceptionThrown') {
            errors.push('exception: ' + (d.params.exceptionDetails.exception?.description ?? d.params.exceptionDetails.text));
        }
        if (d.method === 'Log.entryAdded' && d.params.entry.level === 'error') errors.push('log: ' + d.params.entry.text);
        if (d.method === 'Page.loadEventFired' && loaded) { const l = loaded; loaded = null; l(); }
    };

    const send = (method, params = {}) => new Promise((r) => { pending[++id] = r; ws.send(JSON.stringify({ id, method, params })); });
    const ev = async (expr) => (await send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true })).result.result?.value;
    // Run fn (which starts a navigation) and wait for the next page load.
    const nav = (fn) => new Promise((r) => { loaded = r; fn(); }).then(() => sleep(700));
    const go = (path) => nav(() => send('Page.navigate', { url: appUrl + path }));
    // Click a button that submits a form, from a script the page runs (so confirm() overrides apply).
    const submit = (selectorExpr) => nav(() => ev(`setTimeout(() => (${selectorExpr}).click(), 0); 1`));
    const flash = () => ev(`[...document.querySelectorAll('.bg-green-100, .bg-red-100, .ds-modernist .tag[style*="block"]')]
        .map((e) => e.innerText.trim()).join(' | ')`);
    const close = () => { ws.close(); chrome.kill(); setTimeout(() => rmSync(profile, { recursive: true, force: true }), 500); };

    for (const domain of ['Page', 'Runtime', 'Log', 'Network']) await send(domain + '.enable');
    await send('Network.setCookie', { name: cookieName, value: cookieValue, url: appUrl, httpOnly: true });

    return { send, ev, nav, go, submit, flash, errors, close, appUrl, width };
}
