// Layout figures for /deposits as user 1: the design-system checks, every
// row's height, one-line texts that are clipped, and an optional screenshot
// of the page from the bar to the end of the first group.
//
// Usage: node docs/deposit/scripts/browser/measure.mjs [/path] [screenshot.png]
import { writeFileSync } from 'node:fs';
import { open, sleep } from './lib.mjs';

const [path = '/deposits', shot] = process.argv.slice(2);
const b = await open();
await b.go(path);

const out = await b.ev(`(async () => {
  await document.fonts.ready;
  const cs = (el, p) => el ? getComputedStyle(el)[p] : null;
  const rows = [...document.querySelectorAll('.dr-row')];
  const h = rows.map((r) => ({ h: Math.round(r.getBoundingClientRect().height), name: r.querySelector('.dr-name')?.innerText ?? '?',
    extra: !!(r.children[2].querySelector('.dr-muted, .dr-amber') || r.children[1].querySelector('.dr-accent')) }));
  const clipped = [];
  rows.forEach((r) => r.querySelectorAll('.dr-nowrap').forEach((l) => { if (l.scrollWidth > l.clientWidth + 1) clipped.push(l.innerText.trim()); }));
  const max = (xs) => xs.length ? Math.max(...xs) : null;
  return {
    price_font: cs(document.querySelector('.dr-price'), 'fontFamily'), archivo_800: document.fonts.check('800 20px Archivo'),
    confirm_all_bg: cs(document.querySelector('.nav .btn-primary'), 'backgroundColor'),
    confirm_all_radius: cs(document.querySelector('.nav .btn-primary'), 'borderRadius'),
    sidebar_font: cs(document.querySelector('nav a'), 'fontFamily'),
    columns: cs(rows[0], 'gridTemplateColumns'), rows: rows.length,
    height_max: max(h.map((x) => x.h)), height_min: rows.length ? Math.min(...h.map((x) => x.h)) : null,
    plain_max: max(h.filter((x) => !x.extra).map((x) => x.h)), with_extra_lines_max: max(h.filter((x) => x.extra).map((x) => x.h)),
    tallest: h.slice().sort((a, b) => b.h - a.h).slice(0, 3), clipped: clipped.slice(0, 10), clipped_count: clipped.length,
  }; })()`);
console.log(JSON.stringify(out, null, 1));

if (shot) {
    const box = await b.ev(`(() => { const g = document.querySelector('.ds-modernist').getBoundingClientRect();
      const next = document.querySelectorAll('.dr-group')[1]; return { x: Math.round(g.left), y: Math.round(g.top + scrollY),
      bottom: Math.round(next ? next.getBoundingClientRect().top + scrollY : document.documentElement.scrollHeight) }; })()`);
    const full = await b.ev('document.documentElement.scrollHeight');
    await b.send('Emulation.setDeviceMetricsOverride', { width: b.width, height: full, deviceScaleFactor: 1, mobile: false });
    await sleep(300);
    const img = await b.send('Page.captureScreenshot', { format: 'png', clip: { x: box.x, y: box.y, width: b.width - box.x, height: box.bottom - box.y, scale: 1 } });
    writeFileSync(shot, Buffer.from(img.result.data, 'base64'));
    console.log('screenshot:', shot);
}
console.log('console errors/warnings:', JSON.stringify(b.errors));
b.close();
