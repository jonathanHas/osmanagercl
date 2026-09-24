# Shop mode design (reference copy)

Source: Claude Design project **"Shop mode system complete"**, id `9402165e-9955-461d-8ff4-3e934897b600`
(https://claude.ai/design/p/9402165e-9955-461d-8ff4-3e934897b600). Copied 2026-09-23 by the Planner
session so the Implementer can build from files. This folder is documentation, not application code.

## What is here

| File | What it is |
|---|---|
| `shop.css` | The design, verbatim. Token block first (`/* === SHOP TOKENS START === */` … `END`), then the component layer. Everything is scoped under the root class `.shop`. Copy to `resources/css/shop.css` unchanged; the app appends its own styles below an `APP ADDITIONS` marker at the end of that file, so the design block stays byte-identical and `head -c $(stat -c %s docs/design/shop-mode/shop.css) resources/css/shop.css \| cmp - docs/design/shop-mode/shop.css` compares it. |
| `shop-icons.svg` | Lucide sprite (stroke 2.75). The screens inline the same paths; a Blade `<x-shop.icon name="scan"/>` rendering `<svg class="shop-ico"><use href="…/shop-icons.svg#scan"/></svg>` is the natural port. Provenance metadata stripped; symbols unchanged. Ids: back, scan, camera, keyboard, plus, minus, printer, tag, truck, coffee, requests, gift, carrot, package, search, lock, users, logout, login, office, check, x, alert, chevron-right, chevron-down, backspace, trash, sprout, list-checks, history, pencil, bookmark, inbox, sort, barcode, leaf. |
| `screen-NN-*.html` | The 17 screens in scope, as plain static pages that open in a browser from this folder. Markup inside `<div class="shop">` is identical to the design; only the Claude Design viewer wrapper was replaced by a normal `<head>` and the cross-links renamed to these filenames. Screen 08 (Coffee KDS) is deliberately omitted: the KDS keeps its existing page. |

Screens: 01 Home · 02 Stock scan · 03 Price check · 04 Deliveries · 05 Delivery scan · 06 Delivery summary ·
07 Print labels · 09 Requests board (guest) · 10 Requests staff · 11 Vouchers · 12 F&V availability ·
13 F&V waste · 14 F&V harvest · 15 F&V labels · 16 Switch user · 17 PIN · 18 Locked.

## Handoff rules (from the design's handoff page)

Setup
- Shop layout: `<body><div class="shop">…`. Nothing is styled outside `.shop`, so the manager interface is untouched. Set the layout's body background to `#f5ead8` to avoid a white overscroll edge.
- Figtree must be loaded with weights 500, 600, 700 and 800 (the admin layout only loads 400–600).
- Add `is-touch` to the root when `matchMedia('(pointer: coarse)').matches`. It reveals the camera and keyboard toggles (`.shop-touch-only`); the till PC without it gets a plain always-focused input for the USB scanner.
- Breakpoints are mobile-first: 768 (tablet portrait), 1024 (landscape, two-column `.shop-split`), 1280 (desktop).

Scan input behaviour
- On touch devices render `inputmode="none"` so focus does not open the on-screen keyboard. The keyboard toggle switches to `inputmode="text"` (or `numeric`) and sets `aria-pressed="true"`.
- Keep focus: re-focus the scan input on blur unless another field took focus, and after every result. USB scanners send Enter; treat it as submit.
- States: `:focus-within` is automatic (`.is-focused` mirrors it for static mocks); add `.is-camera` while the camera is open (put the `<video>` inside `.shop-scan__camera`); add `.is-error` and fill `.shop-scan__msg` for unknown codes.

Other state hooks
- User menu is a native `<details>`; no JS needed. Close it on outside tap if you like.
- Sub-tabs use `aria-current="page"`. Segmented control, switch and choice cards style from native `:checked` via `:has()`. The switch writes its own On/Off word.
- Progress widths are an inline `style="width:43%"` on `.shop-progress__bar`; keep `role="progressbar"` and aria values on the track.
- Tiles the user may not use are omitted server-side; `aria-disabled` exists only as a fallback.
- Back always links to Home; the one exception is PIN → Switch user. Idle timeout → Locked → Switch user.

Tokens → components
- Page, top bar, action bar: `--shop-bg`, `--shop-line`, `--shop-topbar-h`, `--shop-page-max`, `--shop-space-4/5`
- Tile + badge: `--shop-surface`, `--shop-radius-lg`, `--shop-shadow-sm`, `--shop-accent-tint` / `-ink` (icon), `--shop-accent-strong` (badge)
- Buttons: `--shop-accent-strong` → `-press` (primary), `--shop-line-strong` (secondary), `--shop-bad` (danger), `--shop-tap` / `--shop-tap-lg`, `--shop-radius-pill`
- Scan input: `--shop-line-strong` (idle), `--shop-accent` + `--shop-ring` (focused), `--shop-bad` / `-bad-soft` (error), `--shop-ok` (listening dot), `--shop-tap-lg`
- Status pill, tickets, totals, toast: `--shop-{ok|warn|bad|muted}` (dot, border), `-soft` (fill), `-ink` (text)
- Number pad, PIN pad, stepper: `--shop-key`, `--shop-pin-key`, `--shop-surface` / `-surface-2`, `--shop-accent-soft` (pressed), `--shop-fs-2xl`
- Big numerals: `--shop-fs-num`, `--shop-fw-heavy`
- Progress: `--shop-surface-2` (track), `--shop-sage` (running), `--shop-ok` (done), `--shop-warn` (issues)
- Chip, avatar, menu: `--shop-sage-soft` / `-ink` (avatar), `--shop-radius-lg`, `--shop-shadow-lg`

To retheme, change only these
- Brand colour: the six `--shop-accent*` tokens. Keep `--shop-accent-strong` at ≥4.5:1 against `--shop-on-accent` (white); `-soft` and `-tint` are its 200 and 100 steps.
- Ground: `--shop-bg`, `--shop-surface`, `--shop-surface-2`, `--shop-line`, `--shop-line-strong`. Keep it light; the shop is brightly lit.
- Second voice: `--shop-sage*` (avatars, progress, Fruit & veg).
- Type: `--shop-font`. Shape: `--shop-radius-*`. Density: `--shop-tap` / `--shop-key`, never below 56px.
- Leave the status tokens alone unless the KDS changes too; staff read green / amber / red the same way on both.

Next to the existing KDS
- The Shop palette shares the KDS's warm beige ground, orange accent and green/amber/red order states, so the two sit side by side. The KDS is not being moved onto these tokens.

Component API in the app
- `<x-shop.scan-input />` — props `placeholder` (default "Scan or type a barcode"), `hint` (default "Ready — scanner listening"), `camera` (default `true`; `false` drops the camera and keyboard buttons).
- It emits a `scan` event on its own root with `detail = { code }`; the screen listens with `@scan="..."` on the element wrapping it.
- It listens on `window` for `shop-scan-done` (clears and re-focuses), `shop-scan-error` (`detail` = the message to show) and `shop-scan-saved` (reopens the camera, which stops itself on a detection).
- Page behaviour lives in `resources/js/shop/<screen>.js`, registered as `Alpine.data('shop<Screen>', ...)` inside the `alpine:init` listener in `resources/js/shop.js`. Screens under `resources/views/shop/` carry no `<script>`.
- Those modules take every URL from a `data-*` attribute on the page root (`this.$root.dataset.*`), never from Blade, so no route helper appears in JavaScript.
- `.shop-thumb` / `.shop-thumb--lg` are app additions for product photos (48 px in rows, 128 px on a detail card); both hide on a load error and the row falls back to the `shop-row__lead` circle.
- The card image sits in a `.shop-thumb-btn`; `.is-open` grows it to the card width (max 60 vh), toggled by tap — same gesture with a finger or a mouse. No overlay, scrim or teleport by design: the design system has no lightbox pattern and markup outside `.shop` gets no shop styles.
- `.shop-peek` is the hover preview for row photos: one shared panel per screen, `position: fixed` beside the thumbnail, non-interactive (`pointer-events: none`), and shown only under `@media (hover: hover) and (pointer: fine)` so touch devices never get it. Because it is fixed rather than teleported, it depends on no ancestor of `.shop-page` having a `transform` — adding one would trap it in a new containing block.
