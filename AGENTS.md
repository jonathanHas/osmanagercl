# Repository Guidelines

## Project Structure & Module Organization
- `app/` contains Laravel domain code; controllers in `Http/Controllers`, business services under `Services`, data access abstractions in `Repositories`.
- `resources/views` stores Blade templates; `resources/js` holds Alpine.js widgets and Vite-managed assets; shared Tailwind utilities live in `resources/css`.
- Routes are split between `routes/web.php` for UI flows and `routes/api.php` for JSON endpoints; update the matching feature docs in `docs/features/` when behavior changes.
- Database migrations, factories, and seeds reside in `database/`; test fixtures and suites live inside `tests/Feature`, `tests/Unit`, with CSV fixtures under `tests/examples`.

## Build, Test, and Development Commands
- `composer install` / `npm install` bootstrap PHP and front-end dependencies.
- `composer run dev` starts the full stack: Laravel server, queue listener, log tailing, and `npm run dev`.
- `npm run build` produces a production asset bundle via Vite; ensure it stays clean before release branches.
- `composer test` clears cached config then runs PHPUnit; pair with `php artisan test --filter Name` for targeted runs.
- `./vendor/bin/pint` enforces the PHP style profile; run before opening a PR.
- `php artisan migrate` and `php artisan db:seed --class=AdminUserSeeder` prepare the SQLite workspace; configure POS sync via `.env` `POS_DB_*` keys.

## Coding Style & Naming Conventions
- Follow PSR-12 with 4-space indentation, typed method signatures, and succinct PHPDoc on public APIs; favor service classes over fat controllers.
- Blade views should remain declarative—push heavier logic into view models or `App\\View\\Components`.
- Alpine.js modules in `resources/js` use camelCase function exports; keep Tailwind utility composition consistent with the design tokens in `tailwind.config.js`.

## Testing Guidelines
- Place HTTP and workflow scenarios in `tests/Feature/*Test.php`; pure logic belongs in `tests/Unit`.
- Name tests descriptively (e.g., `DeliveryControllerTest::authenticated_user_can_view_delivery`) and assert permissions and responses.
- Store reusable artifacts under `tests/examples` and load them via Laravel’s filesystem helpers.
- Target ≥80% coverage on new code paths and document gaps in the PR.

## Commit & Pull Request Guidelines
- Use imperative, present-tense commit subjects with a concise scope (e.g., `Add supplier price comparison caching`); squash noisy fixups before pushing.
- Branches follow `feature/*`, `fix/*`, `docs/*`, or `refactor/*` patterns from `CONTRIBUTING.md`.
- Pull requests must link relevant issues, summarize the change, list test commands executed, and include UI screenshots for view updates.
- Request review from the domain owner and confirm migrations, seeds, or config changes in the description.
