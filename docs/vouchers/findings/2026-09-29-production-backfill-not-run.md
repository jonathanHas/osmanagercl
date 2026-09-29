# Production: 37 of 39 vouchers have no till product

Date: 2026-09-29
Found by: Planner (Fable 5.1), read-only check of production
Status: **PARKED by the owner on 2026-09-29.** Not part of vouchers cycle 2.

## What happened

Cycle 28 is deployed and the scheduler runs `vouchers:sync-till` every minute
(`/etc/cron.d/osmanager`, as `www-data`). But production has 39 vouchers
(37 active, 2 deactivated) and only 2 hidden till products. The other 37
vouchers have `pos_product_id` NULL.

## Why

Till products are created when a voucher is generated, activated, deducted or
has its status changed. Vouchers that existed before the deploy get theirs from
the one-off backfill, `vouchers:sync-pos-products`, which has not been run on
production.

## Effect

Scanning one of those 37 labels at the till gives "product not found", so it
cannot be redeemed at the till. Manual deduct on `/vouchers` and
`/shop/vouchers` still works, and the first manual deduct on a voucher creates
its till product.

## To resolve (owner, on production)

```
cd /var/www/html/osmanager
sudo -u www-data php artisan vouchers:sync-pos-products --dry-run
sudo -u www-data php artisan vouchers:sync-pos-products
```

## What it does not affect

The till sync itself, the exceptions page, finance treatment of `paperin`.

## Link to vouchers cycle 2

The activity screen shows "N vouchers have no till product" as a health line,
so this stays visible until the backfill is run. Vouchers cycle 2 adds no button to fix
it.
