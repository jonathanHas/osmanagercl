// Render a page as user 1 and print console errors. With a row name (on
// /deposits), also exercise that row's price choice: Keep, −deposit, a typed
// 2.49, back to Keep, and capture the confirm() texts. confirm() returns false,
// so nothing is submitted.
//
// Usage: node docs/deposit/scripts/browser/cdp.mjs [/path] ["product name"]
import { open, sleep } from './lib.mjs';

const [path = '/deposits', rowName] = process.argv.slice(2);
const b = await open();
await b.go(path);

const out = await b.ev(`(async () => {
  const out = { url: location.pathname + location.search, title: document.title, alpine: !!window.Alpine,
    rows: document.querySelectorAll('[x-data^="depositRow"]').length };
  const rowName = ${JSON.stringify(rowName ?? null)};
  if (!rowName) return out;
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const asked = []; window.confirm = (t) => { asked.push(t); return false; };
  const row = [...document.querySelectorAll('[x-data^="depositRow"]')].find((r) => r.textContent.includes(rowName));
  if (!row) return { ...out, error: 'row not found' };
  const text = (i) => row.children[i].innerText.replace(/\\s+/g, ' ').trim();
  const hidden = () => [...row.querySelectorAll('input[name="price_action"], input[name="new_price"]')].map((i) => i.name + '=' + i.value);
  out.keep = text(6);
  row.querySelector('input[value="lower"]').click(); await sleep(100);
  out.lower = text(6); out.hidden_lower = hidden();
  row.querySelector('button[title="Confirm"]').click(); await sleep(100);
  const custom = row.querySelector('input[x-model="custom"]');
  custom.value = '2.49'; custom.dispatchEvent(new Event('input', { bubbles: true })); await sleep(100);
  out.custom = text(6); out.custom_tinted = row.children[5].className.includes('dr-custom'); out.hidden_custom = hidden();
  row.querySelector('button[title="Confirm"]').click(); await sleep(100);
  row.querySelector('input[value="keep"]').click(); await sleep(100);
  out.back_to_keep = text(6);
  row.querySelector('button[title="Confirm"]').closest('form').dispatchEvent(new Event('submit', { cancelable: true }));
  out.confirm_texts = asked;
  return out; })()`);

await sleep(100);
console.log(JSON.stringify(out, null, 1));
console.log('console errors/warnings:', JSON.stringify(b.errors));
b.close();
