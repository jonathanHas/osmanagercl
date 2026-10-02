# Staff Procedures (SOPs) from BookStack

Staff get a **"How to do this"** button on the screen they are using. It opens
the written procedure (SOP) for that screen inside our own site: in Shop mode on
the tablets and phones, in the office layout on the PCs. Managers also get a
**Procedures (BookStack)** link in the office sidebar.

Added 2026-09-30 (BookStack track, cycle 1).

## The split: BookStack writes, the app reads

- Procedures are written and edited in **BookStack** by the owner and the
  managers. The app has no editor of its own.
- The app reads BookStack through its **API**, read-only (`GET` requests only),
  and shows the page inside our layouts. It never creates, edits or tags
  BookStack content.
- Why not just link to BookStack: PIN users have no BookStack account (public
  access is off), the idle lock only runs on our Shop pages, and BookStack is
  plain HTTP on an internal name (`bookstack.internal`) that the tablets and
  phones may not resolve. Images go through our own proxy for the same reason.

## Linking a procedure to a screen

1. Open the screen in the app as a manager. The help button shows on every
   screen for managers and admins, even ones with no procedure yet.
2. Press it. The empty page says: add a tag named **`screen`** with the value
   shown (the screen's route name, for example `shop.deliveries.scan`).
3. In BookStack, open the procedure page, add that tag, save.
4. Back in the app, press **Refresh** (or wait: the screen index is cached for
   **5 minutes**, each page's content for **10 minutes**).

A page may carry several `screen` tags (one per screen). Several pages tagged
for one screen are shown as a list, sorted by title. Employees see the button
only on screens that have a procedure.

## Routes

All inside the `auth` group, named `help.*` (on the PIN allow-list in
`config/shop.php`):

| Route | Name | What |
|---|---|---|
| `GET /help/{screen}` | `help.show` | The procedure for a screen: the page itself (one), a list (several), or the empty state |
| `GET /help/page/{id}` | `help.page` | One tagged page. Any id not in the screen index is 404 |
| `GET /help/image/uploads/images/…` | `help.image` | Image proxy for BookStack uploads |
| `POST /help/refresh` | `help.refresh` | Forget the cached index and pages. `role:manager,admin` |

Every reader page takes an optional `back` query parameter (a local path only;
anything else falls back to the mode's home). The view is `shop.help` in Shop
mode and `help.show` (admin layout) in office mode, chosen by `UiMode`.

## Code

- `app/Services/BookStack/BookStackClient.php` — the only class that talks HTTP
  to BookStack. Search (`[screen] {type:page}`, 100 a page, at most 10 pages),
  page, image. If search hits carry no `tags`, each page is read for its tags.
- `app/Services/BookStack/SopHtml.php` — sanitises the page HTML
  (`symfony/html-sanitizer`, safe elements, `class` kept for BookStack callouts,
  links forced to `target="_blank" rel="noopener noreferrer"`, 1 MB input limit)
  and rewrites URLs: `/uploads/images/…` on BookStack's host → our image proxy;
  other root-relative links → BookStack's own URL.
- `app/Services/BookStack/ScreenHelp.php` — the screen index and its caches
  (`bookstack.screen-index`, `bookstack.screen-index.last-good`,
  `bookstack.page.{id}`), and `current()` for the buttons.
- `app/Http/Controllers/HelpController.php`, `resources/views/shop/help.blade.php`,
  `resources/views/help/show.blade.php`.
- Buttons: `resources/views/components/shop/help-button.blade.php` (Shop topbar),
  `resources/views/components/help-button.blade.php` (office sidebar, below the nav).
- Styles: `.shop-sop` in `resources/css/shop.css` (APP ADDITIONS), `.sop-body`
  in `resources/css/app.css`.
- Tests: `tests/Feature/Help/HelpTest.php` (all BookStack responses faked).

## Configuration (`.env`)

```
BOOKSTACK_URL=http://bookstack.internal   # blank = feature off: no button, no HTTP
BOOKSTACK_TOKEN_ID=…
BOOKSTACK_TOKEN_SECRET=…
BOOKSTACK_TIMEOUT=3                       # seconds per request (connect timeout is 2)
BOOKSTACK_SCREEN_TAG=screen               # tag name that links a page to a screen
```

The feature is on only when the URL and both token parts are set. `phpunit.xml`
blanks `BOOKSTACK_URL` so the test suite can never call a real BookStack.

## When BookStack is down, slow or unconfigured

- No page of the app breaks. Every call has a timeout and is caught.
- The index falls back to the last good copy (kept forever in the cache), or to
  an empty index, so buttons keep working for pages already known.
- A page that cannot be fetched shows "This procedure could not be loaded just
  now. Try again in a minute." A failure is never cached.
- Failures are logged as a warning with the message and exception class only.

## Security rules

- **Tagged pages only.** The reader serves a page only if its id is in the
  screen index. BookStack may hold pages that are not for shop-floor staff, and
  the API token may be able to see them.
- **Sanitised HTML.** The page HTML is sanitised before it is cached or
  rendered; it is shown inside our origin where the staff session lives.
  `style` attributes, iframes and `id`s are dropped (so coloured text, fixed
  image widths, embedded video and in-page anchors do not carry over).
- **Image proxy limits.** Only paths under `uploads/images/`, no `..` segments,
  and only `image/png`, `image/jpeg`, `image/gif`, `image/webp`. SVG is refused:
  opened directly it would run script in our origin. Responses carry
  `Cache-Control: private, max-age=604800` and `X-Content-Type-Options: nosniff`.
  The proxy sends no API token.
- **The token never reaches the browser or the log.** It is only ever in the
  `Authorization` header.
- **PIN confinement intact.** `help.*` is on the allow-list; `help.refresh`
  keeps its own `role:manager,admin` gate, and managers have no PIN.

## Owner setup in BookStack

1. Create a **shelf** for shop SOPs, with a **book per area** (deliveries,
   tills, F&V, …).
2. Create a **role** that can view only that shelf and has **"Access system
   API"**, a **user** with that role, and an **API token** for that user. Put
   the token id and secret in the production `.env`, with
   `BOOKSTACK_URL=http://bookstack.internal`.
3. Confirm the **production machine itself resolves `bookstack.internal`**
   (the app calls BookStack from the server, not from the tablets).
4. Give each manager a BookStack **editor** account, and make sure their PCs
   resolve `bookstack.internal` for the sidebar link and "Edit in BookStack".
5. Deploy needs `composer install` (new package `symfony/html-sanitizer`) and
   `npm run build`.

If BookStack stores images as `local_secure`, `/uploads/images/…` needs a
BookStack session and the proxy cannot fetch them: images would show broken.

## Not yet

- A "Procedures" tile on Shop Home, or browsing / searching all SOPs in our site.
- Links between BookStack pages opening in our reader (they open BookStack in a
  new tab).
- Writing to BookStack, single sign-on, syncing users or roles, webhooks.
- Different SOPs for different roles.
- A scheduled refresh job (the caches are enough).
- The help button on the legacy `<x-app-layout>` pages, guest pages and the KDS.
