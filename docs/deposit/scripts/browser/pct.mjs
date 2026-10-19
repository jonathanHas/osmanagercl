// The /deposits views and the delivery-charge stepper as user 1: render the
// brand and flat views, then click + and − (the setting ends where it was).
// WRITES the app_settings row deposits.delivery_pct.
//
// Usage: node docs/deposit/scripts/browser/pct.mjs
import { open } from './lib.mjs';

const b = await open();
for (const path of ['/deposits?group=brand&sort=markup', '/deposits?group=none&sort=sales']) {
    await b.go(path);
    console.log(path, 'groups:', await b.ev(`[...document.querySelectorAll('.dr-group-title')].slice(0, 3).map((e) => e.innerText).join(', ')`),
        'rows:', await b.ev(`document.querySelectorAll('[x-data^="depositRow"]').length`));
}
const pct = `document.querySelector('input[name="delivery_pct"][type="number"]').value`;
await b.submit(`document.querySelector('button[title="One point more"]')`);
console.log('+ :', await b.flash(), '· input now', await b.ev(pct));
await b.submit(`document.querySelector('button[title="One point less"]')`);
console.log('− :', await b.flash(), '· input now', await b.ev(pct));
console.log('console errors/warnings:', JSON.stringify(b.errors));
b.close();
