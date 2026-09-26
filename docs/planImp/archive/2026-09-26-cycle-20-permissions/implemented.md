# Cycle 20 — Grant the customer-requests, voucher and customer-invoice permissions — implementation

Status: DONE
Plan revision: 1
Implementer: Opus
Date: 2026-09-26

## Baseline

HEAD: 35c861c6 — the earlier cycles are now committed, so the tree is clean apart
from this cycle's own files.

Test baseline: 15 failed / 636 passed (2721 assertions).

## Pre-flight

The four permission definitions and the role grants were read from the seeder
rather than taken from the plan, and they match:

```
database/seeders/RolesAndPermissionsSeeder.php:227  customer-invoices.manage
                                              :235  customer-requests.manage
                                              :243  vouchers.redeem
                                              :249  vouchers.manage
employee list (line 343-344): vouchers.redeem, customer-requests.manage
manager list: array_merge($employeePermissions, [... customer-invoices.manage,
                                                 vouchers.manage ...])
admin: every permission
```
So managers inherit the employee two and add the other two — which is the
`EMPLOYEE_GRANTS` + extras shape the plan describes.

The pattern migration `2026_09_23_150000_add_shop_mode_permissions.php` does **not**
pass `guard_name`, confirming the plan's instruction to follow it rather than the
cash-reconciliation one.

**I have not verified the production database myself** — I have no access to it.
The claim that the four names are absent there is the Planner's, made read-only via
the deploy host, and everything here is additive and idempotent, so it is correct
whether or not they are already present.

## Steps

### 1. The migration — done

Changed: `database/migrations/2026_09_26_120000_add_customer_requests_and_voucher_permissions.php (new)`.

Modelled on the shop-mode migration: `firstOrCreate` for each permission,
`syncWithoutDetaching` per role, roles looked up by name and skipped if absent, no
`guard_name`. The four definitions are copied verbatim from the seeder.

Check output — dev already had all four from the seeder, so `up()` alone proves
little. The rollback/re-migrate cycle is what proves it works on a database that
does **not** have them, which is the production case:
```
$ php artisan migrate                       → DONE
employee  ["customer-requests.manage","vouchers.redeem"]
manager   ["customer-invoices.manage","customer-requests.manage","vouchers.manage","vouchers.redeem"]
admin     ["customer-invoices.manage","customer-requests.manage","vouchers.manage","vouchers.redeem"]
barista   []

$ php artisan migrate:rollback --step=1     → DONE
permissions table has: 0 of the four
employee still has: 12 permissions          ← unrelated grants intact

$ php artisan migrate                       → DONE
restored: 4 of the four
employee: ["customer-requests.manage","vouchers.redeem"]
```

### 2. Tests — done

Changed: `tests/Feature/Shop/RolePermissionGrantsTest.php` — four tests.

```
$ php artisan test --filter=RolePermissionGrantsTest
✓ requests migration creates the four permissions and grants them by role
✓ requests migration is additive and idempotent
✓ requests migration down removes only the four
✓ the migration grants what the seeder grants
  ... plus the 6 that were there
Tests:    10 passed (75 assertions)
```

The fourth is beyond the plan and is the one I would keep if only one survived:
**it asserts the migration grants exactly what the seeder grants** for these four,
per role. The whole incident is two sources of truth disagreeing about who holds
what, and nothing was checking they agreed. It ends with
`assertNotSame([], $fromSeeder['employee'])` so it cannot pass vacuously.

Two things the harness made me fix, both worth recording because they would
silently have made the tests meaningless:

- **`RefreshDatabase` runs this migration**, since it lives in
  `database/migrations/`. So the four permissions already exist in every test
  database and `assertDatabaseMissing` failed. The helper now deletes them first,
  which is what makes the tests exercise `up()` rather than assert the state the
  harness built.
- **`till_review.export` is created by the cash-reconciliation migration**, so
  `Permission::create` for it hit a unique constraint. `firstOrCreate` instead —
  and it is a better fixture for it, being genuinely pre-existing, exactly like the
  live database's extras.

I also broke three passing tests briefly by calling `$this->refreshDatabase()`
inside a test; it rolls back the transaction `RefreshDatabase` is holding. The
comparison test now reaches the "production" state by deleting the four in place.

### 3. Docs — done

Changed: `docs/features/customer-requests.md` and
`docs/features/voucher-management.md` (the "run the seeder on deploy" instructions
replaced — both now say the migration does it and that the seeder is fresh-install
only), `docs/development/known-issues.md` (a full entry with the verification
snippet), `DEPLOYMENT_SCRIPTS_README.md` (a "Permissions (post-deploy check)"
section next to the `schedule:list` one from cycle 18).

Both new documents carry the rule in the same words: **a new permission ships as a
migration, never only in the seeder.**

### 4. Format — done. `./vendor/bin/pint --test --dirty` → PASS.

## Verification

**1. Tests**
```
$ php artisan test --filter="RolePermissionGrantsTest|ShopVouchersTest|ShopRequestsTest|ShopHomeTest"
Tests:    44 passed (270 assertions)
$ php artisan test
Tests:    15 failed, 640 passed (2762 assertions)
```
The identical set. 640 = 636 + 4 (the plan budgeted 3; the seeder-comparison test
is the extra).

**2. `php artisan migrate:status`**
```
2026_09_26_120000_add_customer_requests_and_voucher_permissions ... [94] Ran
```

**3. After the owner deploys** — not something I can do. What I could close is the
link between the permission and the tile, which is the whole point of the fix: on
dev, Home as the signed-in employee renders
```
["Stock scan","Find product","Receive delivery","Print labels",
 "Customer requests","Vouchers","Fruit & veg"]
```
Both tiles present, with their badges. That is the state production should reach
once `migrate --force` runs.

**This ships with the next deploy's `php artisan migrate --force`. No seeder run is
needed, and the seeder must not be run against the live database.**

## Deviations

None.

## Files changed

```
?? database/migrations/2026_09_26_120000_add_customer_requests_and_voucher_permissions.php
 M tests/Feature/Shop/RolePermissionGrantsTest.php
 M docs/features/customer-requests.md
 M docs/features/voucher-management.md
 M docs/development/known-issues.md
 M DEPLOYMENT_SCRIPTS_README.md
```
The tree was clean at the start of this cycle, so this is the whole of it.

**Not committed, not pushed, not deployed.**

## Notes for Planner

1. **I could not verify production myself.** The claim that the four names are
   absent there is the Planner's, from a read-only check. Everything in this cycle
   is additive and idempotent, so it is correct either way — but the post-deploy
   check in the deploy README is what will actually confirm it, and it is worth
   someone running the tinker snippet once after the deploy rather than assuming.

2. **The seeder still grants employees more than any migration does** — notably
   `fruit_veg.manage`, `coffee.manage`, `kds.access`, `deliveries.view`,
   `labels.view`, `categories.view`. If production is missing those too, the same
   class of problem exists for whatever they gate, and this cycle did not look.
   Comparing the seeder's full employee list against the live database would be a
   short, purely read-only cycle and would find any remaining gaps in one pass.

3. **`RolesAndPermissionsSeeder` is now documented as fresh-install-only in three
   places** but nothing enforces it. A guard in the seeder — refuse to run when the
   database already holds permissions it does not list, unless `--force` — would
   make the rule mechanical. Worth considering; I did not add it, as it is beyond
   this cycle and changes a tool people may be using deliberately.

4. **Admins bypass permission checks**, so the admin grants here change nothing
   functional. They are for completeness, as the seeder does it, and so that any
   future code that reads permissions directly rather than through `hasPermission`
   sees a consistent picture.
