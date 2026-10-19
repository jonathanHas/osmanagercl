// Real pointer clicks (CDP Input events, not element.click()) on one
// /deposits row's price choice: −deposit, Keep, −deposit, type 2.49, Keep.
// Prints the row's state after each step, including whether the window
// itself scrolled (it must not: the admin shell scrolls inside <main>).
// Nothing is submitted.
//
// Usage: node docs/deposit/scripts/browser/clicks.mjs [row index, default 1]
import { open, sleep } from './lib.mjs';

const row = Number(process.argv[2] ?? 1);
const b = await open();
await b.go('/deposits');
await b.ev(`(() => { const r = document.querySelectorAll('.dr-row')[${row}]; const m = document.querySelector('main');
  m.scrollTop = r.getBoundingClientRect().top + m.scrollTop - 300; })()`);
await sleep(300);

const state = () => b.ev(`(() => { const r = document.querySelectorAll('.dr-row')[${row}]; const d = Alpine.$data(r);
  return JSON.stringify({ windowScrollY: scrollY, documentHeight: document.documentElement.scrollHeight, viewport: innerHeight,
    choice: d.choice, custom: d.custom, newMarkup: r.children[6].innerText.replace(/\\s+/g, ' '),
    submits: [...r.querySelectorAll('input[name=price_action], input[name=new_price]')].map((x) => x.name + '=' + x.value).join(' ') }); })()`);
const centre = (sel) => b.ev(`(() => { const q = (${sel}).getBoundingClientRect(); return { x: q.left + q.width / 2, y: q.top + q.height / 2 }; })()`);
const click = async (sel) => {
    const p = await centre(sel);
    for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) {
        await b.send('Input.dispatchMouseEvent', { type, x: p.x, y: p.y, button: 'left', clickCount: 1 });
    }
    await sleep(250);
};
const seg = (n) => `document.querySelectorAll('.dr-row')[${row}].querySelectorAll('.seg-opt')[${n}]`;

console.log('start        ', await state());
await click(seg(1)); console.log('click −deposit', await state());
await click(seg(0)); console.log('click Keep    ', await state());
await click(seg(1)); console.log('click −deposit', await state());
await click(`document.querySelectorAll('.dr-row')[${row}].querySelector('input[x-model=custom]')`);
for (const ch of '2.49') await b.send('Input.dispatchKeyEvent', { type: 'char', text: ch });
await sleep(250); console.log('type 2.49    ', await state());
await click(seg(0)); console.log('click Keep    ', await state());
console.log('console errors/warnings:', JSON.stringify(b.errors));
b.close();
