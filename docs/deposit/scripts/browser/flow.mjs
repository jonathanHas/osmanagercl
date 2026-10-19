// Real submit on /deposits as user 1: confirm one row with −deposit (the
// confirm() dialog accepted), then Undo it. WRITES: the dev till price goes
// down and back up, the row is confirmed then suggested again, and two
// label-log rows are added.
//
// Usage: node docs/deposit/scripts/browser/flow.mjs "product name"
import { open } from './lib.mjs';

const [rowName] = process.argv.slice(2);
if (!rowName) { console.error('Usage: flow.mjs "product name"'); process.exit(2); }

const b = await open();
await b.go('/deposits');
const row = `[...document.querySelectorAll('[x-data^="depositRow"]')].find((r) => r.textContent.includes(${JSON.stringify(rowName)}))`;
if (!(await b.ev(`!!${row}`))) { console.error('row not found'); b.close(); process.exit(1); }

await b.ev(`window.confirm = () => true; ${row}.querySelector('input[value="lower"]').click(); 1`);
await b.submit(`${row}.querySelector('button[title="Confirm"]')`);
console.log('after confirm:', await b.flash());
console.log('row decided:', await b.ev(`${row}.classList.contains('is-decided')`), 'opacity', await b.ev(`getComputedStyle(${row}).opacity`));

await b.ev(`window.confirm = (t) => { sessionStorage.setItem('asked', t); return true; }; 1`);
await b.submit(`${row}.querySelector('input[value="undo"]').form.querySelector('button')`);
console.log('undo asked:', await b.ev(`sessionStorage.getItem('asked')`));
console.log('after undo:', await b.flash());
console.log('row now:', await b.ev(`${row}.innerText.replace(/\\s+/g, ' ').slice(0, 160)`));
console.log('console errors/warnings:', JSON.stringify(b.errors));
b.close();
