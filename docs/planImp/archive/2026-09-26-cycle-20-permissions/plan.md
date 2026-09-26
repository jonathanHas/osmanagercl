# Cycle 20 — Grant the customer-requests, voucher and customer-invoice permissions on the live database

Status: ACCEPTED
Revision: 1
Planner: Fable 5.1
Date: 2026-09-26

## Goal

On production the Home tiles for Customer requests and Vouchers do not appear because the permissions behind them do not exist there. Checked read-only on the production database: the `permissions` table lacks four names the seeder defines: `customer-requests.manage`, `vouchers.redeem`, `vouchers.manage` and `customer-invoices.manage`. Both features' docs said "run the roles seeder on deploy", which never happened. This codebase's established fix is a migration that creates permissions and grants them to roles additively (`2025_08_11_232011_add_cash_reconciliation_permissions.php`, `2026_09_23_150000_add_shop_mode_permissions.php`); the deploy runs `migrate --force`, so this ships without anyone remembering a seeder. Grants follow the seeder exactly: employees get `customer-requests.manage` and `vouchers.redeem`; managers get those plus `vouchers.manage` and `customer-invoices.manage`; admins get all four. Nothing is removed from any role.

## Context

- Production check (2026-09-26, read-only via the deploy host): roles admin/manager/employee hold `products.view`, `deliveries.process`, `labels.print`, `stocking.scan`, `fruit_veg.operate` from the earlier migrations; the four names above are absent from the `permissions` table; production also holds `cash_reconciliation.*` and `till_review.export`, which the seeder does not list, so the seeder must never be used as a replacement on the live database. The `shop.vouchers` and `customer-requests.index` routes exist on production.
- Seeder definitions to copy verbatim (`database/seeders/RolesAndPermissionsSeeder.php` lines 227–252): `customer-invoices.manage` "Manage Customer Invoices" module "Customer Invoicing"; `customer-requests.manage` "Manage Customer Requests" module "Customer Requests"; `vouchers.redeem` "Redeem Vouchers" module "Voucher Management"; `vouchers.manage` "Manage Vouchers" module "Voucher Management" (take the `description` lines from the same block).
- Pattern: `2026_09_23_150000_add_shop_mode_permissions.php` (constants for the grant lists, `Permission::firstOrCreate` with `display_name`/`description`/`module`, `Role::where('name', …)->first()` guarded with `if`, `permissions()->syncWithoutDetaching(Permission::whereIn('name', …)->pluck('id'))`, a `down()` that detaches and deletes). Note the cash-reconciliation migration passes `guard_name`, which the `permissions` table does not have on every install; the shop-mode migration does not pass it: **follow the shop-mode one**.
- `HasPermissions`: admins bypass permission checks, so the admin grant is for completeness, as the seeder does.
- Tests: `tests/Feature/Shop/RolePermissionGrantsTest.php` has `MIGRATION` constant + `role()` helper, `test_seeder_grants_the_shop_floor_permissions_to_employees`, `test_seeder_leaves_the_barista_with_kds_only`, `test_migration_grants_the_same_permissions_on_an_existing_database` (creates roles, `require`s the migration file, calls `up()`, asserts grants). Home tile tests: `ShopVouchersTest::home_tile_links_to_the_shop_screen`, `ShopRequestsTest` (employee with `customer-requests.manage`).
- Suite baseline: 15 failed / 636 passed.

## Constraints

- Do not commit, push or deploy. Say in `implemented.md` that this ships with the next deploy's `migrate --force` and needs no seeder run.
- Additive only (`syncWithoutDetaching`); no role loses anything; barista gets nothing.
- Idempotent: running on a database that already has some of the four (dev) changes nothing but fills gaps.

## Out of scope

- Reconciling the seeder with the live database's extra permissions (`cash_reconciliation.*`, `till_review.export`); they came from a migration and are fine.
- Any change to the permission checks in the app.

## Steps

### 1. The migration
Files: `database/migrations/2026_09_26_120000_add_customer_requests_and_voucher_permissions.php (new)`
What: mirror the shop-mode migration. Constants: `PERMISSIONS` (the four, with display names, descriptions and modules from the seeder), `EMPLOYEE_GRANTS = ['customer-requests.manage', 'vouchers.redeem']`, `MANAGER_GRANTS = EMPLOYEE_GRANTS + ['vouchers.manage', 'customer-invoices.manage']`, admin = all four. `up()`: `firstOrCreate` each permission; for each role that exists, `syncWithoutDetaching` its list. `down()`: detach the four from every role, then delete them (as the shop-mode migration's `down()` does). Docblock: why (production never had the seeder run; tiles invisible; 2026-09-26).
Check: `php artisan migrate` on dev runs clean (dev already has all four, so it reports nothing changed in effect); `php artisan migrate:rollback --step=1` then `migrate` again both clean; `php artisan tinker --execute='echo App\Models\Role::where("name","employee")->first()->permissions->pluck("name")->filter(fn($p)=>str_starts_with($p,"vouchers")||str_starts_with($p,"customer-"))->values();'` shows `vouchers.redeem`, `customer-requests.manage` (and on dev possibly more from the seeder).

### 2. Tests
Files: `tests/Feature/Shop/RolePermissionGrantsTest.php`
What: add a second `MIGRATION` constant for the new file and three tests in the shape of the existing migration test: `migration_creates_the_four_permissions_and_grants_them_by_role` (roles created bare; `up()`; employee has exactly the two, manager the four, admin the four, barista none); `migration_is_additive_and_idempotent` (give the employee an unrelated permission first, run `up()` twice; the unrelated one survives, the two are present once); `migration_down_removes_only_the_four` (after `up()`, `down()` leaves the unrelated permission and removes the four from roles and from the table).
Check: `php artisan test --filter=RolePermissionGrantsTest` green.

### 3. Docs
Files: `docs/features/customer-requests.md`, `docs/features/voucher-management.md`, `docs/development/known-issues.md`, `DEPLOYMENT_SCRIPTS_README.md`
What: replace the "run the seeder on deploy" instructions in both feature docs with "granted by migration `2026_09_26_120000_…`; the seeder is for fresh installs only". Known issues: an entry "Customer requests and Vouchers tiles missing on production (2026-09-26)": cause (permissions never created because the seeder was never run on the live database), fix (this migration), and the rule going forward: **new permissions ship as a migration, never only in the seeder**. Deploy README: add that rule to the post-deploy notes next to the `schedule:list` check, with a one-line verification: `php artisan tinker --execute='echo App\Models\Permission::count();'` is too vague; instead list the four names and the tinker snippet from step 1.
Check: `grep -n "db:seed --class=RolesAndPermissionsSeeder" docs/features/customer-requests.md docs/features/voucher-management.md` → only in a "fresh install" sentence, if at all.

### 4. Format
What: `./vendor/bin/pint --dirty`.
Check: `./vendor/bin/pint --test --dirty` clean.

## Verification

1. `php artisan test --filter="RolePermissionGrantsTest|ShopVouchersTest|ShopRequestsTest|ShopHomeTest"` → green; `php artisan test` → 15 failed, the identical set; passed = 636 + 3.
2. `php artisan migrate:status | grep 2026_09_26_120000` → Ran (dev).
3. After the owner deploys: sign in on production as an employee → Home shows Customer requests and Vouchers; as a manager, the vouchers screen offers activation; the office customer-invoices pages are reachable for managers.

## Risks

- **Nothing destructive**: `syncWithoutDetaching` and `firstOrCreate` only add. `down()` is provided for symmetry and would remove the four; nobody should run it on production.
- **Role names**: the migration looks roles up by name and skips any that do not exist, as the earlier migrations do.
- **The seeder still exists and still grants more** (e.g. `fruit_veg.manage` to employees) than the migrations do; that is the fresh-install path and is unchanged.

## Review

### Revision 1 (2026-09-26, Planner)

Read `implemented.md` to the end; the migration follows the shop-mode pattern (additive `syncWithoutDetaching`, guarded role lookups, symmetric `down()`), the three tests pass, the docs carry the rule. Reran `php artisan test`: 15 failed / 640 passed, the identical set (636 + 3 + 1 data set). The owner deployed and confirmed both tiles now show on production.

**Steps 1–4: pass. Deviations: none.**

**Notes for Planner.**
1. Production not verified by the implementer: **verified by the Planner and by the owner's deploy.**
2. Other seeder-only grants possibly missing on production: **checked read-only after the deploy**: the production employee role holds every name in the seeder's employee list (14 of 14). Nothing else is missing.
3. A guard on the seeder against running over a live database: **housekeeping candidate**, recorded.
4. Admin grants are for consistency: **agreed.**

**Verdict: ACCEPTED.** Archive to `docs/planImp/archive/2026-09-26-cycle-20-permissions/`.
